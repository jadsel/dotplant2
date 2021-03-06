<?php

namespace app\seo\behaviors;

use app\seo\models\Meta;
use yii\base\Behavior;
use yii\web\Controller;

class MetaBehavior extends Behavior
{
    private $cacheExpire;
    private $cacheName;
    public $index = 'index';

    public function init()
    {
        parent::init();
        $this->cacheName = \Yii::$app->getModule('seo')->cacheConfig['metaCache']['name'];
        $this->cacheExpire = \Yii::$app->getModule('seo')->cacheConfig['metaCache']['expire'];
    }

    public function events()
    {
        return [
            Controller::EVENT_BEFORE_ACTION => 'beforeAction',
        ];
    }

    public function beforeAction()
    {
        /* @var $controller Controller */
        $controller = $this->owner;
        if ($controller->action->id == $this->index) {
            /* @var $metas Meta[] */
            if (\Yii::$app->getCache()->exists($this->cacheName)) {
                $metas = \Yii::$app->getCache()->get($this->cacheName);
            } else {
                $metas = Meta::find()->all();
                \Yii::$app->getCache()->set($this->cacheName, $metas, $this->cacheExpire);
            }
            foreach ($metas as $meta) {
                $controller->getView()->registerMetaTag(
                    [
                        'name' => $meta->name,
                        'content' => $meta->content,
                    ],
                    $meta->key
                );
            }
        }
    }
}
