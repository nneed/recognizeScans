<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 26.02.2019
 * Time: 12:51
 */

namespace app\queue;

use yii\web\UploadedFile;
use \Yii;

class UploadedFileFromJson  extends UploadedFile
{

    private static $_files;

    public static function getInstancesFromJson(array $_arrBase64, $id_queue)
    {
        $files = self::loadFiles($_arrBase64, $id_queue);
        $results = [];
        foreach ($files as $key => $file) {
            $results[] = new static($file);
        }

        return $results;
    }

    private static function loadFiles($_arrBase64, $id_queue)
    {
        self::$_files = [];
        if (isset($_arrBase64) && is_array($_arrBase64)) {
            foreach ($_arrBase64 as $key => $_strBase64) {
                $path = self::createTempFiles($_strBase64,$id_queue);
                $size = filesize($path);
                self::$_files[$id_queue.$key] = [
                    'name' => $id_queue.$key,
                    'tempName' => $path,
                    'type' => '.jpg',
                    'size' => $size,
                    'error' => 0,
                ];
            }
        }

        return self::$_files;
    }


    private static function createTempFiles($_strBase64, $name) : string
    {
        $path = Yii::getAlias('@runtime'). '/' . Yii::$app->params['ScansTemp'];
        if (!file_exists($path)) mkdir($path, 0777);
        $filename = $name . uniqid() . '.jpg';

        $fp = fopen($path . '/' . $filename, "wb");
        if (!fwrite($fp, base64_decode(trim($_strBase64)))) {
            throw new \yii\web\BadRequestHttpException('Невозможно создать файл на сервере.', 400);
        }
        fclose($fp);
        return $path . '/' . $filename;
    }


    public function saveAs($file, $deleteTempFile = false)
    {
        if ($this->error == UPLOAD_ERR_OK) {
            if(copy($this->tempName, $file)){
                return unlink($this->tempName);
            }

        }

        return false;
    }


}