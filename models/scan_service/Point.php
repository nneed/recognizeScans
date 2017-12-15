<?php
/**
 * Created by PhpStorm.
 * User: ��������
 * Date: 30.10.2015
 * Time: 21:08
 */
namespace app\models\scan_service;

class Point {
    public $x;
    public $y;
    public $color;

    public function __construct($x, $y, $color=null) {
        $this->x = $x;
        $this->y = $y;
        $this->color = $color;
    }

    public function isWhite($im) {
        $rgb = imagecolorat($im, $this->x, $this->y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        //return $r == 255 && $g == 255 && $b == 255;
        return $r >= 250 && $g >= 250 && $b >= 250;
    }

    public function isBlack($im) {
        $rgb = imagecolorat($im, $this->x, $this->y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        //return $r == 0 && $g == 0 && $b == 0;
        return $r <= 5 && $g <= 5 && $b <= 5;
    }

    /**
     * @param Image $im
     * @param integer $size
     * @return bool
     */
    public function insideBlackSquare($im, $size) {
        $size = $this->getNeighbours($size);
        if(!$size) return false;
        foreach ($size->getPoints() as $p) {
            if(!$p->isBlack($im))
                return false;
        }
        return true;
    }

    /**
     * @param $size
     * @return PointMap
     */
    private function getNeighbours($size) {
        $map = new PointMap();
        $gap = $size/2 -1;
        $x = $this->x - $gap;
        if($x <= 0) return false;
        for($x; $x < $this->x + $gap; $x++) {
            for($y = $this->y - $gap; $y < $this->y + $gap; $y++) {
                $map->addPoint(new Point($x, $y));
            }
        }
        return $map;
    }
}