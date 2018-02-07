<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 05.02.2018
 * Time: 15:56
 */

namespace app\models\scan_service;


class ImageMagickCommands
{
    public $path;
    public $output;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function prepareScanPassport()
    {
        $this->whiteboard();
        $this->cleanText();
         //$this->unrotate();
    }

    public function exec($command)
    {
        $input = $this->output ? $this->output : $this->path;
        $output = $this->output ? $this->output: \Yii::getAlias('@runtime/scans/') . "TEMP".uniqid().rand().'.jpg';

        $command = __DIR__.'/commands/'.$command;
        $command = "bash {$command} \"{$input}\" \"{$output}\"";

        exec($command,$result,$return_var);
        if($return_var != 0)  throw new \yii\web\ServerErrorHttpException('Ошибка выполнения комады: ' . $command, 500);
        $this->output = $output;
    }

    public function cleanText()
    {
        $this->exec('textcleaner');
    }

    public function unrotate()
    {
        $this->exec('unrotate');
    }
    public function whiteboard()
    {
        $this->exec('whiteboard -e both -a 1.5 -f 12 -o 7 -t 30');
    }

    public function removeTempFile()
    {
        if ($this->output) {
            unlink($this->output);
        }
    }


}