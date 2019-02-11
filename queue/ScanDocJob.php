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
use Yii;
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
/*        if ($queue->status != Queue::PENDING){
            return;
        }*/

        $queue->status = Queue::PROCESSING;
        if (!$queue->save())  throw new Exception(json_encode($queue->errors));

        $files = $queue->files;
        $resultFalse = 0;
        $rejectMessage = '';

        foreach ($files as $file) {


                try {
                    if (filesize($file->data) > 1024*1024*10) {
                        throw new Exception("Big size", 1);
                    }
                    if($file->type == File::SCAN_PASSPORT) {

                        $token = uniqid();
                        $scan = file_get_contents($file->data);
                        $abonent_data = (array)json_decode($queue->abonent_data);
                        $needles = $abonent_data;
                        
                        for ($threshold_shift = 10; $threshold_shift <= 80 ; $threshold_shift+=10) { 
                            try{
                                $ocr = new COCREngine(COCREngine::TYPE_PASSPORT, $token, $scan, $needles, $threshold_shift, COCREngine::DEBUG);
                                $res = $ocr->recognize(); 
                            }catch(\Exception $e){
                                Yii::error('recognizePassport : ' .var_export($e,true));
                            }

                            $res = (boolean)$res['check'];
                            if($res) break;
                        }
                        if(!$res){
                            $resultFalse++;
                            $rejectMessage .= '#'.$file->id . File::SCAN_PASSPORT_WRONG . ' ';
                        }
                    }else{

                        exec("python3.6 /var/www/html/queue/python/recognize.py ".$file->data , $output, $return_var);

                        if ($return_var === 1) {
                           throw new \Exception("Расспознование подписи завершилось с ошибкой");
                        }
                        $res = (boolean)$output[0];
                        if (!$res){
                            $resultFalse++;
                            $rejectMessage .=  '#'.$file->id .File::SCAN_WITH_SIGN_WRONG . ' ';
                        }
                    }
                    $file->signed = $res;


                }catch (\Exception $e) {
                    file_put_contents('/var/www/html/queue/test.txt', $queue->id."--->".var_export($e->getMessage(),true), FILE_APPEND);
                    $rejectMessage .= $file->id .$e->getMessage() . ' ';
                    $file->signed = false;
                    $file->save();
                    $queue->status = Queue::UnknownError;
                    $queue->result = false;
                    $resultFalse++;
//                    $queue->save();
                    yii::error($e);
                    break;
                }
                $file->save();
     
        }
        $result = !((boolean)$resultFalse);
        try{
            $client = new EDO_FL_Client();
            $response = $client->send($this->abonentIdentifier, $result, $rejectMessage);
            file_put_contents('/var/www/html/queue/test.txt', $queue->id."--->".var_export($rejectMessage,true), FILE_APPEND);
        }catch (Exception $e){
            $queue->status = Queue::UnknownError;
            file_put_contents('/var/www/html/queue/test.txt', $queue->id."--->".var_export($e->getMessage(),true), FILE_APPEND);
            $queue->result = $result;
            $queue->save();
            throw $e;
        }
        if ($response->data['IsSuccess'] == true) {

            $queue->status = Queue::FINISHED;
        }else{
            $queue->status = array_search($response->data['ErrorType'], Queue::$errorsForEdoFl);
            file_put_contents('/var/www/html/queue/test.txt', "\n".$queue->id."--->".var_export($response->data,true), FILE_APPEND);
            if (!$queue->status) {
                $queue->status = Queue::UnknownError;
                $queue->result = $result;
            }
        }

        $queue->result = $result;
        $queue->save();
        if ($queue->status == Queue::UnknownError) {
            mail("nukazankov@rus-telecom.ru", "Сервис проверки документов", "Неизвестная ошибка id: ".$queue->id);
            mail("service1@rus-telecom.ru", "Сервис проверки документов", "Неизвестная ошибка id: ".$queue->id);
        }
    }
}