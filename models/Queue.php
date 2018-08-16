<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use \yii\db\ActiveRecord;
use Yii;

/**
 * This is the model class for table "queue".
 *
 * @property integer $id
 * @property string $creation_time
 * @property string $update_time
 * @property string $abonentIdentifier
 * @property integer $user_id
 * @property integer $type
 * @property integer $status
 * @property boolean $result
 * @property boolean $abonent_data
 */

class Queue extends ActiveRecord
{

    /**
     * statuses
     */
    const PENDING = 0;
    const PROCESSING = 1;
    const FINISHED = 2;
    const UnknownError = 3;
    const UserPermissionError = 4;
    const UnsupportedOperationError = 5;
    const InvalidAbonentStateError = 6;
    const InvalidInputParametrs = 7;

    public static $statuses = [
        '' => '',  //for GridView
        self::PENDING => 'Ожидание',
        self::PROCESSING => 'В обработке',
        self::FINISHED => 'Обработано',
        self::UnknownError=>'Непредвиденная ошибка отправки результатов',
        self::UserPermissionError=>'Пользователь не обладает необходимыми правами для изменения статуса проверки абонента',
        self::UnsupportedOperationError=>'Сервис ЭДО ФЛ не настроен на использование сервисов автоматической проверки',
        self::InvalidAbonentStateError=>'Абонент не ожидает установки сервисом проверки своего статуса или не найден',
        self::InvalidInputParametrs=>'Входные параметры ошибочны',
    ];

      public static $errorsForEdoFl = [
        self::UnknownError => 'UnknownError',
        self::UserPermissionError  => 'UserPermissionError',
        self::UnsupportedOperationError  => 'UnsupportedOperationError',
        self::InvalidAbonentStateError  => 'InvalidAbonentStateError',
        self::InvalidInputParametrs => 'InvalidInputParametrs',
    ];

    /**
     * type
     */
    const COPY_CERT = 0;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'queue';
    }

    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'creation_time',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'update_time',
                ],
                'value' => function() {
                    return date('Y-m-d H:i:s.u' );
                },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['status','abonentIdentifier'], 'required'],
            [['status'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'creation_time' => 'Date Time Creation',
            'update_time' => 'Date Time Updated',
            'abonentIdentifier' => 'Abonent Identifier',
            'status' => 'Status',
        ];
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiles()
    {
        return $this->hasMany(File::className(), ['queue_id' => 'id']);
    }

    /**
     * @inheritdoc
     * @return QueueQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new QueueQuery(get_called_class());
    }

    public function SaveScans($data)
    {
        foreach ($data['documents_with_sign'] as $string){
            $this->SaveDoc($string);
        }
        if($data['passport']){
            $this->SaveDoc($data['passport'], File::SCAN_PASSPORT);
        }

    }

    public function SaveDoc($string, $type = File::SCAN_WITH_SIGN)
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
                throw new \Exception(json_encode($file->errors));
            }

    }
}
