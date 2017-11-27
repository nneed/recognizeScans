<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use \yii\db\ActiveRecord;
use Yii;

/**
 * This is the model class for table "queue".
 *
 * @property integer $id
 * @property string $date_time
 * @property integer $status
 */
class Queue extends ActiveRecord
{
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
                'value' => function() { return date('U');},
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['status'], 'required'],
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
            'date_time' => 'Date Time',
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
}
