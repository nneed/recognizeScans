<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 11.02.2019
 * Time: 15:52
 */

namespace app\queue\handlers;

use app\models\File;
use app\queue\handlers\ScanHandlerInterface;

class WithSignHandler implements ScanHandlerInterface
{
    public function handle($file) :string
    {
        exec("python3.6 /var/www/html/queue/python/recognize.py ".$file->getUploadedFilePath('data'). ' '.$file->id, $output, $return_var);
        if ($return_var === 1) {
            throw new \Exception("Расспознование подписи завершилось с ошибкой");
        }
        $res = $output[0] === 'True';
        unset($output[0]);
        $file->signed = $res;
        $file->save();
        if (!$res){
            return  '#'.$file->id .File::SCAN_WITH_SIGN_WRONG . ' ';
        }

        return '';
    }
}