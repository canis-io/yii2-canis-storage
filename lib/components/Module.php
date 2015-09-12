<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\storage\components;

/**
 * Module [[@doctodo class_description:cascade\components\storageHandlers\Module]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
abstract class Module extends \canis\base\CollectorModule
{
    /**
     * @var [[@doctodo var_type:name]] [[@doctodo var_description:name]]
     */
    public $name;
    /**
     * @var [[@doctodo var_type:version]] [[@doctodo var_description:version]]
     */
    public $version = 1;

    /**
     * @inheritdoc
     */
    public function getCollectorName()
    {
        return 'storageEngines';
    }

    /**
     * @inheritdoc
     */
    public function getModuleType()
    {
        return 'Storage';
    }
}
