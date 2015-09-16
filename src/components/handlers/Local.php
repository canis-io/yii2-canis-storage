<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components\handlers;

use canis\storage\models\Storage;
use canis\storage\components\BaseRecord;
use canis\base\exceptions\Exception;
use canis\base\FileInterface;
use canis\helpers\Date;
use Yii;
use yii\helpers\FileHelper;

/**
 * LocalHandler [[@doctodo class_description:cascade\components\storageHandlers\core\LocalHandler]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class Local extends \canis\storage\components\BaseHandler implements \canis\storage\components\UploadInterface
{
    /**
     * @var [[@doctodo var_type:localFileClass]] [[@doctodo var_description:localFileClass]]
     */
    public $localFileClass = 'canis\base\File';
    /**
     * @var [[@doctodo var_type:bucketFormat]] [[@doctodo var_description:bucketFormat]]
     */
    public $bucketFormat = '{year}.{month}';
    /**
     * @var [[@doctodo var_type:_baseDir]] [[@doctodo var_description:_baseDir]]
     */
    protected $_baseDir;

    public function isHealthy()
    {
        return is_dir($this->baseDir) && is_writable($this->baseDir);
    }
    
    public function queryPath($path, $recursive = true)
    {
        $baseKey = explode('.', $path);
        $dirPath = $this->baseDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $baseKey);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }
        if (!is_dir($dirPath)) {
            return false;
        }
        $files = FileHelper::findFiles($dirPath, ['recrusive' => true]);
        $records = [];
        foreach ($files as $file) {
            $records[] = $this->getFileRecord($file);
        }
        return $records;
    }

    public function storageToRecord(Storage $storage)
    {
        $file = [];
        $file['File'] = $this->getPath($storage);
        $file['MimeType'] = $storage->type;
        $file['FileName'] = $storage->file_name;
        return Yii::createObject([
            'class' => LocalRecord::className(),
            'engine' => $this->storageEngine,
            'file' => $file['File'],
            'mime' => $file['MimeType'],
            'fileName' => $file['FileName']
        ]);
    }

    public function getFileRecord($file)
    {
        return Yii::createObject(['class' => LocalRecord::className(), 'engine' => $this->storageEngine, 'file' => $file]);
    }

    /**
     * [[@doctodo method_description:buildKey]].
     *
     * @return [[@doctodo return_type:buildKey]] [[@doctodo return_description:buildKey]]
     */
    public function buildKey()
    {
        $keyVariables = $this->keyVariables;
        $keyParts = explode('.', $this->bucketFormat);
        foreach ($keyParts as &$part) {
            $part = strtr($part, $keyVariables);
        }

        return $keyParts;
    }

    /**
     * @inheritdoc
     */
    public function serve(Storage $storage)
    {
        $path = $this->getPath($storage);
        if (!file_exists($path)) {
            return false;
        }
        Yii::$app->response->sendFile($path, trim($storage->file_name), ['mimeType' => $storage->type]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function handleSave(Storage $storage, $model, $attribute)
    {
        return $this->handleUpload($storage, $model, $attribute);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(Storage $model)
    {
        if (!$model) {
            return true;
        }
        $path = $this->getPath($model);
        if (file_exists($path)) {
            @unlink($path);
        }

        return true;
    }

    public function exists(Storage $model)
    {
        $path = $this->getPath($model);
        return $path && file_exists($path);
    }

    /**
     * Get path.
     *
     * @param cascade\models\Storage $model [[@doctodo param_description:model]]
     *
     * @return [[@doctodo return_type:getPath]] [[@doctodo return_description:getPath]]
     */
    public function getPath(Storage $model)
    {
        $baseKey = explode('.', $model->storage_key);
        $dirPath = $this->baseDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $baseKey);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }
        if (!is_dir($dirPath)) {
            $this->error = 'Unable to create storage directory';

            return false;
        }

        return $dirPath . DIRECTORY_SEPARATOR . $model->primaryKey;
    }

    
    public function getEngineStoragePath(Storage $storage)
    {
        return implode(DIRECTORY_SEPARATOR, $this->buildKey()) . DIRECTORY_SEPARATOR . $storage->primaryKey;
    }

    public function take(FileInterface $file, $path)
    {
        $filePath = $file->tempName;
        try {
            if ($file->saveAs($path) && file_exists($path) && is_readable($path)) {
                return true;
            }
        } catch (\Exception $e) {
            throw $e;
            return false;
        }
        return false;
    }

    /**
     * [[@doctodo method_description:handleUpload]].
     *
     * @param cascade\models\Storage            $storage   [[@doctodo param_description:storage]]
     * @param [[@doctodo param_type:model]]     $model     [[@doctodo param_description:model]]
     * @param [[@doctodo param_type:attribute]] $attribute [[@doctodo param_description:attribute]]
     *
     * @return [[@doctodo return_type:handleUpload]] [[@doctodo return_description:handleUpload]]
     */
    public function handleUpload(Storage $storage, $model, $attribute)
    {
        if (!($model->{$attribute} instanceof FileInterface) && !($model->{$attribute} instanceof \yii\web\UploadedFile)) {
            return true;
        }
        $package = [];
        $baseKey = $this->buildKey();
        $package['storage_key'] = $storage->storage_key = implode('.', $baseKey);
        $dirPath = $this->baseDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $baseKey);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }
        if (!is_dir($dirPath)) {
            $this->error = 'Unable to create storage directory';

            return false;
        }
        $path = $dirPath . DIRECTORY_SEPARATOR . $storage->primaryKey;
        $file = $model->{$attribute};
        if ($this->take($file, $path)) {
            $package['file_name'] = $storage->file_name = $file->name;
            $package['size'] = $storage->size = $file->size;
            $package['type'] = $storage->type = FileHelper::getMimeType($path);

            return $package;
        }
        return false;
    }

    /**
     * Set base dir.
     *
     * @param [[@doctodo param_type:value]] $value [[@doctodo param_description:value]]
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     * @return [[@doctodo return_type:setBaseDir]] [[@doctodo return_description:setBaseDir]]
     *
     */
    public function setBaseDir($value)
    {
        $value = Yii::getAlias($value);
        if (!is_dir($value)) {
            @mkdir($value, 0755, true);
            if (!is_dir($value)) {
                throw new Exception("Unable to set local storage base directory: {$value}");
            }
        }

        return $this->_baseDir = $value;
    }

    /**
     * Get base dir.
     *
     * @return [[@doctodo return_type:getBaseDir]] [[@doctodo return_description:getBaseDir]]
     */
    public function getBaseDir()
    {
        return $this->_baseDir;
    }

    /**
     * @inheritdoc
     */
    public function generateInternal($item)
    {
        return $item->fileInput();
    }
}
