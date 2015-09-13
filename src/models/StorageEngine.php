<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\models;

use canis\db\ActiveRecordRegistryTrait;
use Yii;

/**
 * StorageEngine is the model class for table "storage_engine".
 *
 * @property string $id
 * @property string $system_id
 * @property string $data
 * @property string $created
 * @property string $modified
 * @property Registry $registry
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class StorageEngine extends \canis\db\ActiveRecord
{
    use ActiveRecordRegistryTrait {
        behaviors as baseBehaviors;
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), self::baseBehaviors(), []);
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'storage_engine';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['data'], 'string'],
            [['created', 'modified'], 'safe'],
            [['id'], 'string', 'max' => 36],
            [['system_id'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'system_id' => 'Handler',
            'data' => 'Data',
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
     * Get storage handler.
     *
     * @return [[@doctodo return_type:getStorageHandler]] [[@doctodo return_description:getStorageHandler]]
     */
    public function getStorageHandler()
    {
        if (Yii::$app->collectors['storageEngine']->has($this->system_id)) {
            return Yii::$app->collectors['storageEngine']->getOne($this->system_id);
        }

        return false;
    }
}
