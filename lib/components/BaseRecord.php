<?php
namespace canis\storage\components;

use Yii;
use canis\base\File as CanisFile;
use canis\helpers\FileHelper;

abstract class BaseRecord extends \canis\base\Component
{
	public $engine;
	public function collectFileObject()
	{
		if (!($filePath = $this->collect())) {
			return false;
		}
		return CanisFile::createInstance($this->fileName, $filePath, $this->mimeType, $this->size);
	}

	abstract public function collect();
	abstract public function isStillAvailable();
	abstract public function getFileName();
	abstract public function getSize();
	abstract public function getMimeType();
	abstract public function delete();
	abstract public function rename($newName);
}