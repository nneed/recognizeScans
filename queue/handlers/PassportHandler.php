<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 11.02.2019
 * Time: 15:49
 */

namespace app\queue\handlers;

use app\models\scan_service\COCREngine;
use app\queue\handlers\ScanHandlerInterface;

class PassportHandler implements ScanHandlerInterface
{

    public function handle($file): string
    {
        $token = uniqid();
        $scan = file_get_contents($file->getUploadedFilePath('data'));
        $queue = $file->queue;
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
        $file->signed = $res;
        $file->save();
        if(!$res){
            return '#'.$file->id . File::SCAN_PASSPORT_WRONG . ' ';
        }

        return '';
    }
}