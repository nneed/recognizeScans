<?php
use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m180131_105741_add_column_into_files
 */
class m180131_105741_add_column_into_files extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('files','type', Schema::TYPE_INTEGER);
    }

    /**
     * @inheritdoc
     */


    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m180131_105741_add_column_into_files cannot be reverted.\n";

        return false;
    }
    */
}
