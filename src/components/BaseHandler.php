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
use canis\base\collector\CollectedObjectTrait;
use canis\base\FileInterface;
use canis\helpers\Date;
use Yii;

/**
 * Handler [[@doctodo class_description:cascade\components\storageHandlers\Handler]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
abstract class BaseHandler extends \canis\base\Component implements \canis\base\collector\CollectedObjectInterface
{
    use CollectedObjectTrait;

    public $storageEngine;

    /**
     * @var [[@doctodo var_type:error]] [[@doctodo var_description:error]]
     */
    public $error;

    public $destructiveShift = true;


    /**
     * @var [[@doctodo var_type:localFileClass]] [[@doctodo var_description:localFileClass]]
     */
    public $localFileClass = 'canis\base\File';
    
    abstract public function isHealthy();

    abstract public function exists(Storage $model);

    /**
     * [[@doctodo method_description:generateInternal]].
     *
     * @param [[@doctodo param_type:item]] $item [[@doctodo param_description:item]]
     */
    abstract public function generateInternal($item);
    
    /**
     * [[@doctodo method_description:handleSave]].
     *
     * @param cascade\models\Storage            $storage   [[@doctodo param_description:storage]]
     * @param [[@doctodo param_type:model]]     $model     [[@doctodo param_description:model]]
     * @param [[@doctodo param_type:attribute]] $attribute [[@doctodo param_description:attribute]]
     */
    abstract public function handleSave(Storage $storage, $model, $attribute);

    abstract public function getEngineStoragePath(Storage $storage);

    public function handleShift(BaseRecord $record, Storage $storage, $model, $attribute)
    {
        $package = [];
        $baseKey = $this->buildKey();
        $package['storage_key'] = $storage->storage_key =  implode('.', $baseKey);
        $key = $this->getEngineStoragePath($storage);
        if ($this->destructiveShift) {
            $result = $record->rename($key);
        } else {
            $result = $record->copy($key);
        }
        if ($result) {
            $package['file_name'] = $storage->file_name = $record->fileName;
            $package['size'] = $storage->size = $record->size;
            $package['type'] = $storage->type = $record->mimeType;
            return $package;
        }
        return false;
    }

    /**
     * [[@doctodo method_description:serve]].
     *
     * @param cascade\models\Storage $storage [[@doctodo param_description:storage]]
     */
    abstract public function serve(Storage $storage);

    /**
     * [[@doctodo method_description:serve]].
     *
     * @param cascade\models\Storage $storage [[@doctodo param_description:storage]]
     */
    abstract public function queryPath($path, $recursive = true);

    /**
     * @inheritdoc
     */
    public function validate($model, $attribute)
    {
        $errorMessage = "No file was uploaded!";
        if ($model->{$attribute} instanceof FileInterface || $model->{$attribute} instanceof \yii\web\UploadedFile) {
            if (!$model->{$attribute}->hasError) {
                return true;
            } else {
                $errorMessage = 'An error occurred during file transport.';
            }
        } elseif (!$model->isNewRecord || !$model->validateFileOnSave) {
            return true;
        }
        $model->addError($attribute, $errorMessage);

        return false;
    }

    /**
     * [[@doctodo method_description:generate]].
     *
     * @param [[@doctodo param_type:item]] $item [[@doctodo param_description:item]]
     *
     * @return [[@doctodo return_type:generate]] [[@doctodo return_description:generate]]
     */
    public function generate($item)
    {
        $rendered = $this->generateInternal($item);
        if ($rendered) {
            $this->prepareRendered($rendered, $item);
        }

        return $rendered;
    }

    /**
     * [[@doctodo method_description:prepareRendered]].
     *
     * @param [[@doctodo param_type:rendered]] $rendered [[@doctodo param_description:rendered]]
     * @param [[@doctodo param_type:item]]     $item     [[@doctodo param_description:item]]
     */
    public function prepareRendered(&$rendered, $item)
    {
    }

    /**
     * [[@doctodo method_description:hasFile]].
     *
     * @return [[@doctodo return_type:hasFile]] [[@doctodo return_description:hasFile]]
     */
    public function hasFile()
    {
        return $this instanceof UploadInterface;
    }

    /**
     * [[@doctodo method_description:prepareStorage]].
     *
     * @return [[@doctodo return_type:prepareStorage]] [[@doctodo return_description:prepareStorage]]
     */
    protected function prepareStorage()
    {
        $storageClass = Yii::$app->classes['Storage'];
        return $storageClass::startBlank($this->storageEngine);
    }

    public function createStorageFromSelf(BaseRecord $record, $model, $attribute)
    {
        if ($record->engine !== $this->storageEngine) {
            return false;
        }
        if (!$record->exists()) {
            return false;
        }
        $storage = $this->prepareStorage();
        $fill = $this->handleShift($record, $storage, $model, $attribute);
        $result = $storage->fillKill($fill);
        if ($result) {
            $model->{$attribute} = $storage->primaryKey;
            return true;
        }
        return false;
    }

    /**
     * [[@doctodo method_description:afterDelete]].
     *
     * @param cascade\models\Storage       $model  [[@doctodo param_description:model]]
     *
     * @return [[@doctodo return_type:afterDelete]] [[@doctodo return_description:afterDelete]]
     */
    public function afterDelete(Storage $model)
    {
        return true;
    }

    /**
     * [[@doctodo method_description:beforeSave]].
     *
     * @param [[@doctodo param_type:model]]     $model     [[@doctodo param_description:model]]
     * @param [[@doctodo param_type:attribute]] $attribute [[@doctodo param_description:attribute]]
     *
     * @return [[@doctodo return_type:beforeSave]] [[@doctodo return_description:beforeSave]]
     */
    public function beforeSave($model, $attribute)
    {
        $result = false;
        if (($storage = $this->prepareStorage())) {
            $fill = $this->handleSave($storage, $model, $attribute);
            $result = $storage->fillKill($fill);
            if ($result) {
                $model->{$attribute} = $storage->primaryKey;
            }
        }

        return $result;
    }

    /**
     * [[@doctodo method_description:beforeSetStorage]].
     *
     * @param [[@doctodo param_type:value]] $value [[@doctodo param_description:value]]
     *
     * @return [[@doctodo return_type:beforeSetStorage]] [[@doctodo return_description:beforeSetStorage]]
     */
    
    /**
     * @inheritdoc
     */
    public function beforeSetStorage($value)
    {
        if (is_array($value) && isset($value['tempName'])) {
            if (!isset($value['class'])) {
                $value['class'] = $this->localFileClass;
            }
            if (!isset($value['size'])) {
                $value['size'] = filesize($value['tempName']);
            }
            if (!isset($value['type'])) {
                $value['type'] = FileHelper::getMimeType($value['tempName']);
            }
            if (!isset($value['name'])) {
                $value['name'] = basename($vale['tempName']);
            }
            $value = Yii::createObject($value);
        }

        return $value;
    }


    /**
     * Get key variables.
     *
     * @return [[@doctodo return_type:getKeyVariables]] [[@doctodo return_description:getKeyVariables]]
     */
    public function getKeyVariables()
    {
        $vars = [];
        $time = Date::time();
        $vars['{year}'] = Date::date("Y", $time);
        $vars['{month}'] = Date::date("m", $time);
        $vars['{day}'] = Date::date("d", $time);
        $vars['{hour}'] = Date::date("H", $time);
        $vars['{minute}'] = Date::date("i", $time);

        return $vars;
    }
}
