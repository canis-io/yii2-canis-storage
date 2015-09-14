<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components;

use canis\base\exceptions\Exception;
use canis\base\FileInterface;
use canis\web\UploadedFile;
use Yii;
use yii\base\Event;

/**
 * StorageBehavior [[@doctodo class_description:cascade\components\storageHandlers\StorageBehavior]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class StorageBehavior extends \canis\db\behaviors\ActiveRecord
{
    /**
     * @var [[@doctodo var_type:storageAttribute]] [[@doctodo var_description:storageAttribute]]
     */
    public $storageAttribute = 'storage_id';

    /**
     * @var [[@doctodo var_type:_storageEngine]] [[@doctodo var_description:_storageEngine]]
     */
    protected $_storageEngine;
    /**
     * @var [[@doctodo var_type:_oldStorage]] [[@doctodo var_description:_oldStorage]]
     */
    protected $_oldStorage;

    /**
     * @var [[@doctodo var_type:required]] [[@doctodo var_description:required]]
     */
    public $required = true;

    public $validateFileOnSave = true;

    /**
     * Converts object to string.
     *
     * @return [[@doctodo return_type:__toString]] [[@doctodo return_description:__toString]]
     */
    public function __toString()
    {
        return $this->primaryKey;
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            \canis\db\ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            \canis\db\ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            \canis\db\ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            \canis\db\ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            \canis\db\ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            \canis\db\ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * @inheritdoc
     */
    public function safeAttributes()
    {
        return ['storageEngine', 'storage'];
    }

    /**
     * Set storage.
     *
     * @param [[@doctodo param_type:value]] $value [[@doctodo param_description:value]]
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     */
    public function setStorage($value)
    {
        $value = $this->storageHandler->beforeSetStorage($value);
        if ($value instanceof FileInterface) {
            $this->owner->{$this->storageAttribute} = $value;
        } else {
            throw new Exception("Trying to set storage item that isn't part of the file interface!");
        }
    }

    /**
     * Get storage.
     *
     * @return [[@doctodo return_type:getStorage]] [[@doctodo return_description:getStorage]]
     */
    public function getStorage()
    {
        if (isset($this->owner->{$this->storageAttribute}) && $this->owner->{$this->storageAttribute} instanceof FileInterface) {
            return $this->owner->{$this->storageAttribute};
        }

    }

    /**
     * [[@doctodo method_description:loadPostFile]].
     *
     * @param [[@doctodo param_type:tabId]] $tabId [[@doctodo param_description:tabId]] [optional]
     */
    public function loadPostFile($tabId = null)
    {
        $attribute = $this->storageAttribute;
        if (isset($tabId)) {
            $attribute = "[{$tabId}]$attribute";
        }
        if (($fileField = UploadedFile::getInstance($this->owner, $attribute)) && !empty($fileField)) {
            $this->_oldStorage = $this->owner->{$this->storageAttribute};
            $this->owner->{$this->storageAttribute} = $fileField;
        }
    }

    /**
     * [[@doctodo method_description:beforeSave]].
     *
     * @param [[@doctodo param_type:event]] $event [[@doctodo param_description:event]]
     *
     * @return [[@doctodo return_type:beforeSave]] [[@doctodo return_description:beforeSave]]
     */
    public function beforeSave($event)
    {
        if (!$this->required && empty($this->owner->{$this->storageAttribute})) {
            return true;
        }
        if (is_object($this->owner->{$this->storageAttribute}) && !$this->storageHandler->beforeSave($this->owner, $this->storageAttribute)) {
            $event->isValid = false;
            $this->owner->addError($this->storageAttribute, 'Unable to save file in storage engine. Try again later. (' . $this->storageHandler->error . ')');

            return false;
        }
    }

    /**
     * [[@doctodo method_description:afterSave]].
     *
     * @param [[@doctodo param_type:event]] $event [[@doctodo param_description:event]]
     */
    public function afterSave($event)
    {
        if (!empty($this->_oldStorage) && $this->_oldStorage !== $this->owner->{$this->storageAttribute}) {
            $storageClass = Yii::$app->classes['Storage'];
            $storageObject = $storageClass::get($this->_oldStorage, false);
            if (!empty($storageObject)) {
                $this->handleDelete($storageObject);
            }
        }
    }

    /**
     * [[@doctodo method_description:handleDelete]].
     *
     * @param [[@doctodo param_type:storageObject]] $storageObject [[@doctodo param_description:storageObject]]
     *
     * @return [[@doctodo return_type:handleDelete]] [[@doctodo return_description:handleDelete]]
     */
    public function handleDelete($storageObject)
    {
        if (is_null($this->storageEngine)) {
            $this->storageEngine = $this->storageObject->storageEngine;
        }
        if ($storageObject && !$this->storageHandler->afterDelete($storageObject)) {
            return false;
        } elseif ($storageObject) {
            return $storageObject->delete();
        }
        return false;
    }

    /**
     * [[@doctodo method_description:afterDelete]].
     *
     * @param [[@doctodo param_type:event]] $event [[@doctodo param_description:event]]
     */
    public function afterDelete($event)
    {
        $this->handleDelete($this->storageObject);
    }

    /**
     * [[@doctodo method_description:serve]].
     *
     * @return [[@doctodo return_type:serve]] [[@doctodo return_description:serve]]
     */
    public function serve()
    {
        if (!$this->storageEngine || !$this->storageEngine->storageHandler) {
            return false;
        }
        $storageObject = $this->storageObject;
        if (!$storageObject) {
            return false;
        }
        if (!$this->storageHandler->serve($storageObject)) {
            return false;
        }

        return true;
    }

    /**
     * Get storage path.
     *
     * @return [[@doctodo return_type:getStoragePath]] [[@doctodo return_description:getStoragePath]]
     */
    public function getStoragePath()
    {
        if (!$this->storageEngine || !$this->storageEngine->storageHandler) {
            return false;
        }
        $storageObject = $this->storageObject;
        if (!$storageObject) {
            return false;
        }
        if (!method_exists($this->storageHandler, 'getPath')) {
            return false;
        }

        return $this->storageHandler->getPath($storageObject);
    }
    /**
     * Get storage object.
     *
     * @return [[@doctodo return_type:getStorageObject]] [[@doctodo return_description:getStorageObject]]
     */
    public function getStorageObject()
    {
        if (empty($this->owner->{$this->storageAttribute})) {
            return false;
        }
        $registryClass = Yii::$app->classes['Registry'];

        return $registryClass::getObject($this->owner->{$this->storageAttribute});
    }

    /**
     * [[@doctodo method_description:beforeValidate]].
     *
     * @param [[@doctodo param_type:event]] $event [[@doctodo param_description:event]]
     *
     * @return [[@doctodo return_type:beforeValidate]] [[@doctodo return_description:beforeValidate]]
     */
    public function beforeValidate($event)
    {
        if (!$this->required && empty($this->owner->{$this->storageAttribute})) {
            return true;
        }
        if (empty($this->storageEngine)) {
            $this->owner->addError($this->storageAttribute, 'Unknown storage engine!');

            return false;
        } elseif (!$this->storageHandler->validate($this->owner, $this->storageAttribute)) {
            return false;
        }

        return true;
    }

    /**
     * Get storage engine.
     *
     * @return [[@doctodo return_type:getStorageEngine]] [[@doctodo return_description:getStorageEngine]]
     */
    public function getStorageEngine()
    {
        if (is_null($this->_storageEngine)) {
            $this->storageEngine = Yii::$app->collectors['storageEngines']->getOne(Yii::$app->params['defaultStorageEngine']);
        }

        return $this->_storageEngine;
    }

    public function getStorageHandler()
    {
        if (!($storageEngine = $this->storageEngine) || !isset($storageEngine->object)) {
            return false;
        }
        return $storageEngine->object;
    }

    /**
     * Set storage engine.
     *
     * @param [[@doctodo param_type:value]] $value [[@doctodo param_description:value]]
     *
     * @return [[@doctodo return_type:setStorageEngine]] [[@doctodo return_description:setStorageEngine]]
     */
    public function setStorageEngine($value)
    {
        if (is_object($value)) {
            $this->_storageEngine = $value;
        } else {
            $storageEngineClass = Yii::$app->classes['StorageEngine'];
            $engineTest = $storageEngineClass::find()->pk($value)->one();
            if ($engineTest) {
                return $this->_storageEngine = $engineTest;
            }
        }

        return false;
    }
}
