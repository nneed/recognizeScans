<?php

use yii\db\Migration;

/**
 * Class m171130_081203_create_table_users
 */
class m171130_081203_create_table_users extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = null;

        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('users', [
            'id' => $this->primaryKey(),
            'username' => $this->string()->notNull()->unique(),
            'password_hash' => $this->string()->notNull(),
            'email' => $this->string()->unique(),
            'status' => $this->smallInteger()->notNull()->defaultValue(10),
        ], $tableOptions);

        $this->insert('users',[
            'username' => 'EDO_FL',
            'password_hash' => '$2y$13$OOx/Ex5GbkcpZbYk94d9zObRH18OnxyL0R0NqCourv94Fs8ddoJ.e',
        ]);
    }

    public function down()
    {
        $this->dropTable('users');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m171130_081203_create_table_users cannot be reverted.\n";

        return false;
    }
    */
}
