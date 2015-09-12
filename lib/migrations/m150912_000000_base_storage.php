<?php

namespace canis\db\migrations;

class m131021_005748_base_canis extends \canis\db\Migration
{
    public function up()
    {
        $this->db->createCommand()->checkIntegrity(false)->execute();
        $this->dropExistingTable('storage_engine');

        $this->createTable('storage_engine', [
            'id' => 'char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY',
            'handler' => 'string(255) DEFAULT NULL',
            'data' => 'blob DEFAULT NULL',
            'created' => 'datetime DEFAULT NULL',
            'modified' => 'datetime DEFAULT NULL',
        ]);

        $this->addForeignKey('storageEngineRegistry', 'storage_engine', 'id', 'registry', 'id', 'CASCADE', 'CASCADE');

        $this->dropExistingTable('storage');

        $this->createTable('storage', [
            'id' => 'char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY',
            'storage_engine_id' => 'char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            'storage_key' => 'string(255) DEFAULT NULL',
            'file_name' => 'string(255) DEFAULT NULL',
            'type' => 'string(100) DEFAULT NULL',
            'size' => 'integer(11) unsigned DEFAULT NULL',
            'created' => 'datetime DEFAULT NULL',
            'modified' => 'datetime DEFAULT NULL',
        ]);

        $this->createIndex('storageStorageEngine', 'storage', 'storage_engine_id', false);
        $this->addForeignKey('storageRegistry', 'storage', 'id', 'registry', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('storageStorageEngine', 'storage', 'storage_engine_id', 'storage_engine', 'id', 'CASCADE', 'CASCADE');
        
        $this->db->createCommand()->checkIntegrity(true)->execute();
    }

    public function down()
    {
        $this->db->createCommand()->checkIntegrity(false)->execute();

        $this->dropExistingTable('storage');
        $this->dropExistingTable('storage_engine');

        $this->db->createCommand()->checkIntegrity(true)->execute();

        return true;
    }
}
