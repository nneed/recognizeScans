<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 04.12.2017
 * Time: 11:21
 */

namespace app\queue;

use yii;
use yii\httpclient\Client;


class EDO_FL_Client
{

    private $client;
    private $config;

    public function __construct()
    {
        $this->config =  Yii::$app->params['EDO_FL_Client'];
        $this->client = new Client(['baseUrl' => $this->config['baseUrl']]);
    }

    public function send($abonentIdentifier, $result)
    {
        $res = $this->client->createRequest()
            ->setMethod('post')
            ->setHeaders(
                [
                    'Authorization' => $this->config['Authorization'],
                ]
            )
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl($this->config['url'])
            ->setData([
                'AbonentIdentifier' => $abonentIdentifier,
                'IsDocumentsAccepted' => $result,
                'RejectReason' => "",
            ])
            ->send();
        if (!$res->isOk) throw new \yii\web\HttpException(404, var_export($res->content));
        return $res;
    }
}