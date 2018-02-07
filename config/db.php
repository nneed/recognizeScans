<?php
//Закоментировать на боевом
/*return [
    'class' => 'yii\db\Connection',
    'dsn' => 'sqlite:/usr/local/www/apache24/data/test-site/yii2/queue.db',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];*/
//Раскоментировать на боевом
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=localhost;port=5432;dbname=queue',
    'charset' => 'utf8',
    'username' => 'postgres',
    'password' => '',

    // Schema cache options (for production environment)
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 60,
    'schemaCache' => 'cache',
];