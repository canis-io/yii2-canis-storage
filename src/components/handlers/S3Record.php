<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components\handlers;

use Yii;
use yii\helpers\FileHelper;

/**
 * LocalHandler [[@doctodo class_description:cascade\components\storageHandlers\core\LocalHandler]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class S3Record extends \canis\storage\components\BaseRecord
{
	public $key;
	public $size;
	public $mime;
	protected $_fileName;
	protected $_tmp;

	public function collect()
	{
		if (!isset($this->_tmp)) {
	        $this->_tmp = Yii::$app->fileStorage->getTempFile();
	        $args = [
	            'Bucket' => $this->handler->bucket,
	            'Key' => $this->key,
	            'SaveAs' => $this->_tmp
	        ];
	        $result = $this->handler->getClient()->getObject($args);
	        if (!$result || !file_exists($this->_tmp) || filesize($this->_tmp) === 0) {
	        	$this->_tmp = false;
	        	return false;
	        }
		}
		return $this->_tmp;
	}

	public function getHandler()
	{
		return $this->engine->storageHandler;
	}

	public function exists()
	{
		return $this->handler->getClient()->doesObjectExist($this->handler->bucket, $this->key);
	}

	public function getFileName()
	{
		if (isset($this->_fileName)) {
			return $this->_fileName;
		}

		return basename($this->key);
	}

	public function setFileName($fileName)
	{
		$this->_fileName = $filename;
		return true;
	}

	public function getSize()
	{
		return $this->size;
	}

	public function getMimeType()
	{
		if (isset($this->mime)) {
			return $this->mime;
		}
		if (!($file = $this->collect()) || !file_exists($file)) {
			return false;
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$this->mime = finfo_file($finfo, $file);
		if (empty($this->mime)) {
			return 'application/octet-stream';
		}
		finfo_close($finfo);
		return $this->mime;
	}

	public function delete()
	{
		$result = $this->handler->getClient()->deleteObject([
			'Bucket' => $this->handler->bucket, 
			'Key' => $this->key
		]);
		return $result && $result->get('DeleteMarker') === true;
	}

	public function copy($newName)
	{
		$options = [
			'Bucket' => $this->handler->bucket, 
			'CopySource' => $this->handler->bucket ."/". $this->key, 
			'Key' => $newName
		];
        if ($this->handler->encrypt) {
            $options['ServerSideEncryption'] = 'AES256';
        }
        if ($this->handler->encrypt) {
            $options['StorageClass'] = $this->handler->reducedRedundancy ? 'REDUCED_REDUNDANCY' : 'STANDARD';
        }
		$result = $this->handler->getClient()->copyObject($options);
		if ($result && $result->getPath('CopyObjectResult.ETag') !== null) {
			return true;
		}
		return false;
	}

	public function rename($newName)
	{
		if ($this->copy($newName)) {
			$result = $this->handler->getClient()->deleteObject([
				'Bucket' => $this->handler->bucket, 
				'Key' => $this->key
			]);
			$this->key = $newName;
			return $result && $this->exists();
		}
		return false;
	}
}