<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 11.02.2019
 * Time: 14:03
 */

namespace app\services;

use app\models\Queue;
use app\queue\EDO_FL_Client;
use Yii;
use \Exception;
use \app\models\File;
use app\queue\handlers\PassportHandler;
use app\queue\handlers\WithSignHandler;

class RecognizeService
{
    public $client;

    public $rejectMessage;

    public $result;

    public $handlers = [];

    public function __construct(EDO_FL_Client $client, PassportHandler $passportHandler, WithSignHandler $withSignHandler)
    {
        $this->client = $client;
        $this->handlers = [
            File::SCAN_PASSPORT => $passportHandler,
            File::SCAN_WITH_SIGN => $withSignHandler,
        ];
    }

    public function recognize($queue)
    {
        $queue->status = Queue::PROCESSING;
        if (!$queue->save())  throw new Exception(json_encode($queue->errors));

        $files = $queue->files;

        foreach ($files as $file) {

            try {

                if (filesize($file->getUploadedFilePath('data')) > 1024*1024*10) {
                    throw new Exception("Big size", 1);
                }

                $res = $this->handlers[$file->type]->handle($file);

                $this->rejectMessage .= $res;

            }catch (\Exception $e) {
                $this->rejectMessage .= $file->id .$e->getMessage() . ' ';
                $file->signed = false;
                $file->save();
                $queue->status = Queue::UnknownError;
                $queue->result = false;
                yii::error($e);
            }
        }
        $this->result = !((boolean)$this->rejectMessage);
        $this->sendResult($queue);

        if ($queue->status == Queue::UnknownError) {
            $this->sendEmailError($queue->id);
        }

    }


    private function sendResult($queue)
    {
        try{
            $response = $this->client->send($queue->abonentIdentifier, $this->result, $this->rejectMessage);
        }catch (Exception $e){
            $queue->status = Queue::UnknownError;
            file_put_contents('/var/www/html/queue/test.txt', $queue->id."--->".var_export($e->getMessage(),true), FILE_APPEND);
            $queue->result = $this->result;
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
                $queue->result = $this->result;
            }
        }

        $queue->result = $this->result;
        $queue->save();

    }

    private function sendEmailError($id): void
    {
        mail("nukazankov@rus-telecom.ru", "Сервис проверки документов", "Неизвестная ошибка id: ".$id);
        mail("service1@rus-telecom.ru", "Сервис проверки документов", "Неизвестная ошибка id: ".$id);
    }
}