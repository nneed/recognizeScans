<?php

namespace app\controllers;

use yii;
use app\models\Queue;
use app\models\File;
use app\models\scan_service\SquaredScan;
use app\models\User;
use yii\base\Exception;
use yii\rest\ActiveController;
use app\queue\ScanDocJob;
use app\queue\EDO_FL_Client;
use yii\web\UnauthorizedHttpException;
use app\models\scan_service\COCREngine;
use app\queue\QueueStorage;

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
            $user = User::findOne(['username' => $username]);
            if (!$user->validatePassword($password))
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

        if ($queue->save()){
            try{
                $queueStorage->id = $queue->id;
                $queueStorage->save();
            }catch(Exception $e){
                throw new Exception($e->getMessage());
            }

            $id_event = Yii::$app->queue->push(new ScanDocJob([
                'idQueue' => $queue->id,
                'abonentIdentifier' => $queueStorage->abonentIdentifier
            ]));


        }else{
            $transaction->rollBack();
            throw new Exception(json_encode($queue->errors));
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

                        $token = uniqid();
                        $scan = file_get_contents($file->data);
                        $abonent_data = (array)json_decode($queue->abonent_data);
                        $needles = $abonent_data;
                        $threshold_shift = 50;

                        $ocr = new COCREngine(COCREngine::TYPE_PASSPORT, $token, $scan, $needles, $threshold_shift, COCREngine::DEBUG);

                        $res = $ocr->recognize();
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
