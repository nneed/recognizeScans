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
            if ($action->actionMethod == 'actionTest' ) {
                return parent::beforeAction($action);
            }
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

    public function actionTest($id)
    {
        $queue = Queue::findOne($id);
    }



}
