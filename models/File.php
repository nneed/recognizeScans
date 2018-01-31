<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "files".
 *
 * @property integer $id
 * @property string $data
 * @property integer $queue_id
 * @property boolean $signed
 * @property integer $type
 */
class File extends \yii\db\ActiveRecord
{

    const SCAN_WITH_SIGN = 0;
    const SCAN_PASSPORT = 1;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'files';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['data', 'queue_id','type'], 'required'],
            [['data'], 'string'],
            [['queue_id'], 'integer'],
            [['signed'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'data' => 'Data',
            'queue_id' => 'Queue ID',
            'signed' => 'Result Signed',
            'type' => 'Type file',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getQueue()
    {
        return $this->hasOne(Queue::className(), ['id' => 'queue_id']);
    }

    /**
     * @inheritdoc
     * @return FileQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new FileQuery(get_called_class());
    }
}
