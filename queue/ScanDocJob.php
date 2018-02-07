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
use \app\models\File;
use app\models\scan_service\COCREngine;

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
        $rejectMessage = '';
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
                        var_dump($needles);

                        var_dump($res?'Паспорт распознан':'Паспорт нераспознан');
                        if(!$res){
                            $resultFalse++;
                            $rejectMessage .= $file->id . File::SCAN_PASSPORT_WRONG . ' ';
                        }
                    }else{
                        $squaredScan = new SquaredScan($file->data);
                        $res = $squaredScan->test();
                        var_dump('expression'.$res);
                        if (!(boolean)$res){
                            $resultFalse++;
                            $rejectMessage .=  $file->id .File::SCAN_WITH_SIGN_WRONG . ' ';
                        }
                    }
                    $file->signed = $res;


                }catch (Exception $e) {
                    $rejectMessage .= $file->id .$e->getMessage() . ' ';
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
        var_dump($rejectMessage);
        try{
            $client = new EDO_FL_Client();
            $response = $client->send($this->abonentIdentifier, $result, $rejectMessage);
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

        }else{
            $queue->status = array_search($response->data['ErrorType'], \app\models\Queue::$statuses);
            if (!$queue->status) {
                $queue->status = Queue::UnknownError;
                $queue->result = $result;
            }
        }
        var_dump($response->data);
        $queue->save();
    }
}