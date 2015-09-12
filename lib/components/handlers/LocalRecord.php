<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\handlers;

use Yii;
use yii\helpers\FileHelper;

class LocalRecord extends \canis\storage\BaseRecord
{
	public $file;
	public $mime;

	public function collect()
	{
		if (!file_exists($this->file)) {
			return false;
		}
		return $this->file;
	}

	public function isStillAvailable()
	{
		if (!file_exists($this->file)) {
			return false;
		}
		return true;
	}

	public function getFileName()
	{
		if (!file_exists($this->file)) {
			return false;
		}
		return basename($this->file);
	}

	public function getSize()
	{
		if (!file_exists($this->file)) {
			return false;
		}
		return filesize($this->file);
	}

	public function getMimeType()
	{
		if (isset($this->mime)) {
			return $this->mime;
		}
		if (!file_exists($this->file)) {
			return false;
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$this->mime = finfo_file($finfo, $this->file);
		if (empty($this->mime)) {
			return 'application/octet-stream';
		}
		finfo_close($finfo);
		return $this->mime;
	}

	public function delete()
	{
		@unlink($this->file);
		return true;
	}

	public function rename($newName)
	{
		$baseName = basename($this->fileName);
		$newPath = substr($this->file, 0, strlen($this->file) - strlen($baseName)) . $newName;
		rename($this->file, $newPath);
		if (file_exists($newPath)) {
			$this->file = $newPath;
			return true;
		}
		return false;
	}
}