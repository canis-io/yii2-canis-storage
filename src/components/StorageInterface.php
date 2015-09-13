<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components;

use canis\storage\models\Storage;

interface UploadInterface
{
    public function handleUpload(Storage $storage, $model, $attribute);
}
