<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components\handlers;

use canis\storage\models\Storage;
use canis\base\exceptions\Exception;
use canis\base\FileInterface;
use canis\helpers\Date;
use canis\storage\components\BaseRecord;
use Yii;
use yii\helpers\FileHelper;
use Aws\S3\S3Client;

/**
 * LocalHandler [[@doctodo class_description:cascade\components\storageHandlers\core\LocalHandler]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class S3 extends \canis\storage\components\BaseHandler implements \canis\storage\components\UploadInterface
{

    public $accessKey;
    public $secretKey;
    public $bucket;
    public $region;
    public $acl = 'private';
    public $encrypt = false;
    public $reducedRedundancy = false;
    public $cacheTime = false;
    public $presignedExpireTime = '+5 minutes';
    public $serveLocally = false;

    public $bucketFormat = '{year}.{month}';

    protected $_credentials;
    protected $_cache = [];

    public function __sleep()
    {
        $keys = array_keys((array) $this);
        $bad = ["\0*\0_cache"];
        foreach ($keys as $k => $key) {
            if (in_array($key, $bad)) {
                unset($keys[$k]);
            }
        }

        return $keys;
    }

    public function init()
    {
        parent::init();
        if (empty($this->credentials)) {
            throw new InvalidConfigException('S3 credentials isn\'t set.');
        }

        if (empty($this->region)) {
            throw new InvalidConfigException('Region isn\'t set.');
        }

        if (empty($this->bucket)) {
            throw new InvalidConfigException('You must set bucket name.');
        }
        $this->getClient();
    }

    public function isHealthy()
    {
        try {
            return $this->getClient()->doesBucketExist($this->bucket);
        } catch (\Exception $e) {
            // throw $e;
        }
        return false;
    }
    
    public function queryPath($path, $recursive = true)
    {
        $baseKey = explode('.', $path);
        $results = $this->getClient()->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => implode("/", $baseKey)
        ]);
        $records = [];
        if (empty($results->get('Contents'))) {
            return $records;
        }
        foreach ($results->get('Contents') as $file) {
            if (substr($file['Key'], -1) === '/') { continue; }
            $records[] = $this->getFileRecord($file);
        }
        return $records;
    }

    public function storageToRecord(Storage $storage)
    {
        $file = [];
        $file['Key'] = $this->getKey($storage);
        $file['MimeType'] = $storage->type;
        $file['Size'] = $storage->size;
        $file['FileName'] = $storage->file_name;
        return Yii::createObject([
            'class' => S3Record::className(),
            'engine' => $this->storageEngine,
            'key' => $file['Key'],
            'size' => $file['Size'],
            'mime' => $file['MimeType'],
            'fileName' => $file['FileName']
        ]);
    }

    public function getFileRecord($file)
    {
        return Yii::createObject([
            'class' => S3Record::className(),
            'engine' => $this->storageEngine,
            'key' => $file['Key'],
            'size' => $file['Size']
        ]);
    }

    public function getCredentials()
    {
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return false;
        }
        return [
            'key' => $this->accessKey,
            'secret' => $this->secretKey
        ];
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

    public function exists(Storage $model)
    {
        return $this->getClient()->doesObjectExist($this->bucket, $this->getKey($model));
    }

    /**
     * @inheritdoc
     */
    public function serve(Storage $model)
    {
        if ($this->serveLocally || $this->isCached($model)) {
            $path = $this->getPath($model);
            if (!file_exists($path)) {
                return false;
            }
            Yii::$app->response->sendFile($path, trim($model->file_name), ['mimeType' => $model->type]);
        } else {
            $cmd = $this->getClient()->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $this->getKey($model),
                'ResponseContentDisposition' => 'attachment; filename="'.$model->file_name.'"'
            ]);
            $response = $this->getClient()->createPresignedRequest($cmd, $this->presignedExpireTime);
            if ($response && ($uri = $response->getUri())) {
                Yii::$app->response->redirect($uri->__toString());
                Yii::$app->end(0);
            }
        }
        return true;
    }

    public function isCachingEnabled()
    {
        return $this->cacheTime && isset(Yii::$app->fileCache);
    }

    public function getCacheKey(Storage $storage)
    {
        return md5($storage->primaryKey);
    }

    public function isCached(Storage $storage)
    {
        if (!$this->isCachingEnabled()) {
            return false;
        }
        if (($cache = Yii::$app->fileCache->exists($this->getCacheKey($storage)))) {
            return true;
        }
        return false;
    }

    public function getCache(Storage $storage)
    {
        if (!$this->isCachingEnabled()) {
            return false;
        }
        if (($cache = Yii::$app->fileCache->getValue($this->getCacheKey($storage)))) {
            $tmp = Yii::$app->fileStorage->getTempFile();
            file_put_contents($tmp, $cache);
            return $tmp;
        }
        return false;
    }
    public function saveCache(Storage $storage, $contents)
    {
        if (!$this->isCachingEnabled()) {
            return false;
        }
        return Yii::$app->fileCache->setValue($this->getCacheKey($storage), $contents, $this->cacheTime);
    }
    public function deleteCache()
    {
        if (!$this->isCachingEnabled()) {
            return false;
        }
        return Yii::$app->fileCache->deleteValue($this->getCacheKey($storage));
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
        $result = $this->getClient()->deleteObject([
            'Bucket' => $this->bucket, 
            'Key' => $this->getKey($model)
        ]);
        if (!$result) {
            return false;
        }
        return true;
    }

    public function getKey(Storage $model)
    {
        return implode('/', explode('.', $model->storage_key)) . '/' . $model->primaryKey;
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
        $cachedFile = $this->getCache($model);
        if ($cachedFile) {
            return $cachedFile;
        }
        $key = $this->getKey($model);
        $tmp = Yii::$app->fileStorage->getTempFile();
        $args = [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SaveAs' => $tmp
        ];
        $result = $this->execute('GetObject', $args);
        if ($result) {
            return $tmp;
        }
        return false;
    }

    public function getEngineStoragePath(Storage $storage)
    {
        return implode('/', $this->buildKey()) . '/' . $storage->primaryKey;
    }

    public function take(FileInterface $file, $key)
    {
        $filePath = $file->tempName;
        try {
            $fileStream = fopen($filePath, 'r+');
            $options = [
                'params' => [
                    'StorageClass' => $this->reducedRedundancy ? 'REDUCED_REDUNDANCY' : 'STANDARD'
                ]
            ];
            if ($this->encrypt) {
                $options['params']['ServerSideEncryption'] = 'AES256';
            }
            $uploadResult = $this->getClient()->upload($this->bucket, $key, $fileStream, $this->acl, $options);
            fclose($fileStream);
            return $uploadResult;
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
        if (!($model->{$attribute} instanceof FileInterface)) {
            return true;
        }
        $package = [];
        $baseKey = $this->buildKey();
        $package['storage_key'] = $storage->storage_key = implode('.', $baseKey);
        $key = $this->getEngineStoragePath($storage);
        $file = $model->{$attribute};
        if ($this->take($file, $key)) {
            if ($this->isCachingEnabled()) {
                $this->saveCache($storage, file_get_contents($filePath));
            }
            $package['file_name'] = $storage->file_name = $file->name;
            $package['size'] = $storage->size = $file->size;
            $package['type'] = $storage->type = $file->type;
            return $package;
        }
        return false;
    }

    public function getClient()
    {
        if (!isset($this->_cache['client'])) { 
            $this->_cache['client'] = new S3Client([
                'version' => '2006-03-01',
                'credentials' => $this->credentials,
                'region' => $this->region
            ]);
        }
        return $this->_cache['client'];
    }

    protected function execute($name, array $args)
    {
        $command = $this->getClient()->getCommand($name, $args);
        return $this->getClient()->execute($command);
    }

    /**
     * @inheritdoc
     */
    public function generateInternal($item)
    {
        return $item->fileInput();
    }
}
