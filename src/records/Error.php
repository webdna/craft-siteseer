<?php

namespace webdna\craftsiteseer\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * Error record
 */
class Error extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%siteseer_errors}}';
    }
}
