<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 14.08.2018
 * Time: 17:06
 */

namespace app\bootstrap;


use yii\base\BootstrapInterface;

class SetUp implements BootstrapInterface
{
    public function bootstrap($app) : void
    {
        $container = \Yii::$container;

    }
}