<?php

namespace webdna\craftsiteseer\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as Commerce;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use Illuminate\Support\Collection;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Set;
use webdna\craftsiteseer\models\Destination;
use webdna\craftsiteseer\models\DestinationList;
use webdna\craftsiteseer\Siteseer;
use yii\base\Component;

/**
 * Itinerary service
 * 
 * This service contains all the functions required
 * to put together the destinations of our trip.
 */
class ItineraryService extends Component
{
    public function allTheUrls(array|string $sections=[], array|string $groups=[], array|string $types=[], array|string $manualUris=[], array $siteIds=[], array $options = []): array
    {
        if (empty($siteIds)) {
            return [];
        }

        $siteDestinations = [];
        
        foreach ($siteIds as $siteId) {
            $entryUrls = [];
            $categoryUrls = [];
            $productUrls = [];
            $manualUris = [];
            if (is_array($sections)) {
                $entryUrls = $this->entryUrls($sections, $siteId, $options);
            }
            if (is_array($groups)) {
                $categoryUrls = $this->categoryUrls($groups, $siteId, $options);
            }
            if (is_array($types)) {
                $productUrls = $this->productUrls($types, $siteId, $options);
            }
            if (is_array($manualUris)) {
                $manualUrls = $this->manualUrls($manualUris, $siteId, $options);
            }
            
            $destinations = ArrayHelper::merge($entryUrls,$categoryUrls,$productUrls,$manualUrls);
            $destinationList = new DestinationList([
                'siteId' => $siteId,
                'destinations' => $destinations
            ]);
            $siteDestinations[] = $destinationList;
        }

        // add an event to allow plugins to register their own urls

        return $siteDestinations;
    } 

    public function allSectionIds(): array
    {
        // c5
        return Craft::$app->getEntries()->getAllSectionIds();
        // c4
        // return Craft::$app->getSections()->getAllSectionIds();
    }

    public function allGroupIds(): array
    {
        return Craft::$app->getCategories()->getAllGroupIds();
    }

    public function allTypeIds(): array
    {
        if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            return Commerce::getInstance()->getProductTypes()->getAllProductTypeIds();
        }
        return [];
    }

    public function allSiteIds(): array
    {
        return Craft::$app->getSites()->getAllSiteIds();
    }

    public function entryUrls(array $sectionIds=[], int|null $siteId=null, array $options = []): array
    {
        if (empty($sectionIds)) {
            return [];
        }

        if (!$siteId) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }
        $entries = Entry::find()->sectionId($sectionIds)->siteId($siteId)->collect();
        $destinations = $this->_createDestinations($entries);
        
        return $destinations;
    }

    public function categoryUrls(array $groupIds=[], int|null $siteId=null, array $options = []): array
    {
        if (empty($groupIds)) {
            return [];
        }

        if (!$siteId) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }
        $categories = Category::find()->groupId($groupIds)->siteId($siteId)->collect();
        $destinations = $this->_createDestinations($categories);

        return $destinations;
    }

    public function productUrls(array $typeIds=[], int|null $siteId=null, array $options = []): array
    {
        $destinations = [];
        if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            if (empty($typeIds)) {
                return [];
            }
            if (!$siteId) {
                $siteId = Craft::$app->getSites()->getPrimarySite()->id;
            }
            $products = Product::find()->typeId($typeIds)->siteId($siteId)->collect();
            $destinations = $this->_createDestinations($products);
        }

        return $destinations;
    }

    public function manualUrls(array $manualUris=[], int|null $siteId=null, array $options = []): array
    {
        $settings = Siteseer::getInstance()->getSettings();
        $configUris = [];
        if (array_key_exists('includeConfig', $options) && $options['includeConfig'] === true) {
            $configUris = $settings->manualUris;
        }

        $uris = ArrayHelper::merge(
            $manualUris,
            $configUris
        );

        $destinations = [];
        foreach ($uris as $uri) {
            $destinations[] = new Destination([
                'elementId' => null,
                'elementType' => null,
                'siteId' => $siteId,
                'url' => UrlHelper::siteUrl($uri, null, null, $siteId),
                'editUrl' => null
            ]);
        }
        return $destinations;
    }

    private function _createDestinations(array|Collection $elements): array
    {
        $destinations = [];
        foreach ($elements as $element) {
            if ($element->url && !ElementHelper::isDraftOrRevision($element)) {
                $destinations[] = new Destination([
                    'elementId' => $element->id,
                    'elementType' => get_class($element),
                    'siteId' => $element->siteId,
                    'url' => $element->url,
                    'editUrl' => $element->getCpEditUrl()
                ]);
            }
        }

        return $destinations;
    }
}
