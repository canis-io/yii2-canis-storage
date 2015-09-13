<?php
namespace canis\storage\console;

use Yii;
use yii\helpers\FileHelper;

ini_set('memory_limit', -1);

class StorageController extends \canis\console\Controller
{
    public $verbose = false;
    public function actionIndex()
    {
        //$this->cleanVolumes();
    }
    public function actionModels()
    {
        if (!isset(Yii::$app->collectors['storageEngines'])) {
            throw new \Exception("No storage handler collection");
        }
        foreach (Yii::$app->collectors['storageEngines']->getAll() as $engine) {
            \d([$engine->systemId, $engine->model->id]);
        }
    }
    public function actionHealth()
    {
        if (!isset(Yii::$app->collectors['storageEngines'])) {
            throw new \Exception("No storage handler collection");
        }
        \d(Yii::$app->collectors['storageEngines']->getEngineHealth());
    }
    /**
     * @inheritdoc
     */
    public function options($id)
    {
        return array_merge(parent::options($id), []);
    }
}
