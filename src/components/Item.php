<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components;

use canis\storage\models\Storage;
use canis\storage\models\StorageEngine;
use canis\base\exceptions\Exception;
use Yii;

/**
 * Item [[@doctodo class_description:cascade\components\storageHandlers\Item]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class Item extends \canis\base\collector\Item
{
    public $ensureGroups = [];
    private $_model;
    
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->object->storageEngine = $this;
    }

    public function getStorageHandler()
    {
        return $this->object;
    }

    public function getModel()
    {
        if ($this->_model === null) {
            $storageEngineClass = Yii::$app->classes['StorageEngine'];
            $model = $storageEngineClass::find()->where(['system_id' => $this->systemId])->one();
            if (empty($model)) {
                $model = new $storageEngineClass;
                $model->system_id = $this->systemId;
                if (!empty($this->ensureGroups)) {
                    foreach ($this->ensureGroups as $group) {
                        $model->allow('read', Yii::$app->gk->getGroup($group));
                    }
                }
                if (!$model->save()) {
                    throw new \Exception("Unable to set up storage engine {$this->systemId}");
                }
            }
            $this->_model = $model;
        }
        return $this->_model;
    }
}
