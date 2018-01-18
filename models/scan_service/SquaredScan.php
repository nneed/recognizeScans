<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 06.11.2017
 * Time: 18:02
 */
namespace app\models\scan_service;

use yii\helpers\ArrayHelper;
use yii;
class SquaredScan{

    const WIDTH = 800;
    const SQUARE_SIZE = 10;
    const PADDING = 5;
    private $im;
    private $croped;

    private $colored;
    private $new_colored;
    private $corners;
    private $validate;
    private $black = 0;


    /**
     * SquaredScan constructor.
     * @param $path
     */
    public function __construct($path) {
        $this->loadImage($path);
    }

    /**
     * @param $path
     * @throws \Exception $e
     */
    private function loadImage($path) {

        try{
       // $im = imagecreatefromjpeg($path);
        $imagick = new \Imagick();
        $imagick->readImage($path);
        $imagick->blurImage(5,2);
        $imagick->blackThresholdImage('#7F7F80');
   //     $imagick->whiteThresholdImage('#7F7F80');

        $im = imagecreatefromstring($imagick->getImageBlob());
        unset($imagick);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

        $width = imagesx($im);
        $height = imagesy($im);
        $this->im = imagecreatetruecolor(self::WIDTH, ($height * self::WIDTH)/$width);
        imagecopyresampled (
            $this->im,
            $im, 0, 0, 0, 0,
            self::WIDTH,
            ($height * self::WIDTH)/$width,
            $width,
            $height);

        imagefilter($this->im, IMG_FILTER_GRAYSCALE);
      //  imagefilter($this->im, IMG_FILTER_CONTRAST, -100);
        imagejpeg($this->im,  \Yii::getAlias('@runtime/scans').'/merge.jpg', 100);
       // $imagick = new \Imagick( Yii::getAlias('@webroot/upload').'/merge.jpg');
    }

    public function output() {
        header('Content-Type: image/jpeg');
        imagejpeg($this->im, null, 100);
        imagedestroy($this->im);
    }

    public function test() {
        ini_set('memory_limit', '-1');
        $width = imagesx($this->im);
        $height = imagesy($this->im);
        $step = 0;
        $min_x = 40;
        $min_y = 40;
        $max_x = $width;
        $max_y = $height;

        $max_points = 0;

        $green = imagecolorallocate($this->im, 0, 255, 0);
        $red = imagecolorallocate($this->im, 255, 0, 0);
        $white = imagecolorallocate($this->im, 255, 255, 255);

        while ($step < 100 && $max_points < 3) {
            for ($i = $min_x; $i < $max_x; $i++) {
                $point = new Point($i, $min_y);
                if ($point->isBlack($this->im)) {
                    if ($point->insideBlackSquare($this->im, self::SQUARE_SIZE)) {
//                        imagefilledellipse($this->im, $i, $min_y, self::SQUARE_SIZE*2, self::SQUARE_SIZE*2, $green);
                        $this->SmartSelect($point);
                        $max_points++;
                    }
                }
            }
            $max_x--;
            for ($i = $min_y; $i < $max_y; $i++) {
                $point = new Point($max_x, $i);
                if ($point->isBlack($this->im)) {
                    if ($point->insideBlackSquare($this->im, self::SQUARE_SIZE)) {
//                        imagefilledellipse($this->im, $max_x, $i, self::SQUARE_SIZE*2, self::SQUARE_SIZE*2, $green);
                        $this->SmartSelect($point);
                        $max_points++;
                    }
                }
            }
            $max_y--;
            for ($i = $max_x; $i > $min_x; $i--) {
                $point = new Point($i, $max_y);
                if ($point->isBlack($this->im)) {
                    if ($point->insideBlackSquare($this->im, self::SQUARE_SIZE)) {
//                        imagefilledellipse($this->im, $i, $max_y, self::SQUARE_SIZE*2, self::SQUARE_SIZE*2, $green);
                        $this->SmartSelect($point);
                        $max_points++;
                    }
                }
            }
            $min_x++;
            for ($i = $max_y; $i > $min_y; $i--) {
                $point = new Point($min_x, $i);
                if ($point->isBlack($this->im)) {
                    if ($point->insideBlackSquare($this->im, self::SQUARE_SIZE)) {
//                        imagefilledellipse($this->im, $min_x, $i, self::SQUARE_SIZE*2, self::SQUARE_SIZE*2, $green);
                        $this->SmartSelect($point);
                        $max_points++;
                    }
                }
            }
            $min_y++;
            $step++;
        }

        imageline($this->im, $this->corners[0]->x, $this->corners[0]->y, $this->corners[1]->x, $this->corners[1]->y, $green);
        imageline($this->im, $this->corners[1]->x, $this->corners[1]->y, $this->corners[2]->x, $this->corners[2]->y, $green);
        imageline($this->im, $this->corners[0]->x, $this->corners[0]->y, $this->corners[2]->x, $this->corners[2]->y, $green);



/*        $length[0] = sqrt(pow($this->corners[0]->x - $this->corners[1]->x,2) + pow($this->corners[0]->y - $this->corners[1]->y,2));
        $length[1] = sqrt(pow($this->corners[1]->x - $this->corners[2]->x,2) + pow($this->corners[1]->y - $this->corners[2]->y,2));
        $length[2] = sqrt(pow($this->corners[0]->x - $this->corners[2]->x,2) + pow($this->corners[0]->y - $this->corners[2]->y,2));*/



        $topSpace = $height/2;
        $leftSpace = $width/2;
        $sides = [];


        foreach ($this->corners as $val){
            if($val->y <= $topSpace){
                $sides['top'][] = $val;
            }if($val->x <= $leftSpace){
                $sides['left'][] = $val;
            }if($val->x > $leftSpace){
                $sides['right'][] = $val;
            }if($val->y > $topSpace){
                $sides['bottom'][] = $val;
            }
        }

        $angle = 0;

        if(count($sides['top']) == 2 && count($sides['left']) == 2){ // наклон меньше 90
            $angle = atan(($sides['top'][1]->y - $sides['top'][0]->y)/($sides['top'][1]->x - $sides['top'][0]->x))*180/pi();
        }
        if(count($sides['top']) == 2 && count($sides['right']) == 2){ // наклон больше 90
            $angle = atan(($sides['top'][1]->y - $sides['top'][0]->y)/($sides['top'][1]->x - $sides['top'][0]->x))*180/pi() + 90;
        }
        if(count($sides['bottom']) == 2 && count($sides['right']) == 2){ // наклон больше 180
            $angle = atan(($sides['bottom'][1]->y - $sides['bottom'][0]->y)/($sides['bottom'][1]->x - $sides['bottom'][0]->x))*180/pi() + 180;
        }
        if(count($sides['bottom']) == 2 && count($sides['left']) == 2){ // наклон больше 270
            $angle = atan(($sides['bottom'][1]->y - $sides['bottom'][0]->y)/($sides['bottom'][1]->x - $sides['bottom'][0]->x))*180/pi() + 270;
        }


 /*       foreach ($sides as $key => $side){
            if (count($side) == 2){
                if ($key == 'top' || $key == 'bottom'){
                    //$angle = atan(($side[1]->x - $side[0]->x)/($side[1]->y - $side[0]->y))*180/pi();
                    $angle = atan(($side[1]->y - $side[0]->y)/($side[1]->x - $side[0]->x))*180/pi();
                    break;
                }else{
                    if ($side[1]->y - $side[0]->y > 0){
                        $angle = atan(($side[1]->y - $side[0]->y)/($side[1]->x - $side[0]->x))*180/pi();
                    }
                    break;
                }

            }
        }

        if (count($sides['bottom']) == 2 && count($sides['right']) == 2){
            $angle = atan(($side[1]->y - $side[0]->y)/($side[1]->x - $side[0]->x))*180/pi()-180;
        }
 */

        $this->im = imagerotate($this->im, $angle, $white);
        imagejpeg($this->im,  \Yii::getAlias('@runtime/scans').'/color.jpg', 100);

        $width = imagesx($this->im);
        $height = imagesy($this->im);
        $p = new Point($width/2*1.5, $height*0.86);
        $this->SmartSelect($p, true);
        imagejpeg($this->im,  \Yii::getAlias('@runtime/scans').'/color.jpg', 100);
        return $this->output();
        return $this->validate;

    }

    public function SmartSelect($center, $color = null) {
        ini_set('memory_limit', '-1');
        $this->black = 0;
        $this->Step([$center]);

        while (count($this->colored) > 0){
            $contur = $this->colored;
            foreach ($this->colored as $point) {
                $this->Step($this->getN($point, $color));
            }
            for($a = 0; $a < count($contur); $a++) {
                array_shift($this->colored) ;
            }
        }

        $lt = $lb = $rt = $rb = $center;

        foreach ($this->new_colored as $point) {
            if($point->x <= $lt->x){
                if($point->x < $lt->x)$lt = $point;
                else {
                    if($point->y < $lt->y) $lt = $point;
                }
            }
            if($point->x >= $lb->x){
                if($point->x > $lb->x)$lb = $point;
                else {
                    if($point->y > $lb->y) $lb = $point;
                }
            }
            if($point->y >= $rt->y){
                if($point->y > $rt->y)$rt = $point;
                else {
                    if($point->x > $rt->x) $rt = $point;
                }
            }
            if($point->y <= $rb->y){
                if($point->y < $rb->y)$rb = $point;
                else {
                    if($point->x < $rb->x) $rb = $point;
                }
            }
        }

        $blue = imagecolorallocate($this->im, 0, 0, 255);
        $green = imagecolorallocate($this->im, 0, 255, 0);
        $black = imagecolorallocate($this->im, 0, 0, 0);
        $yellow = imagecolorallocate($this->im, 255, 255, 0);

        imagefilledellipse($this->im,$lt->x,$lt->y,10,10,$blue);
        imagefilledellipse($this->im,$lb->x,$lb->y,10,10,$green);
        imagefilledellipse($this->im,$rt->x,$rt->y,10,10,$black);
        imagefilledellipse($this->im,$rb->x,$rb->y,10,10,$yellow);

        $width = max($rt->x,$rb->x,$lt->x,$lb->x) - min($rt->x,$rb->x,$lt->x,$lb->x) - self::PADDING;
        $height = max($rt->y,$rb->y,$lt->y,$lb->y) - min($rt->y,$rb->y,$lt->y,$lb->y) - self::PADDING;

        if ($width > 17 && $height >17){

            $this->croped = imagecrop($this->im,[
                'x' => min($rt->x,$rb->x,$lt->x,$lb->x) + self::PADDING,
                'y' => min($rt->y,$rb->y,$lt->y,$lb->y)/* + self::PADDING*/,
                'width' => $width,
                'height' => $height/* - self::PADDING - 10*/
                ]);
            $width -= min($rt->y,$rb->y,$lt->y,$lb->y);
            $height -= min($rt->x,$rb->x,$lt->x,$lb->x) + self::PADDING;

            $this->validate = $this->ValidateResult();
        }

        $this->corners[] = $lt;


        $this->colored = [];
        $this->new_colored = [];
//        imagejpeg($this->im, '/tmp/colored'.uniqid().'.jpg');
//        imagejpeg($this->cropped, '/tmp/cropped'.uniqid().'.jpg');

//        if($this->ValidateResult()){
//            $res = imagecrop($this->cropped,[
//                'x' => min($rt->x,$rb->x,$lt->x,$lb->x) + self::PADDING,
//                'y' => min($rt->y,$rb->y,$lt->y,$lb->y) + self::PADDING,
//                'width' => max($rt->x,$rb->x,$lt->x,$lb->x) - min($rt->x,$rb->x,$lt->x,$lb->x) - self::PADDING,
//                'height' => max($rt->y,$rb->y,$lt->y,$lb->y) - min($rt->y,$rb->y,$lt->y,$lb->y) - self::PADDING - 10
//            ]);
//
//            imagefilter($res, IMG_FILTER_GRAYSCALE);
//            imagefilter($res, IMG_FILTER_CONTRAST, -100);
//            imagefilter($res, IMG_FILTER_SMOOTH, 50);
//
//            $this->resultPath = Yii::app()->basePath.'/../signs/result'.uniqid().'.jpg';
//            imagejpeg($res, $this->resultPath);
//
//            return $this->resultPath;
//        } else{
//            var_dump('NOT VALIDATED');
//            return false;
//        }
    }
    public function Step($points) {
        $red = imagecolorallocate($this->im, 255, 0, 0);
        foreach ($points as $point) {
            imagesetpixel($this->im,$point->x,$point->y,$red);
            $this->colored[] = $point;
            $this->new_colored[] = $point;
        }
    }

    /**
     * @param Point $point
     * @return array
     */
    public function getN($point, $color) {
        $res = [];
        if($color) {
            $p = new Point($point->x+1,$point->y);
            if($p->isWhite($this->im)) $res[] = $p;
            $p = new Point($point->x-1,$point->y);
            if($p->isWhite($this->im)) $res[] = $p;
            $p = new Point($point->x,$point->y+1);
            if($p->isWhite($this->im)) $res[] = $p;
            $p = new Point($point->x,$point->y-1);
            if($p->isWhite($this->im)) $res[] = $p;
        } else {
            $p = new Point($point->x + 1, $point->y);
            if ($p->isBlack($this->im)) $res[] = $p;
            $p = new Point($point->x - 1, $point->y);
            if ($p->isBlack($this->im)) $res[] = $p;
            $p = new Point($point->x, $point->y + 1);
            if ($p->isBlack($this->im)) $res[] = $p;
            $p = new Point($point->x, $point->y - 1);
            if ($p->isBlack($this->im)) $res[] = $p;
        }
        return $res;
    }

    /**
     * @return bool
     */
    private function ValidateResult()
    {
        $h = imagesy($this->croped);
        $w = imagesx($this->croped);
        $black = 0;
        $px_count = $h * $w;

        for ($i = 0; $i < $w; $i++)
        {
            for ($j = 0; $j < $h; $j++)
            {
                $point = new Point($i, $j);
                if( imagecolorat($this->croped, $i, $j) != 16711680){
                    $black++;
                }
            }
        }
        return ($black * 100 / $px_count) > 2;

    }
/*    public function ValidateResult() {
        $width = imagesx($this->im);
        $height = imagesy($this->im);
        $result = true;
        $is_full = 0;
        for($i = 0; $i < $width; $i++) {
            if($this->isRed($p = new Point( $i, 0)) || $this->isRed($p = new Point( $i, $height))){
                $is_full = 1;
                $result = false;
            }
        }
        for($i = 0; $i < $height; $i++) {
            if($this->isRed($p = new Point(0, $i)) || $this->isRed($p = new Point($width, $i))){
                $is_full = 1;
                $result = false;
            }
        }
        if(count($this->new_colored) < ($width*$height) * 0.25){
            $this->log('INSIDE SMALL AREA');
            $result = false;
        }
        if($is_full) $this->log('FULL COLORED');
        return $result;
    }*/


}