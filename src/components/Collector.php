<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components;
use Yii;
use yii\helpers\ArrayHelper;
/**
 * Collector [[@doctodo class_description:cascade\components\storageHandlers\Collector]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
class Collector extends \canis\base\collector\Module
{
    protected $_tableRegistry;
    /**
     * @var [[@doctodo var_type:_initialItems]] [[@doctodo var_description:_initialItems]]
     */
    protected $_initialItems = [];

    /**
     * @inheritdoc
     */
    public function getCollectorItemClass()
    {
        return Item::className();
    }

    /**
     * @inheritdoc
     */
    public function getModulePrefix()
    {
        return 'Storage';
    }

    /**
     * @inheritdoc
     */
    public function getInitialItems()
    {
        return $this->_initialItems;
    }

    /**
     * Set initial items.
     *
     * @param [[@doctodo param_type:value]] $value [[@doctodo param_description:value]]
     */
    public function setInitialItems($value)
    {
        $this->_initialItems = $value;
    }

    public function getEngineHealth()
    {
        $health = [];
        foreach ($this->getAll() as $engineItem) {
            $health[$engineItem->systemId] = $engineItem->storageHandler->isHealthy();
        }
        return $health;
    }

    /**
     * Get by.
     *
     * @param [[@doctodo param_type:id]] $id [[@doctodo param_description:id]]
     *
     * @return [[@doctodo return_type:getById]] [[@doctodo return_description:getById]]
     */
    public function getById($id)
    {
        foreach ($this->tableRegistry as $entry) {
            if ($entry->primaryKey === $id) {
                $object = $this->getOne($entry->system_id);
                if (isset($object->object)) {
                    return $object;
                }
                break;
            }
        }

        return false;
    }

    /**
     * Get table registry.
     *
     * @return [[@doctodo return_type:getTableRegistry]] [[@doctodo return_description:getTableRegistry]]
     */
    public function getTableRegistry()
    {
        if (is_null($this->_tableRegistry)) {
            $engineClass = Yii::$app->classes['StorageEngine'];
            $this->_tableRegistry = [];
            if ($engineClass::tableExists()) {
                $om = $engineClass::find()->all();
                $this->_tableRegistry = ArrayHelper::index($om, 'system_id');
            }
        }

        return $this->_tableRegistry;
    }
}
