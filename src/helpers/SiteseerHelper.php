<?php

namespace webdna\craftsiteseer\helpers;

use craft;
use craft\commerce\models\ProductType;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\CategoryGroup;
use craft\models\Section;
use craft\models\Site;
use webdna\craftsiteseer\Siteseer;

class SiteseerHelper
{
    public static function getCheckboxes(array $options): string
    {
        if (empty($options)) {
            return '';
        }

        // check the first item
        $first = $options[array_key_first($options)];

        if ($first instanceof Section) {
            $name = 'sections';
        } elseif ($first instanceof CategoryGroup) {
            $name = 'groups';
        } elseif ($first instanceof ProductType) {
            $name = 'types';
        } elseif ($first instanceof Site) {
            $name = 'sites';
        } else {
            // can't be sure if will follow the name:id format, lets bail
            return '';
        }

        $fieldOptions = collect($options)->map(function ($option) {
            return [
                'label' => Html::encode($option->name),
                'value' => $option->id
            ];
        })->values()->all();

        $view = Craft::$app->getView();
        return $view->renderTemplate(
            '_includes/forms/checkboxSelect.twig',
            [
                'class' => '',
                'name' => $name,
                'options' => $fieldOptions,
                'showAllOption' => true,
                'values' => '*'
            ]
        );
    }

    public static function getConfigCheckbox(): string {
        $view = Craft::$app->getView();
        return $view->renderTemplate(
            '_includes/forms/checkbox.twig',
            [
                'class' => '',
                'name' => 'includeconfig',
                'label' => Craft::t('siteseer','Include Urls from config'),
                'checked' => true
            ]
        );
    }

    public static function getManualUrlTable(): string
    {
        $view = Craft::$app->getView();
        return $view->renderTemplate(
            '_includes/forms/editableTable.twig',
            [
                'name' => 'manual',
                'id' => 'manual-urls',
                'staticRows' => false,
                'allowAdd' => true,
                'allowDelete' => true,
                'allowReorder' => false,
                'addRowLabel' => Craft::t('siteseer', 'Add static URLs'),
                'cols' => [
                    'url' => [
                        'heading' => Craft::t('siteseer', 'URL'),
                        'type' => 'url'
                    ]
                ],
                'rows' => []
            ]
        );
    }

    public static function getConfigManualUrlTable(): string
    {
        $view = Craft::$app->getView();
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        return $view->renderTemplate(
            '_includes/forms/editableTable.twig',
            [
                'name' => 'config',
                'id' => 'config-urls',
                'staticRows' => true,
                'allowAdd' => false,
                'allowDelete' => false,
                'allowReorder' => false,
                'static' => true,
                'cols' => [
                    'url' => [
                        'heading' => Craft::t('siteseer', 'URL'),
                        'type' => 'url'
                    ]
                ],
                'rows' => collect(Siteseer::getInstance()->getSettings()->manualUrls)->map( function ($url) {
                    return [
                        'url' => UrlHelper::siteUrl($url)
                    ];
                })->values()->all()
            ]
        );
    }

    public static function getSnapshotCheckbox(): string
    {
        $view = Craft::$app->getView();
        return $view->renderTemplate(
            '_includes/forms/checkbox.twig',
            [
                'class' => '',
                'name' => 'snapshot',
                'label' => Craft::t('siteseer','Take a snapshot of visited sites'),
                'instructions' => 'Only possible in a "dev" environment',
                'checked' => false
            ]
        );
    }

}