<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 18.03.15
 * Time: 0:00
 */

namespace app\models\scan_service;
use \Imagick;
use TesseractOCR;

class COCREngine {

    const DEBUG = 1;
    const RELEASE = 2;

    const RESIZE_WIDTH = 2000;
    const TMP_DIR = '/usr/local/www/apache24/data/test-site/yii2/runtime/scans/temp/';

    const TYPE_PASSPORT = 1;
    const TYPE_SNILS = 2;
    const TYPE_INN = 3;
    const TYPE_OGRN = 4;
    const TYPE_CERT = 5;

    const MAX_LEVENSTEIN_DISTANCE = 2;

    private $type;
    private $token;
    private $source;
    private $needles;

    /**
     * @var TesseractOCR object
     */
    private $api;
    /**
     * @var Imagick object
     */
    private $img;
    /**
     * @var Fuzzy object
     */
    private $searchEngine;

    private $execution_type;
    private static $threshold_shift;
    private $tmp_files;
    private $start_time;

    public function __construct($type, $token, $scan_data, $needles, $threshold_shift = 0, $execution_type = self::RELEASE) {
        self::$threshold_shift = $threshold_shift;
        $this->execution_type = $execution_type;
        $this->type = $type;
        $this->token = $token;
        $this->needles = $needles;
        $this->source = $scan_data;

        $this->searchEngine = new Fuzzy();
        $this->img = new Imagick();
    }

    private static function NUMBERS(){
        return range(0,9);
    }

    private static function RUSSIAN_LETTERS(){
        $abc = [];
        foreach (range(chr(0xC0),chr(0xDF+32)) as $v)
            $abc[$v] = iconv('CP1251','UTF-8',$v);
        return $abc;
    }

    private static function SPECIAL_SYMBOLS(){
        return ['-','⃞'];
    }

    private static function THRESHOLD_COLOR() {
        return 100 + self::$threshold_shift;
    }

    private static function SYMBOLS_WHITE_LIST(){
        return array_merge(self::RUSSIAN_LETTERS(), self::NUMBERS(), self::SPECIAL_SYMBOLS());
    }

    /**
     * @param int $psm (0,1,2,3,4,5,6,7,8,9,10)
     *          0 = Orientation and script detection (OSD) only.
     *          1 = Automatic page segmentation with OSD.
     *          2 = Automatic page segmentation, but no OSD, or OCR.
     *          3 = Fully automatic page segmentation, but no OSD. (Default)
     *          4 = Assume a single column of text of variable sizes.
     *          5 = Assume a single uniform block of vertically aligned text.
     *          6 = Assume a single uniform block of text.
     *          7 = Treat the image as a single text line.
     *          8 = Treat the image as a single word.
     *          9 = Treat the image as a single word in a circle.
     *          10 = Treat the image as a single character.
     *
     * @param $white_list
     */
    private function init($white_list = null, $psm = 3) {
        $this->api->setLanguage('rus');
        $this->api->setTempDir(self::TMP_DIR);
        $this->api->setPsm($psm);
        if($white_list)
            $this->api->setWhitelist($white_list);
    }

    private function prepareImages() {

        $w_threshold = self::THRESHOLD_COLOR();
        //$b_threshold = $w_threshold + 1;

        $this->img->readImageBlob($this->source);
        //$this->img->scaleImage(self::RESIZE_WIDTH,0);
        $this->img->setImageColorSpace(Imagick::COLORSPACE_GRAY);

        $this->img->negateImage(true);
        $this->img->whiteThresholdImage("rgb({$w_threshold},{$w_threshold},{$w_threshold})");
        //$this->img->blackThresholdImage("rgb({$b_threshold},{$b_threshold},{$b_threshold})");

        $dir = self::TMP_DIR;

        $file_name = "{$dir}/{$this->token}_{$this->type}_0.jpg";
        $this->img->writeImage($file_name);
        $this->tmp_files[] = $file_name;

        $this->img->rotateImage("#000", 90);
        $file_name = "{$dir}/{$this->token}_{$this->type}_90.jpg";
        $this->img->writeImage($file_name);
        $this->tmp_files[] = $file_name;

        $this->img->rotateImage("#000", 180);
        $file_name = "{$dir}/{$this->token}_{$this->type}_180.jpg";
        $this->img->writeImage($file_name);
        $this->tmp_files[] = $file_name;

        $this->img->rotateImage("#000", 270);
        $file_name = "{$dir}/{$this->token}_{$this->type}_270.jpg";
        $this->img->writeImage($file_name);
        $this->tmp_files[] = $file_name;

        //$t = time()- $this->start_time;
    }

    private function deleteTempFiles() {
        $dir = self::TMP_DIR;
        unlink("{$dir}/{$this->token}_{$this->type}_0.jpg");
        unlink("{$dir}/{$this->token}_{$this->type}_90.jpg");
        unlink("{$dir}/{$this->token}_{$this->type}_180.jpg");
        unlink("{$dir}/{$this->token}_{$this->type}_270.jpg");
    }

    private function recognizeImage($path) {
        $this->api = new TesseractOCR($path);
        if($this->type == self::TYPE_PASSPORT || $this->type == self::TYPE_SNILS)
            $this->init(self::SYMBOLS_WHITE_LIST());
        else if($this->type == self::TYPE_INN || $this->type == self::TYPE_OGRN)
            $this->init(array_merge(self::NUMBERS()),9);
        return $this->api->recognize();
    }

    private function compareWithSourceData($data) {
        $res = [];
        $t_arr = preg_split("/(\n| )/", mb_strtolower(trim($data)));
        foreach($this->needles as $needle) {
            if(!is_array($needle)) {
                if($res = $this->searchEngine->search($t_arr, mb_strtolower($needle), self::MAX_LEVENSTEIN_DISTANCE)) {
                    return $res;
                }
            } else {
                $condition1 = $this->searchEngine->search($t_arr, mb_strtolower($needle[0]), self::MAX_LEVENSTEIN_DISTANCE+2);
                $condition2 = $this->searchEngine->search($t_arr, mb_strtolower($needle[1]), self::MAX_LEVENSTEIN_DISTANCE);
                if($condition1 && $condition2) {
                    return [$condition1,$condition2];
                }
            }
        }
        return $res;
    }

    public function recognize() {
        $this->start_time = time();

        $this->prepareImages();

        $res = []; $recognized_data = [];
        foreach($this->tmp_files as $key => $tmp_file) {
            $recognized_data[$key] = $this->recognizeImage($tmp_file);
            if($res = $this->compareWithSourceData($recognized_data[$key])) {
                break;
            }
        }
        if($this->execution_type == self::RELEASE) $this->deleteTempFiles();
        $execution_time =  "Execution time: ".(time()-$this->start_time)." sec.";
        //echo "$execution_time\n";

        return $res ?
            ['check' => true,  'res' => $res, 'recognized_data' => $recognized_data, 'execution_time' => $execution_time]:
            ['check' => false, 'res' => $res, 'recognized_data' => $recognized_data, 'execution_time' => $execution_time];
    }
}