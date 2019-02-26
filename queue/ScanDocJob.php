<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 09.11.2017
 * Time: 11:26
 */

namespace app\queue;
use app\queue\handlers\PassportHandler;
use app\queue\handlers\WithSignHandler;
use yii\base\BaseObject;
use app\models\Queue;

use app\services\RecognizeService;

class ScanDocJob extends BaseObject implements \yii\queue\Job
{
    public $idQueue;
    public $abonentIdentifier;
    public $service;

    public function execute($queue)
    {
        $queue = Queue::findOne($this->idQueue);
        $this->service = new RecognizeService(new EDO_FL_Client(), new PassportHandler(), new WithSignHandler());
        $this->service->recognize($queue);
    }
}