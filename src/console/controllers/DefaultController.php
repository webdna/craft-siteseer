<?php

namespace webdna\craftsiteseer\console\controllers;

use craft\console\Controller;
use webdna\craftsiteseer\Siteseer;
use yii\console\ExitCode;

/**
 * default controller
 */
class DefaultController extends Controller
{
    public $defaultAction = 'visit';
    
    /** @var string|array Comma-separated list of section IDs, or "*" for all. Defaults to "*".
     * Example: --sections="1,2,3" or --sections="*"
     */
    public string|array $sections = [];

    /** @var string|array Comma-separated list of category group IDs, or "*" for all. Defaults to "*".
     * Example: --groups="1,2,3" or --groups="*"
     */
    public string|array $groups = [];

    /** @var string|array Comma-separated list of entry type IDs, or "*" for all. Defaults to "*".
     * Example: --types="1,2,3" or --types="*"
     */
    public string|array $types = [];

    /** @var string|array Comma-separated list of manual URIs. Defaults to empty.
     * Example: --manual="contact,about,insights"
     */
    public string|array $manual = [];

    /** @var string|array Comma-separated list of site IDs, or "*" for all. Defaults to "*".
     * Example: --sites="1,2" or --sites="*"
     */
    public string|array $sites = [];

    /** @var bool Include manual URIs from plugin settings. Defaults to false.
     * Example: --includeconfig=1
     */
    public bool $includeconfig = false;

    /** @var bool Take snapshots and clear existing cached snapshots first. Defaults to false.
     * Example: --snapshot=1
     */
    public bool $snapshot = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                // $options[] = '...';
                break;
            case 'visit':
                $options[] = 'sections';
                $options[] = 'groups';
                $options[] = 'types';
                $options[] = 'manual';
                $options[] = 'sites';
                $options[] = 'includeconfig';
                $options[] = 'snapshot';
                break;
        }
        return $options;
    }

    /**
     * siteseer/default/visit
     *
     * Examples:
     *  ./craft siteseer/default/visit --sections="\*" --groups="\*" --types="\*" --sites="\*" --includeconfig=1 --snapshot=1
     *  ./craft siteseer/default/visit --sections="1,2" --types="5" --sites="1" --manual="contact,about"
     */
    public function actionVisit(): int
    {
        $itineraryService = Siteseer::getInstance()->itineraryService;

        $sections = $this->normalizeList($this->sections, true);
        $groups = $this->normalizeList($this->groups, true);
        $types = $this->normalizeList($this->types, true);
        $manual = $this->normalizeList($this->manual);
        $siteIds = $this->normalizeList($this->sites, true);

        $includeConfig = (bool)$this->includeconfig;
        $takeSnapshots = (bool)$this->snapshot;

        if ($sections === '*') {
            $sections = $itineraryService->allSectionIds();
        }
        if ($groups === '*') {
            $groups = $itineraryService->allGroupIds();
        }
        if ($types === '*') {
            $types = $itineraryService->allTypeIds();
        }
        if ($siteIds === '*') {
            $siteIds = $itineraryService->allSiteIds();
        }

        $destinations = Siteseer::getInstance()->itineraryService->allTheUrls(
            $sections,
            $groups,
            $types,
            $manual,
            $siteIds,
            [
                'includeConfig' => $includeConfig
            ]
        );

        if ($takeSnapshots) {
            Siteseer::getInstance()->visitService->deleteAllSnapshots(true);
        }

        if (count($destinations)) {
            foreach ($destinations as $destinationList) {
                Siteseer::getInstance()->visitService->visit($destinationList, $takeSnapshots, true);
            }
        }

        return ExitCode::OK;
    }

    private function normalizeList(string|array $value, bool $defaultToAll = false): array|string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $defaultToAll ? '*' : [];
            }
            if ($trimmed === '*') {
                return '*';
            }
            return array_values(array_filter(array_map('trim', explode(',', $trimmed)), static fn($v) => $v !== ''));
        }

        if (empty($value)) {
            return $defaultToAll ? '*' : [];
        }

        return $value;
    }
}
