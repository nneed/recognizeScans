<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 09.11.2017
 * Time: 11:26
 */

namespace app\queue;
use yii\base\BaseObject;
use app\models\Queue;
use app\models\scan_service\SquaredScan;
use yii;
use \Exception;

class ScanDocJob extends BaseObject implements \yii\queue\Job
{
    public $idQueue;
    public $abonentIdentifier;


    public function execute($queue)
    {
        $queue = Queue::findOne($this->idQueue);
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
                    $squaredScan = new SquaredScan($file->data);
                    $file->signed = $squaredScan->test();
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
            $response = $client->send($this->abonentIdentifier, $result);
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
        }
        $queue->save();
    }
}