<?php

namespace webdna\craftsiteseer\models;

use Craft;
use craft\base\Model;

/**
 * Error model
 */
class DestinationList extends Model
{
    public ?int $siteId = null;
    public array $destinations = [];

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
