<?php
/**
 * Created by PhpStorm.
 * User: НЮКазанков
 * Date: 30.11.2017
 * Time: 16:34
 */

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Queue;

class QueueSearch extends Queue
{

    public $filesNotRecognizedAsString;
    public function rules()
    {
        // только поля определенные в rules() будут доступны для поиска
        return [
/*            [['status','abonentIdentifier'], 'required'],*/
            [['status','result','abonentIdentifier', 'filesNotRecognizedAsString','id'], 'safe'],
        ];
    }

    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = Queue::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        // загружаем данные формы поиска и производим валидацию
        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        // изменяем запрос добавляя в его фильтрацию
        $query->andFilterWhere(['like', 'abonentIdentifier', $this->abonentIdentifier])
        ->andFilterWhere(['status' => $this->status])
        ->andFilterWhere(['result' => $this->result]);

/*        $query->andFilterWhere(['id' => $this->id]);
            ->andFilterWhere(['like', 'creation_date', $this->creation_date]);*/

        return $dataProvider;
    }

    public function searchWithFiles($params)
    {
        $query = Queue::find();
//        $query->select('queue.id,files.queue_id, array_agg(files.signed) as filesNotRecognizedAsString');
        $query->join('join','files', 'queue.id = files.queue_id');
        $query->groupBy('queue.id, files.queue_id');
        $query->with('filesNotRecognized');
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
//        $dataProvider->setSort([
////            'attributes' => [
//////                'filesNotRecognizedAsString'=>[
//////                    'asc' => ['files.filesNotRecognizedAsString' => SORT_ASC],
//////                    'desc' => ['filesNotRecognizedAsString' => SORT_DESC],
//////                    'label' => 'Не расспознано',
//////                ],
////                'filesNotRecognizedAsString',
//                'id',
//            ]
//        ]);


        // загружаем данные формы поиска и производим валидацию
        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        // изменяем запрос добавляя в его фильтрацию
        $query->andFilterWhere(['like', 'abonentIdentifier', $this->abonentIdentifier])
        ->andFilterWhere(['status' => $this->status])
        ->andFilterWhere(['result' => $this->result]);

        $query->andFilterWhere(['queue.id' => $this->id]);
       /*     ->andFilterWhere(['like', 'creation_date', $this->creation_date]);*/

        return $dataProvider;
    }
}