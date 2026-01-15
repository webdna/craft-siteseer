<?php

namespace webdna\craftsiteseer\models;

use Craft;
use craft\base\Model;

/**
 * Error model
 */
class Error extends Model
{
    public int $id;
    public ?int $elementId = null;
    public ?string $elementType = '';
    public ?int $siteId = null;
    public string $url = '';
    public string $code = '';
    public ?string $editUrl = '';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }
}
