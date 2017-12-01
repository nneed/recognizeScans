<?php
namespace app\controllers;
use app\models\Queue;
use app\models\File;
use app\models\scan_service\SquaredScan;
use app\models\User;
use yii\base\Exception;
use yii\rest\ActiveController;
use app\queue\ScanDocJob;
use Yii;
use yii\httpclient\Client;
use yii\web\UnauthorizedHttpException;

class FileController extends ActiveController
{
    public $modelClass = 'app\models\Queue';

    public function beforeAction($action)
    {
        $authData = Yii::$app->request->getHeaders()['Authorization'];
        if (!$authData) throw new UnauthorizedHttpException();
        list($username,$password) = explode(':',base64_decode(str_replace('Basic' ,'', $authData)));
        $user = User::findOne(['username' => $username]);
        if (!$user->validatePassword($password))
            throw new UnauthorizedHttpException();
        return parent::beforeAction($action);
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['create']);
        return $actions;
    }

    public function actionCreate()
    {
        $data = Yii::$app->request->post('data');
        $abonentIdentifier = Yii::$app->request->post('abonentIdentifier');
        $queue = new Queue();
        $queue->abonentIdentifier = $abonentIdentifier;
        $queue->type = Queue::COPY_CERT;
        $queue->user_id = 1;
        $queue->status = Queue::PENDING;
        if ($queue->save()){

            foreach ($data as $string) {

                $path = Yii::getAlias('@webroot/upload');
                if (!file_exists($path)) mkdir($path, 0755);

                $filename = $queue->id .'_'. uniqid() . '.jpg';

                 $fp = fopen( $path.'/'.$filename, "wb" );
                 if (!fwrite( $fp, base64_decode( trim($string ) ))){
                    throw new Exception('Невозможно создать файл на сервере');
                 }

                fclose($fp);

                $file = new File();
                $file->data = $path.'/'.$filename;
                $file->queue_id = $queue->id;

                if (!$file->save()) throw new Exception(json_encode($file->errors));

            }

            Yii::$app->queue->push(new ScanDocJob([
                'idQueue' => $queue->id,
                'abonentIdentifier' => $abonentIdentifier
            ]));

        }else{
            throw new Exception(json_encode($queue->errors));
        }

        return $queue;

    }


    public function actionTest()
    {
        $abonentIdentifier = yii::$app->request->get('abonentIdentifier');
        $idQueue = yii::$app->request->get('id');

        $queue = Queue::findOne($idQueue);
        if ($queue->status != Queue::PENDING){
           // return;
        }
        $queue->status = Queue::PROCESSING;
        if (!$queue->save())  throw new Exception(json_encode($queue->errors));

        //Жадно загрузим все
      //  foreach (Queue::find()->where(['id'=>$idQueue])->with('files')->each() as $queue){

        $files = $queue->files;
        $result = false;
            foreach ($files as $file) {
                if ($file->signed === null){
                    try {
                        $squaredScan = new SquaredScan($file->data);
                        $file->signed = $result = $squaredScan->test();
                    } catch (Exception $e) {
                        $file->signed = false;
                    }
                    $file->save();
                }
            }

       $client = new Client(['baseUrl' => 'http://dev-1601/IDE.Web/']); //todo: Вынести в отдельный класс. Настройки в конфиг

        $response = $client->createRequest()
            ->setMethod('post')
            ->setHeaders(
                [
                    'Authorization' => 'Basic checker:1',
                ]
            )
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl('/api/Abonent/SetAbonentVerificationStatus')
            ->setData([
                'AbonentIdentifier' => $abonentIdentifier,
                'IsDocumentsAccepted' => $result,
                'RejectReason' => "",
            ])
            ->send();

         if($response->data['IsSuccess'] == true){

      //  if(true){
            $queue->status = Queue::FINISHED;
            $queue->result = $result;

        }else{
            $queue->status = array_search($response->data['ErrorType'], \app\models\Queue::$statuses);
        }
        $queue->save();

        //сделать грид для отображения всех очередей там должно быть поле с результатом обработки в виде ссылки на страницу

    }

}
