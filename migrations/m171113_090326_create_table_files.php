<?php
use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m171113_090326_create_table_files
 */
class m171113_090326_create_table_files extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('files', [
            'id' => Schema::TYPE_PK,
            'data' => Schema::TYPE_TEXT. ' NOT NULL',
            'queue_id' => Schema::TYPE_INTEGER,
            'result' => Schema::TYPE_BOOLEAN,
        ]);

        $this->createIndex(
            'idx-files-queue_id',
            'files',
            'queue_id'
        );

        // add foreign key for table `user`
        $this->addForeignKey(
            'fk-files-queue_id',
            'files',
            'queue_id',
            'queue',
            'id',
            'CASCADE'
        );
    }

    /**
     * @inheritdoc
     */
/*    public function safeDown()
    {
        echo "m171113_090326_create_table_files cannot be reverted.\n";

        return false;
    }*/

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m171113_090326_create_table_files cannot be reverted.\n";

        return false;
    }
    */
}
