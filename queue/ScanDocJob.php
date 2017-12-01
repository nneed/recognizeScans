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
use yii\httpclient\Client;


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

        //Жадно загрузим все
        //  foreach (Queue::find()->where(['id'=>$idQueue])->with('files')->each() as $queue){

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

        $client = new Client(['baseUrl' => 'http://dev-1601/IDE.Web/']);

        $response = $client->createRequest()
            ->setMethod('post')
            ->setHeaders(
                [
                    'Authorization' => 'Basic checker:1',
                ]
            )
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl('/api/Abonent/SetAbonentVerificationStatus')
            ->setData([
                'AbonentIdentifier' => $this->abonentIdentifier,
                'IsDocumentsAccepted' => $result,
                'RejectReason' => "",
            ])
            ->send();
        $queue->result = $result;
        if($response->data['IsSuccess'] == true){

            //  if(true){
            $queue->status =  \app\models\Queue::FINISHED;


        }else{
            $queue->status = array_search($response->data['ErrorType'], \app\models\Queue::$statuses);
        }
        $queue->save();
    }
}