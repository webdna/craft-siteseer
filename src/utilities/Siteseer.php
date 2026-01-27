<?php

namespace webdna\craftsiteseer\utilities;

use webdna\craftsiteseer\Siteseer as Plugin;
use webdna\craftsiteseer\helpers\SiteseerHelper;

use Craft;
use craft\base\Utility;
use craft\commerce\Plugin as Commerce;
use craft\web\assets\admintable\AdminTableAsset;

/**
 * Hit It utility
 */
class Siteseer extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('siteseer', 'Siteseer');
    }

    static function id(): string
    {
        return 'siteseer';
    }

    public static function icon(): ?string
    {
        return 'wrench';
    }

    public static function badgeCount(): int
    {
        return Plugin::getInstance()->errorService->getErrorCount();
    }

    public static function contentHtml(): string
    {
        //c5
        $sections = Craft::$app->getEntries()->getAllSections();
        // c4
        // $sections = Craft::$app->getSections()->getAllSections();
        $sites = Craft::$app->getSites()->getAllSites();
        $groups = Craft::$app->getCategories()->getAllGroups();

        $isCommerce = false;
        if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            $productTypes = Commerce::getInstance()->getProductTypes()->getAllProductTypes();
            $isCommerce = true;
        }
      
        $view = Craft::$app->getView();
        $view->registerAssetBundle(AdminTableAsset::class);
        $view->registerJs(<<<JS
            var columns = [
                { name: 'id', title: 'ID' },
                { name: 'link', title: 'URL' },
                { name: 'code', title: 'Code' },
                { name: 'site', title: 'Site' },
                { name: 'editUrl', title: 'Edit Url' },
            ];

            new Craft.VueAdminTable({
                columns: columns,
                container: '#error-list-vue-admin-table',
                emptyMessage: 'No Errors found',
                padded: true,
                perPage: 10,
                tableDataEndpoint: Craft.getActionUrl('siteseer/default/get-siteseer-error-table'),
                search: false,
                checkboxes: true,
                deleteAction: Craft.getActionUrl('siteseer/default/delete-siteseer-error'),
                allowMultipleDeletions: true,
                deleteConfirmationMessage: `Are you sure you want to delete this error?`,
                deleteSuccessMessage: 'Error deleted',
                deleteFailMessage: 'Couldn\'t delete error'
            });
        JS);
        return $view->renderTemplate(
            'siteseer/utilities/siteseer.twig',
            [
                'siteOptions' => SiteseerHelper::getCheckboxes($sites),
                'sectionOptions' => SiteseerHelper::getCheckboxes($sections),
                'groupOptions' => SiteseerHelper::getCheckboxes($groups),
                'typeOptions' => $isCommerce ? SiteseerHelper::getCheckboxes($productTypes) : '',
                'manualUrls' => SiteseerHelper::getManualUrlTable(),
                'configUrls' => SiteseerHelper::getConfigManualUrlTable(),
                'includeConfigCheckBox' => SiteseerHelper::getConfigCheckbox(),
                'takeSnapshotCheckBox' => SiteseerHelper::getSnapshotCheckbox(),
                'hasErrors' => Plugin::getInstance()->errorService->getErrorCount()
            ]
        );
    }
}
