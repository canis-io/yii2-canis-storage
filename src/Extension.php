<?php
namespace canis\storage;

use Yii;

class Extension implements \yii\base\BootstrapInterface
{
    public function bootstrap($app)
    {
    	\Yii::$app->controllerMap['storage'] = console\StorageController::className();
        if (\Yii::$app instanceof \yii\console\Application) {
        	\Yii::$app->registerMigrationAlias('@canis/storage/migrations');
    	}
    }
}
