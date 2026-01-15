<?php

namespace webdna\craftsiteseer\models;

use Craft;
use craft\base\Model;

/**
 * Error model
 */
class Destination extends Model
{
    public ?int $elementId = null;
    public ?string $elementType = '';
    public ?int $siteId = null;
    public string $url = '';
    public ?string $editUrl = '';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
