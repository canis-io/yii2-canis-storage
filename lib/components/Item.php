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
    /**
     * @var [[@doctodo var_type:publicEngine]] [[@doctodo var_description:publicEngine]]
     */
    public $publicEngine = false;
    /**
     * @var [[@doctodo var_type:publicEngineGroup]] [[@doctodo var_description:publicEngineGroup]]
     */
    public $publicEngineGroup = 'top';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->publicEngine !== false && Yii::$app->isDbAvailable && !defined('CANIS_SETUP')) {
            Yii::$app->collectors->onAfterInit([$this, 'ensurePublicEngine']);
        }
    }

    public function getHandler()
    {
        return $this->object;
    }

    /**
     * [[@doctodo method_description:ensurePublicEngine]].
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     */
    public function ensurePublicEngine()
    {
        if ($this->publicEngine !== false) {
            // @todo cache this
            $storageEngineClass = Yii::$app->classes['StorageEngine'];
            $publicEngine = $storageEngineClass::find()->disableAccessCheck()->where(['system_id' => $this->systemId])->one();
            if (empty($publicEngine)) {
                $publicEngine = new $storageEngineClass();
                $publicEngine->asGroup($this->publicEngineGroup);
                if (is_array($this->publicEngine)) {
                    $publicEngine->data = serialize($this->publicEngine);
                }
                $publicEngine->handler = $this->systemId;
                if (!$publicEngine->save()) {
                    throw new Exception("Unable to initialize public storage engine for {$this->systemId}");
                }
            }
            if (!$publicEngine->asGroup($this->publicEngineGroup)->can('read')) {
                $publicEngine->allow(['list', 'read']);
            }
        }
    }
}
