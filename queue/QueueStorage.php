<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 01.02.2018
 * Time: 13:23
 */

namespace app\queue;
use Yii;
use app\models\File;
use yii\web\UnsupportedMediaTypeHttpException;

class QueueStorage
{
    public $abonentIdentifier;
    public $passport;
    public $documents_with_sign;
    public $image;
    public $id;

    public function __construct($data)
    {
        $this->abonentIdentifier = $data['abonentIdentifier'];
        $this->passport = isset($data['passport'])?$data['passport']:null;
        //todo Добавить проверки
        $this->documents_with_sign = $data['documents_with_sign'];
        //todo сделать возможность сохранения других документов
    }

    public function validateData()
    {
        if (!$this->abonentIdentifier) {
            throw new UnsupportedMediaTypeHttpException("Неверный формат параметра 'abonentIdentifier'");
        }
        if (!$this->documents_with_sign) {
            throw new UnsupportedMediaTypeHttpException("Неверный формат параметра 'documents_with_sign'");
        }
        if($this->passport){
            if (empty($this->passport['image']) || empty($this->passport['first_name']) || empty($this->passport['last_name']) || empty($this->passport['midle_name']) || empty($this->passport['issued_by'])) {
                throw new UnsupportedMediaTypeHttpException("Неверный формат параметра 'passport'");
            }
            $this->image = $this->passport['image'];
            unset($this->passport['image']);
        }

    }

    public function save()
    {
        foreach ($this->documents_with_sign as $string){
            $this->saveFile($string);
        }
        if($this->image){
            $this->saveFile($this->image, File::SCAN_PASSPORT);
        }

    }

    private function saveFile($string, $type = File::SCAN_WITH_SIGN)
    {
        $path = Yii::getAlias('@runtime/scans');
        $alias = $type?'passport':'';

        if (!file_exists($path)) mkdir($path, 0777);

        $filename = $this->id . '_' . uniqid() . $alias . '.jpg';

        $fp = fopen($path . '/' . $filename, "wb");
        if (!fwrite($fp, base64_decode(trim($string)))) {
            throw new \yii\web\BadRequestHttpException('Невозможно создать файл на сервере.', 400);
        }
        fclose($fp);

        $file = new File();
        $file->data = $path . '/' . $filename;
        $file->queue_id = $this->id;
        $file->type = $type;

        if (!$file->save()) {
            //todo Сделать удаление файла
            throw new \Exception(json_encode($file->errors));
        }

    }
}