<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 01.02.2018
 * Time: 13:23
 */

namespace app\queue;
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
        if ($this->documents_with_sign) {
            $this->saveFile($this->documents_with_sign, File::SCAN_WITH_SIGN);
        }
        if($this->image){
            $this->saveFile([$this->image], File::SCAN_PASSPORT);
        }

    }

    private function saveFile($array, $type)
    {
        $files = UploadedFileFromJson::getInstancesFromJson($array, $this->id);
        foreach ($files as $oneFile){
            $file = new File();
            $file->queue_id = $this->id;
            $file->type = $type;
            $file->data = $oneFile;
            if (!$file->save()) {
                throw new \Exception(json_encode($file->errors));
            }
        }


    }
}