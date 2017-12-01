<?php
use yii\grid\GridView;
use yii\widgets\Pjax;
use yii\helpers\Html;
/* @var $this yii\web\View */

$this->title = 'My Yii Application';
?>
<div class="site-index">
    <h1>Очереди</h1>
<?php
/*Pjax::begin();*/
    echo GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            'id',
            [
                'attribute' => 'status',
                'label'=>'Status',
                'filter' => Html::activeDropDownList($searchModel, 'status', \app\models\Queue::$statuses,
                                    ['text' => 'Please select','class'=>'form-control']),
                'content'=>function($data){
                    return \app\models\Queue::$statuses[$data->status];
                }
            ],
            [
                'attribute' => 'result',
                'label'=>'result',
                'format'=>'boolean',
                'content'=>function($data){
                    if ($data->result === false) {
                        return Html::tag('span','Не удалось распознать подпись.', [ 'style'=> 'color:red;'])
                                . Html::a('Скачать оригинал', yii\helpers\Url::toRoute(
                                    ['site/download-result', 'id' => $data->id]), ['target'=>'_blank']);
                    }
                    if ($data->result === true)
                        return Html::tag('span','Документ подсписан', [ 'style'=> 'color:green;']);
                }
            ],
            'creation_time:dateTime',
            'update_time:dateTime',
            'abonentIdentifier',
            'user_id',
            //['class' => 'yii\grid\ActionColumn'],
        ],
    ]);
/*Pjax::end();*/
?>

</div>
