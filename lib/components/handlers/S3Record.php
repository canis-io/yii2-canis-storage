<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storageHandlers\core;

use Yii;
use yii\helpers\FileHelper;

/**
 * LocalHandler [[@doctodo class_description:cascade\components\storageHandlers\core\LocalHandler]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class S3Record extends \canis\storage\BaseRecord
{
	public $key;
	public $size;
	public $mime;
	protected $_tmp;

	public function collect()
	{
		if (!isset($this->_tmp)) {
	        $this->_tmp = Yii::$app->fileStorage->getTempFile();
	        $args = [
	            'Bucket' => $this->engine->bucket,
	            'Key' => $this->key,
	            'SaveAs' => $this->_tmp
	        ];
	        $result = $this->engine->execute('GetObject', $args);
	        if (!$result || !file_exists($this->_tmp) || filesize($this->_tmp)) {
	        	$this->_tmp = false;
	        	return false;
	        }
		}
		return $this->file;
	}

	public function isStillAvailable()
	{
		return $this->engine->getClient()->doesObjectExist($this->engine->bucket, $this->engine->getKey($model));
	}

	public function getFileName()
	{
		return basename($this->key);
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
		$result = $this->engine->getClient()->deleteObject([
			'Bucket' => $this->engine->bucket, 
			'Key' => $this->key
		]);
		return $result && $result->get('DeleteMarker') === true;
	}

	public function rename($newName)
	{
		$result = $this->engine->getClient()->copyObject([
			'Bucket' => $this->engine->bucket, 
			'CopySource' => $this->engine->bucket ."/". $this->key, 
			'Key' => $newName
		]);
		if ($result && $result->get('CopyObjectResult.ETag') !== null) {
			$deleteResult = $this->engine->getClient()->deleteObject([
				'Bucket' => $this->engine->bucket, 
				'Key' => $this->key
			]);
			$this->key = $newName;
			return $deleteResult && $deleteResult->get('DeleteMarker') === true;
		}
		return false;

	}
}