<?php

namespace app\controllers;

use app\models\File;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\Queue;
use app\models\QueueSearch;
use app\models\scan_service\SquaredScan;
use app\models\scan_service\COCREngine;

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
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

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

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
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

/*        где
$type = COCREngine::TYPE_PASSPORT;
$token = uniqid();
$needles = ['калабин','александр','геннадьевич'];
$scan = file_get_contents('passport.jpg');
$threshold_shift = 100; - нужно играться
$execution_type = COCREngine::RELEASE; - но он такой вроде по дефолту*/
    }
}
