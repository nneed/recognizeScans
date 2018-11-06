<?php

namespace app\controllers;

use yii;
use app\models\Queue;
use app\models\File;
use app\models\User;
use app\models\scan_service\SquaredScan;
use yii\base\Exception;
use yii\rest\ActiveController;
use app\queue\ScanDocJob;
use app\queue\EDO_FL_Client;
use yii\web\UnauthorizedHttpException;
use app\models\scan_service\COCREngine;
use app\queue\QueueStorage;
use app\models\scan_service\ImageMagickCommands;

class FileController extends ActiveController
{
    public $modelClass = 'app\models\Queue';

        public function beforeAction($action)
        {
            $authData = Yii::$app->request->getHeaders()['Authorization'];
            if (!$authData) throw new UnauthorizedHttpException('Требуется авторизация');
            $arr = explode(':',base64_decode(str_replace('Basic' ,'', $authData)));
            if(count($arr) < 2) throw new UnauthorizedHttpException('Строка авторизации имеет не верный формат');
            list($username,$password) = $arr;
            $user = User::findOne(['username' => trim($username)]);
            if (!$user) throw new UnauthorizedHttpException();
            if (!$user->validatePassword(trim($password)))
                throw new UnauthorizedHttpException();
            return parent::beforeAction($action);
        }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        return $actions;
    }

    public function actionCreate()
    {

        $edofl = Yii::$app->request->post('edofl');
        $queueStorage = new QueueStorage($edofl);

        $queueStorage->validateData();

        $queue = new Queue();
        $queue->abonentIdentifier = $queueStorage->abonentIdentifier;
        $queue->type = Queue::COPY_CERT;
        $queue->user_id = 1;
        $queue->status = Queue::PENDING;
        $queue->abonent_data = json_encode($queueStorage->passport);

        $transaction = Yii::$app->db->beginTransaction();

        try{
            if ($queue->save()){
                $queueStorage->id = $queue->id;
                $queueStorage->save();

                $id_event = Yii::$app->queue->push(new ScanDocJob([
                    'idQueue' => $queue->id,
                    'abonentIdentifier' => $queueStorage->abonentIdentifier
                ]));
            }else{
                throw new Exception(json_encode($queue->errors));
            }
        }catch(\Exception $e){
            mail("nukazankov@rus-telecom.ru", "Сервис проверки документов", "Ошибка id: ".$queue->id. ' Ошибка '.$e->getMessage());
            $transaction->rollBack();
            throw new Exception($e->getMessage());
        }

        if ($id_event === null) $transaction->rollBack();
        $transaction->commit();
        return $queue->id;

    }

    public function actionTest()
    {
        $abonentIdentifier = yii::$app->request->get('abonentIdentifier');
        $idQueue = yii::$app->request->get('id');
        $queue = Queue::findOne($idQueue);

        if ($queue->status != Queue::PENDING){
            return;
        }
        $queue->status = Queue::PROCESSING;
        if (!$queue->save())  throw new Exception(json_encode($queue->errors));

        $files = $queue->files;
        $resultFalse = 0;
        $rejectMessage = '';

        foreach ($files as $file) {
            if ($file->signed === null){
                try {
                    if($file->type == File::SCAN_PASSPORT) {
                        $ImageMagickCommands = new ImageMagickCommands($file->data);
                        $ImageMagickCommands->prepareScanPassport();
                        $token = uniqid();
                        $scan = file_get_contents($ImageMagickCommands->output);
                        $abonent_data = (array)json_decode($queue->abonent_data);
                        $needles = $abonent_data;
                        $threshold_shift = 30;

                        $ocr = new COCREngine(COCREngine::TYPE_PASSPORT, $token, $scan, $needles, $threshold_shift, COCREngine::DEBUG);
                       // $ImageMagickCommands->removeTempFile();

                        $res = $ocr->recognize();
                       // var_dump($res);die();
                        $res = (boolean)$res['check'];
                        if (!$res) {
                            $resultFalse++;
                            $rejectMessage = File::SCAN_PASSPORT_WRONG;
                        }
                    }else{
                        $squaredScan = new SquaredScan($file->data);
                        $res = $squaredScan->test();
                    }
                    $file->signed = $res;
                    if (!$file->signed) {
                        $resultFalse++;
                        $rejectMessage = File::SCAN_WITH_SIGN_WRONG;
                    }
                }catch (Exception $e) {
                    if($e->statusCode == 500) throw $e;
                    $file->signed = false;
                    $file->save();
                    $queue->status = Queue::UnknownError;
                    $queue->result = false;
                    $resultFalse++;
                    $queue->save();
                    yii::error($e);
                }
                $file->save();
            }
        }


        $result = !((boolean)$resultFalse);
        try{
            $response = new \stdClass();
            $response->data = ['IsSuccess'=>true];
            $client = new EDO_FL_Client();
            $response = $client->send($queue->abonentIdentifier, $result, $rejectMessage);
        }catch (Exception $e){
            $queue->status = Queue::UnknownError;
            $queue->result = $result;
            $queue->save();
            throw $e;
        }

        if ($response->data['IsSuccess'] == true) {

            //  if(true){
            $queue->status = Queue::FINISHED;
            $queue->result = $result;

        } else {
            $queue->status = array_search($response->data['ErrorType'], \app\models\Queue::$statuses);
            if (!$queue->status) {
                $queue->status = Queue::UnknownError;
                $queue->result = $result;
            }
        }
        $queue->save();
    }



}
