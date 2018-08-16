<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'queue'],
    'controllerNamespace' => 'app\commands',
    'components' => [
/*        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],*/
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'queue' => [
/*            'class' => \yii\queue\gearman\Queue::class,
            'host' => 'localhost',
            'port' => 4730,
            'channel' => 'yii2_queue',*/
           'class' => \yii\queue\file\Queue::class,
            'path' => '@runtime/queue_files',
            'ttr' => 10 * 60, // Максимальное время выполнения задания 
            'attempts' => 5, // Максимальное кол-во попыток
        ],
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
