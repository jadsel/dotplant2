<?php

namespace app\modules\config\controllers;

use app;
use app\modules\config\components\ConfigurationSaveEvent;
use app\modules\config\models\Configurable;
use app\modules\config\helpers\ApplicationConfigWriter;
use devgroup\TagDependencyHelper\ActiveRecordHelper;
use Yii;
use yii\caching\TagDependency;
use yii\filters\AccessControl;

/**
 * Backend controller for modifying application and it's modules configuration.
 *
 * @package app\modules\config\controllers
 */
class BackendController extends app\backend\components\BackendController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['setting manage'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists configurables by tabs and saves configuration
     * @return string
     * @throws \Exception
     * @throws \yii\web\ServerErrorHttpException
     */
    public function actionIndex()
    {
        /** @var Configurable[] $models */
        $models = Configurable::getDb()->cache(
            function ($db) {
                return Configurable::find()
                    ->orderBy([
                        'sort_order' => SORT_ASC,
                    ])
                    ->all($db);
            },
            86400,
            new TagDependency([
                'tags' => ActiveRecordHelper::getCommonTag(Configurable::className()),
            ])
        );

        foreach ($models as $model) {
            $configurableModel = $model->getConfigurableModel();
            $configurableModel->loadState();
        }


        $commonConfigWriter = new ApplicationConfigWriter([
            'filename' => '@app/config/common-configurables.php',
        ]);
        $webConfigWriter = new ApplicationConfigWriter([
            'filename' => '@app/config/web-configurables.php',
        ]);
        $consoleConfigWriter = new ApplicationConfigWriter([
            'filename' => '@app/config/console-configurables.php',
        ]);
        $kvConfigWriter = new ApplicationConfigWriter([
            'filename' => '@app/config/kv-configurables.php',
        ]);


        if (Yii::$app->request->isPost === true) {
            $isValid = true;
            $errorModule = '';

            foreach ($models as $model) {
                $configurableModel = $model->getConfigurableModel();

                if ($configurableModel->load(Yii::$app->request->post()) === true) {
                    $event = new ConfigurationSaveEvent();
                    $event->configurable = &$model;
                    $event->configurableModel = &$configurableModel;

                    $configurableModel->trigger($configurableModel->configurationSaveEvent(), $event);
                    if ($event->isValid === true) {
                        if ($configurableModel->validate() === true) {
                            // apply application configuration
                            $commonConfigWriter->addValues(
                                $configurableModel->commonApplicationAttributes()
                            );
                            $webConfigWriter->addValues(
                                $configurableModel->webApplicationAttributes()
                            );
                            $consoleConfigWriter->addValues(
                                $configurableModel->consoleApplicationAttributes()
                            );
                            $kvConfigWriter->addValues(
                                [
                                    'kv-' . $model->module => $configurableModel->keyValueAttributes(),
                                ]
                            );

                            $configurableModel->saveState();

                            if (isset(Yii::$app->modules[$model->module]) === true) {
                                /** @var \yii\base\Module $module */
                                $module = Yii::$app->modules[$model->module];

                                // invalidate cache by module class name tag
                                TagDependency::invalidate(
                                    Yii::$app->cache,
                                    [
                                        ActiveRecordHelper::getCommonTag($module->className())
                                    ]
                                );


                            }

                        } else {
                            $isValid = false;
                        }
                    } else {
                        $isValid = false;
                    }
                    if ($isValid === false) {
                        $errorModule = $model->module;
                        // event is valid, stop saving data
                        break;
                    }
                } // model load from user input

            }  // /foreach


            if ($isValid === true) {

                $isValid =
                    $commonConfigWriter->commit() &&
                    $webConfigWriter->commit() &&
                    $consoleConfigWriter->commit() &&
                    $kvConfigWriter->commit();

                if (ini_get('opcache.enable')) {
                    if (function_exists('opcache_invalidate') === true) {
                        // invalidate opcache of this files!
                        opcache_invalidate(
                            Yii::getAlias($commonConfigWriter->filename),
                            true
                        );
                        opcache_invalidate(
                            Yii::getAlias($webConfigWriter->filename),
                            true);
                        opcache_invalidate(
                            Yii::getAlias($consoleConfigWriter->filename),
                            true
                        );
                        opcache_invalidate(
                            Yii::getAlias($kvConfigWriter->filename),
                            true
                        );


                    } else {
                        Yii::$app->session->setFlash(
                            'info',
                            Yii::t(
                                'app',
                                'You have opcache turned on but opcache_invalidate function is not available. That\'s strange.'
                            )
                        );
                    }
                }
            }

            if ($isValid === true) {
                Yii::$app->session->setFlash(
                    'success',
                    Yii::t(
                        'app',
                        'Configuration saved'
                    )
                );
            } else {
                Yii::$app->session->setFlash(
                    'error',
                    Yii::t(
                        'app',
                        'Error saving configuration for module {module}',
                        [
                            'module' => $errorModule,
                        ]
                    )
                );
            }
        }

        return $this->render(
            'index',
            [
                'models' => $models,
            ]
        );
    }
}