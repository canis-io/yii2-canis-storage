<?php
namespace canis\storage;

use Yii;

class Extension implements \yii\base\BootstrapInterface
{
    public function bootstrap($app)
    {
        \Yii::$app->registerMigrationAlias('@canis/storage/migrations');
    }
}
