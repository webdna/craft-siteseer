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
use yii\base\InvalidArgumentException;

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
            $manualUrls = [];
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

    /**
     * Turns a list of handles and/or ids (or "*") into the ids of one kind of thing.
     *
     * @param string $kind 'sites', 'sections', 'groups' or 'types'
     * @param array $values handles, numeric ids, or '*' for all
     * @throws InvalidArgumentException naming the first value that matches nothing
     */
    public function resolveIds(string $kind, array $values): array
    {
        $values = array_values(array_filter(
            array_map(static fn($value) => trim((string)$value), $values),
            static fn($value) => $value !== ''
        ));

        if (in_array('*', $values, true)) {
            return match ($kind) {
                'sites' => $this->allSiteIds(),
                'sections' => $this->allSectionIds(),
                'groups' => $this->allGroupIds(),
                'types' => $this->allTypeIds(),
            };
        }

        if ($kind === 'types' && !empty($values) && !Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            throw new InvalidArgumentException('Product types need Commerce, which is not installed.');
        }

        $ids = [];
        foreach ($values as $value) {
            $isId = ctype_digit($value);
            $model = match ($kind) {
                'sites' => $isId ? Craft::$app->getSites()->getSiteById((int)$value) : Craft::$app->getSites()->getSiteByHandle($value),
                'sections' => $isId ? Craft::$app->getEntries()->getSectionById((int)$value) : Craft::$app->getEntries()->getSectionByHandle($value),
                'groups' => $isId ? Craft::$app->getCategories()->getGroupById((int)$value) : Craft::$app->getCategories()->getGroupByHandle($value),
                'types' => $isId ? Commerce::getInstance()->getProductTypes()->getProductTypeById((int)$value) : Commerce::getInstance()->getProductTypes()->getProductTypeByHandle($value),
            };

            if (!$model) {
                $label = ['sites' => 'site', 'sections' => 'section', 'groups' => 'category group', 'types' => 'product type'][$kind];
                throw new InvalidArgumentException("Unknown $label: $value");
            }
            $ids[] = (int)$model->id;
        }

        return array_values(array_unique($ids));
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
            // the utility's editable table posts rows, the console posts strings
            if (is_array($uri)) {
                $uri = $uri['url'] ?? '';
            }
            $uri = trim((string)$uri);
            if ($uri === '') {
                continue;
            }
            $destinations[] = new Destination([
                'elementId' => null,
                'elementType' => null,
                'siteId' => $siteId,
                'url' => UrlHelper::siteUrl($uri, null, $settings->useHttps ? 'https' : null, $siteId),
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
