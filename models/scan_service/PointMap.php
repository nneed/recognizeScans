<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 16.04.17
 * Time: 15:30
 */
namespace app\models\scan_service;

class PointMap {
    /** @var  Point[] */
    private $points;
    private $map;
    private $count;

    public function __construct() {
        $this->count = 0;
    }

    /**
     * @param Point $point
     */
    public function addPoint($point) {
        ini_set('memory_limit', '-1');
        $this->points[$this->count] = $point;
        $this->map[$point->x][$point->y] = $this->count;
        $this->count++;
    }

    public function getPoints() {
        return $this->points;
    }

    /**
     * @param integer $color
     * @return Point[]
     */
    public function getPointsByColor($color) {
        $res = [];
        foreach ($this->points as $point) {
            if($point->color == $color) $res[] = $point;
        }
        return $res;
    }

    /**
     * @param Point $point
     * @return Point[]
     */
    public function getNeighbors($point) {
        $res = [];
        if($p = $this->map[$point->x][$point->y+1]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x][$point->y-1]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x+1][$point->y+1]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x+1][$point->y+1]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x-1][$point->y+1]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x-1][$point->y+1]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x-1][$point->y]) $res[] = $this->points[$p];
        if($p = $this->map[$point->x+1][$point->y]) $res[] = $this->points[$p];
        return $res;
    }
}