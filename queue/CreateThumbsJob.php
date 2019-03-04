<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 04.03.2019
 * Time: 13:25
 */

namespace app\queue;

use yii\base\BaseObject;
use app\models\Queue;

class CreateThumbsJob extends BaseObject implements \yii\queue\Job
{
    public $idQueue;

    public function execute($queue)
    {
        $queue = Queue::findOne($this->idQueue);
        foreach ($queue->files as $file){
            $file->getThumbFileUrl('data');
        }
    }
}