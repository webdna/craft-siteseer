<?php

namespace webdna\craftsiteseer\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Place installation code here...
        $this->createTable('{{%siteseer_errors}}', [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer(),
            'siteId' => $this->integer(),
            'url' => $this->text(),
            'code' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createIndex(null, '{{%siteseer_errors}}', ['elementId'], false);
        $this->addForeignKey(null, '{{%siteseer_errors}}', ['elementId'], Table::ELEMENTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%siteseer_errors}}', ['siteId'], Table::SITES, ['id'], 'SET NULL', null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Place uninstallation code here...
        $this->dropAllForeignKeysToTable('{{%siteseer_errors}}');
        $this->dropTableIfExists('{{%siteseer_errors}}');

        return true;
    }
}
