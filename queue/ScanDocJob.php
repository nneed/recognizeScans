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
        $result = false;
        foreach ($files as $file) {
            if ($file->signed === null){
                try {
                    $squaredScan = new SquaredScan($file->data);
                    $file->signed = $result = $squaredScan->test();
                } catch (Exception $e) { // TODO: Доделать исключение в случае ошибки распознования.
                    $file->signed = false;
                }
                $file->save();
            }
        }

        $client = new EDO_FL_Client();
        $response = $client->send($this->abonentIdentifier, $result);

        $queue->result = $result;
        if($response->data['IsSuccess'] == true){
            $queue->status =  \app\models\Queue::FINISHED;
        }else{
            $queue->status = array_search($response->data['ErrorType'], \app\models\Queue::$statuses);
        }
        $queue->save();
    }
}