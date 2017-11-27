<?php
namespace app\controllers;
use app\models\Queue;
use app\models\File;
use app\models\scan_service\SquaredScan;
use yii\base\Exception;
use yii\rest\ActiveController;
use app\queue\ScanDocJob;
use Yii;

class FileController extends ActiveController
{
    public $modelClass = 'app\models\Queue';

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        return $actions;
    }

    public function actionCreate()
    {

        $data = Yii::$app->request->post('data');
        $queue = new Queue();
        $queue->status = 1;
        if ($queue->save()){

            $path = Yii::getAlias('@webroot/upload');
            if (!file_exists($path)) mkdir($path, 0755);

            $filename = uniqid() . '.jpg';
            $fp = fopen($path.'/'.$filename, 'w');
            if (!fputs($fp, base64_decode($data))){
                throw new Exception('Невозможно создать файл на сервере');
            }
            fclose($fp);

            $file = new File();
            $file->data = $path.'/'.$filename;
            $file->queue_id = $queue->id;

            if (!$file->save()) return $file->errors;

            Yii::$app->queue->push(new ScanDocJob([
                'idQueue' => $queue->id,
            ]));

        }else{
            return $queue->errors;
        }

        return $queue;

    }


    public function actionTest()
    {
        $id = yii::$app->request->get('id');
        $queue = Queue::findOne($id);
        $files = $queue->files;
        foreach ($files as $file){
            try{
                $squaredScan = new SquaredScan($file->data);
                $file->result = $squaredScan->test();
            }catch (Exception $e){
                $file->result = false;
            }
            $file->save();
            $test = $squaredScan->output();
        }
        $queue->status = 2;
        $queue->save();
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        return $test;

    }

}
