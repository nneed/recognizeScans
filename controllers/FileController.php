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

class FileController extends ActiveController
{
    public $modelClass = 'app\models\Queue';

/*    public function beforeAction($action)
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
    }*/

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        return $actions;
    }

    public function actionCreate()
    {
        $abonentIdentifier = Yii::$app->request->post('abonentIdentifier');
        $passport = Yii::$app->request->post('passport');
        $data['passport'] = isset($passport['image'])?$passport['image']:null;
        $data['documents_with_sign'] = Yii::$app->request->post('documents_with_sign');
        if (!$abonentIdentifier){
            throw new yii\web\UnsupportedMediaTypeHttpException("Неверный формат параметра 'abonentIdentifier'");
        }
        if (!$data['documents_with_sign']){
            throw new yii\web\UnsupportedMediaTypeHttpException("Неверный формат параметра 'documents_with_sign'");
        }
        if (!$passport || !isset($passport['image']) || !$passport['first_name'] || !$passport['last_name'] || !$passport['midle_name'] || !$passport['issued_by']){
            throw new yii\web\UnsupportedMediaTypeHttpException("Неверный формат параметра 'passport'");
        }
        unset($passport['image']);
     //   var_dump($data);die();

        $queue = new Queue();
        $queue->abonentIdentifier = $abonentIdentifier;
        $queue->type = Queue::COPY_CERT;
        $queue->user_id = 1;
        $queue->status = Queue::PENDING;
        $queue->abonent_data = json_encode($passport);

        $transaction = Yii::$app->db->beginTransaction();

        if ($queue->save()){
            try{
                $queue->SaveScans($data);
            }catch(Exception $e){
                throw new Exception(json_encode($e->getMessage()));
            }

/*            $id_event = Yii::$app->queue->push(new ScanDocJob([
                'idQueue' => $queue->id,
                'abonentIdentifier' => $abonentIdentifier
            ]));*/

            $id_event = 1;


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
                       if (!$res) $resultFalse++;
                    }else{
                        $squaredScan = new SquaredScan($file->data);
                        $res = $squaredScan->test();
                    }
                    $file->signed = $res;
                    if (!$file->signed) $resultFalse++;
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
            $response = $client->send($queue->abonentIdentifier, $result);
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
