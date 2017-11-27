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

    public function execute($queue)
    {
        $queue = Queue::findOne($this->idQueue);
        $files = $queue->files;
        foreach ($files as $file){
            try{
                $squaredScan = new SquaredScan($file->data);
                $file->result = $squaredScan->test();
            }catch (Exception $e){
                $file->result = false;
            }
            $file->save();
        }
        $queue->status = 2;
        $queue->save();

    }
}