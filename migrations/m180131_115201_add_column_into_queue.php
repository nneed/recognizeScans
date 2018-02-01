<?php
use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m180131_115201_add_column_into_queue
 */
class m180131_115201_add_column_into_queue extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('queue','abonent_data', Schema::TYPE_TEXT);
    }

    public function safeDown()
    {
        $this->dropColumn('queue','abonent_data');
    }
    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m180131_115201_add_column_into_files cannot be reverted.\n";

        return false;
    }
    */
}
