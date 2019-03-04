<?php
use yii\grid\GridView;
use yii\widgets\Pjax;
use yii\helpers\Html;
/* @var $this yii\web\View */

$this->title = 'My Yii Application';
?>
<div class="site-index">
    <h1>Документы</h1>
<div class="pull-right">
<?php
foreach ($infoQueue as $val){
    echo $val;
}
?>
</div>

<?php
/*Pjax::begin();*/
    echo GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'files',
                'label'=>'Images',
                 'content'=>function($data){
                    $string = '';
                    foreach ($data->files as $file){
                        $string .= Html::img($file->getThumbFileUrl('data'));
                    }
                    return $string;
                }
            ],
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
                                . Html::a('Скачать оригиналы', yii\helpers\Url::toRoute(
                                    ['site/download-result', 'id' => $data->id]), ['target'=>'_blank']);
                    }else if ($data->result === true) {
                        return Html::tag('span', 'Документы подписаны.', ['style' => 'color:green;']) . Html::a('Скачать оригиналы', yii\helpers\Url::toRoute(['site/download-result', 'id' => $data->id]), ['target' => '_blank']);
                    }else{
                        return Html::a('Скачать оригиналы', yii\helpers\Url::toRoute(['site/download-result', 'id' => $data->id]), ['target' => '_blank']);
                    }
                }
            ],
            'creation_time',
            'update_time',
            [
                'attribute' => 'abonentIdentifier',
                'label'=>'Abonent Identifier',
                'filter' => Html::activeInput('text',$searchModel, 'abonentIdentifier',['text' => 'Please select','class'=>'form-control']),
            ],
            [
                'attribute' => 'filesNotRecognizedAsString',
                'format' => 'raw',
                'label'=>'Не расспознано',
                'filter'=> false
            ],
           // ['class' => 'yii\grid\ActionColumn'],
        ],
    ]);
/*Pjax::end();*/
?>

</div>
