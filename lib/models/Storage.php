<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\models;

use canis\db\ActiveRecordRegistryTrait;

/**
 * Storage is the model class for table "storage".
 *
 * @property string $id
 * @property string $storage_engine_id
 * @property string $storage_key
 * @property string $file_name
 * @property string $type
 * @property string $size
 * @property string $created
 * @property string $modified
 * @property ObjectFile[] $objectFiles
 * @property Registry $registry
 * @property StorageEngineId $storageEngine
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class Storage extends \canis\db\ActiveRecord
{
    use ActiveRecordRegistryTrait {
        behaviors as baseBehaviors;
    }

    /**
     * @inheritdoc
     */
    public static function isAccessControlled()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'storage';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['storage_engine_id', 'file_name', 'type', 'size'], 'safe'],
            [['storage_engine_id', 'file_name', 'type', 'size'], 'required', 'on' => 'fill'],
            [['size'], 'integer'],
            [['created', 'modified'], 'safe'],
            [['id', 'storage_engine_id'], 'string', 'max' => 36],
            [['storage_key', 'file_name'], 'string', 'max' => 255],
            [['type'], 'string', 'max' => 100],
        ];
    }

    /**
     * [[@doctodo method_description:fillKill]].
     *
     * @param [[@doctodo param_type:attributes]] $attributes [[@doctodo param_description:attributes]]
     *
     * @return [[@doctodo return_type:fillKill]] [[@doctodo return_description:fillKill]]
     */
    public function fillKill($attributes)
    {
        if ($attributes === false) {
            $this->delete();

            return false;
        } elseif ($attributes !== true) {
            $this->scenario = 'fill';
            $this->attributes = $attributes;
            if (!$this->save()) {
                $this->delete();

                return false;
            }

            return true;
        } else {
            return true;
        }
    }

    /**
     * [[@doctodo method_description:startBlank]].
     *
     * @param [[@doctodo param_type:engine]] $engine [[@doctodo param_description:engine]]
     *
     * @return [[@doctodo return_type:startBlank]] [[@doctodo return_description:startBlank]]
     */
    public static function startBlank($engine)
    {
        $className = self::className();
        $blank = new $className();
        $blank->storage_engine_id = $engine->primaryKey;
        if ($blank->save()) {
            return $blank;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'storage_engine_id' => 'Storage Engine ID',
            'storage_key' => 'Storage Key',
            'file_name' => 'File Name',
            'type' => 'Type',
            'size' => 'Size',
            'created' => 'Created',
            'modified' => 'Modified',
        ];
    }

    /**
     * Get registry.
     *
     * @return \yii\db\ActiveRelation
     */
    public function getRegistry()
    {
        return $this->hasOne(Registry::className(), ['id' => 'id']);
    }

    /**
     * Get storage engine.
     *
     * @return \yii\db\ActiveRelation
     */
    public function getStorageEngine()
    {
        return $this->hasOne(StorageEngine::className(), ['id' => 'storage_engine_id']);
    }
}
