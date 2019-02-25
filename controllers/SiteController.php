<?php

namespace app\controllers;

use app\models\File;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\Queue;
use app\models\QueueSearch;
use app\models\scan_service\SquaredScan;
use app\models\scan_service\COCREngine;
use app\queue\ScanDocJob;

class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {

        $searchModel = new QueueSearch();
        $dataProvider = $searchModel->searchWithFiles(Yii::$app->request->queryParams);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }

    /**
     * Download file.
     *
     */
    public function actionDownloadResult()
    {
        $id = Yii::$app->request->get('id');
        $zip = new \ZipArchive();
        $filename = Yii::getAlias('@runtime') . "/files.zip";
        if ($zip->open($filename, \ZIPARCHIVE::CREATE)!==TRUE) {
            exit("Невозможно открыть <$filename>\n");
        }

        $files = \yii\helpers\ArrayHelper::map(Queue::findOne($id)->files, 'id', 'data');
        foreach ($files as $id => $path){
            if (!is_file($path)) throw new \yii\web\NotFoundHttpException('The file does not exists.');
            $zip->addFile($path, basename($path));
        }
        $zip->close();
        Yii::$app->response->sendFile($filename);
        if (file_exists($filename)) {
            unlink($filename);
        }

    }


    public function actionRetry()
    {
        $id = Yii::$app->request->get('id');
        $queue = Queue::findOne($id);

        $id_event = Yii::$app->queue->push(new ScanDocJob([
            'idQueue' => $queue->id,
            'abonentIdentifier' => $queue->abonentIdentifier
        ]));
        if ($id_event === null) throw new \yii\web\BadRequestHttpException('Error retry', 400);
        echo "success";
    }

   public function actionDownloadScansAsJson()
    {
        $id = Yii::$app->request->get('id');
        $queue = Queue::find()->where(['id' => $id])->with('files')->one();

        $edofl = [
            'edofl' => [
                'abonentIdentifier' => $queue->abonentIdentifier,
                'passport' => json_decode($queue->abonent_data, true)
            ],
        ];
        $documents_with_sign = [];
        foreach ($queue->files as $file){
            if (!is_file($file->data)) throw new \yii\web\NotFoundHttpException('The file does not exists.');
            switch ($file->type){
                case File::SCAN_WITH_SIGN:
                    $documents_with_sign[] = base64_encode(file_get_contents($file->data));
                    break;
                case File::SCAN_PASSPORT:
                    $edofl['edofl']['passport']['image'] = base64_encode(file_get_contents($file->data));
            }
        }
        $edofl['edofl']['documents_with_sign'] = $documents_with_sign;

        $edofl = json_encode($edofl);

        $filename = Yii::getAlias('@runtime') . $id . '_' . uniqid() . 'files' . '.json';

        $fp = fopen($filename, "wb");
        if (!fwrite($fp, trim($edofl))) {
            throw new \yii\web\BadRequestHttpException('Невозможно создать файл на сервере.', 400);
        }
        fclose($fp);

        Yii::$app->response->sendFile($filename);
        if (file_exists($filename)) {
            unlink($filename);
        }
        echo 'done!';

    }

    public function actionShowResult()
    {

        $id = Yii::$app->request->get('id');
        $file = File::find()->where(['queue_id' => $id])->one();
        $squaredScan = new SquaredScan($file->data);
        $headers = Yii::$app->response->headers;
        $headers->set('Content-Type', 'image/jpeg');
        Yii::$app->response->format = Response::FORMAT_RAW;
        $squaredScan->test();
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionPassportScan()
    {
        $token = uniqid();
        $path = Yii::getAlias('@runtime/scans/passport.jpg');
        $scan = file_get_contents($path);
        $needles = ['григорьев','иван','никитич'];
        $threshold_shift = 50;

        $ocr = new COCREngine(COCREngine::TYPE_PASSPORT,$token,$scan, $needles, $threshold_shift, COCREngine::DEBUG);

        $res = $ocr->recognize();
        echo "<pre>";
        var_dump($res);
        echo "</pre>";

    }
}
