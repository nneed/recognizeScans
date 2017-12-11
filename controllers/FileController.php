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
        $data = Yii::$app->request->post('data');
        $abonentIdentifier = Yii::$app->request->post('abonentIdentifier');
        $queue = new Queue();
        $queue->abonentIdentifier = $abonentIdentifier;
        $queue->type = Queue::COPY_CERT;
        $queue->user_id = 1;
        $queue->status = Queue::PENDING;
        if ($queue->save()){

            foreach ($data as $string) {

                $path = Yii::getAlias('@webroot/upload');
                if (!file_exists($path)) mkdir($path, 0755);

                $filename = $queue->id .'_'. uniqid() . '.jpg';

                 $fp = fopen( $path.'/'.$filename, "wb" );
                 if (!fwrite( $fp, base64_decode( trim($string ) ))){
                    throw new \Exception('Невозможно создать файл на сервере');
                 }

                fclose($fp);

                $file = new File();
                $file->data = $path.'/'.$filename;
                $file->queue_id = $queue->id;

                if (!$file->save()) throw new \Exception(json_encode($file->errors));

            }

            Yii::$app->queue->push(new ScanDocJob([
                'idQueue' => $queue->id,
                'abonentIdentifier' => $abonentIdentifier
            ]));

        }else{
            throw new Exception(json_encode($queue->errors));
        }

        return $queue;

    }


    public function actionTest()
    {
        $abonentIdentifier = yii::$app->request->get('abonentIdentifier');
        $idQueue = yii::$app->request->get('id');

        $queue = Queue::findOne($idQueue);
        if ($queue->status != Queue::PENDING){
           // return;
        }
        $queue->status = Queue::PROCESSING;
        if (!$queue->save())  throw new \Exception(json_encode($queue->errors));

        $files = $queue->files;
        $resultFalse = 0;
            foreach ($files as $file) {
                if ($file->signed === null){
                    try {
                        $squaredScan = new SquaredScan($file->data);
                        $file->signed = $squaredScan->test();
                        if (!$file->signed) $resultFalse++;
                    } catch (\Exception $e) {
                        $file->signed = false;
                        $file->save();
                        $queue->status = Queue::UnknownError;
                        $queue->result = false;
                        $resultFalse++;
                        $queue->save();
                        //throw new \Exception($e->getMessage());
                    }
                    $file->save();
                }
            }
        $result = !((boolean)$resultFalse);
        try{
            $client = new EDO_FL_Client();
            $response = $client->send($abonentIdentifier, $result);
        }catch (\Exception $e){
            $queue->status = Queue::PENDING;
            $queue->result = $result;
            throw $e;
        }

        if ($response->data['IsSuccess'] == true) {

            //  if(true){
            $queue->status = Queue::FINISHED;
            $queue->result = $result;

        } else {
            $queue->status = array_search($response->data['ErrorType'], \app\models\Queue::$statuses);
        }
        $queue->save();
    }

}
