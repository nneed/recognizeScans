<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 11.02.2019
 * Time: 15:52
 */

namespace app\queue\handlers;


interface ScanHandlerInterface
{
    public function handle($file);
}