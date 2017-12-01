<?php
use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m171113_073928_create_table_queue
 */
class m171113_073928_create_table_queue extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('queue', [
            'id' => Schema::TYPE_PK,
            'creation_time' => Schema::TYPE_TIMESTAMP . ' NOT NULL',
            'update_time' => Schema::TYPE_TIMESTAMP,
            'abonentIdentifier' => Schema::TYPE_STRING . ' NOT NULL',
            'user_id' => Schema::TYPE_INTEGER . ' NOT NULL',
            'type' => Schema::TYPE_INTEGER . ' NOT NULL',
            'status' => Schema::TYPE_INTEGER . ' NOT NULL',
            'result' => Schema::TYPE_BOOLEAN,
        ]);
    }

    /**
     * @inheritdoc
     */
/*    public function safeDown()
    {
        echo "m171113_073928_create_table_queue cannot be reverted.\n";

        return false;
    }*/

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m171113_073928_create_table_queue cannot be reverted.\n";

        return false;
    }
    */
}
