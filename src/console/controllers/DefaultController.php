<?php

namespace webdna\craftsiteseer\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use webdna\craftsiteseer\Siteseer;
use yii\base\InvalidArgumentException;
use yii\console\ExitCode;

/**
 * Runs the visits the Siteseer utility runs, from the command line.
 */
class DefaultController extends Controller
{
    public $defaultAction = 'visit';

    /** @var array Comma-separated site handles or IDs, or "*". Left out: all sites. */
    public array $sites = [];

    /** @var array Comma-separated entry section handles or IDs, or "*". Left out: all sections. Empty: none. */
    public array $sections = [];

    /** @var array Comma-separated category group handles or IDs, or "*". Left out: all groups. Empty: none. */
    public array $groups = [];

    /** @var array Comma-separated Commerce product type handles or IDs, or "*". Left out: all types. Empty: none. */
    public array $types = [];

    /** @var array Comma-separated URIs to visit on every chosen site, e.g. --manual="contact,about" */
    public array $manual = [];

    /** @var bool Also visit the manualUris from config/siteseer.php. */
    public bool $includeConfig = false;

    /** @var bool The older spelling of --include-config. */
    public bool $includeconfig = false;

    /** @var bool Delete the existing snapshots and take new ones. */
    public bool $snapshot = false;

    /** @var bool Push the visits onto the queue, as the utility's "Run a scan" does, instead of visiting now. */
    public bool $queue = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if ($actionID === 'visit') {
            array_push($options, 'sites', 'sections', 'groups', 'types', 'manual', 'includeConfig', 'includeconfig', 'snapshot', 'queue');
        }
        return $options;
    }

    /**
     * Visits every URL in an itinerary, now or on the queue.
     *
     * Examples:
     *  ./craft siteseer/visit
     *  ./craft siteseer/visit --sections=news,blog --queue
     *  ./craft siteseer/visit --sites=default --sections= --groups= --types= --manual=contact,about --include-config
     */
    public function actionVisit(): int
    {
        $plugin = Siteseer::getInstance();
        $passed = $this->getPassedOptions();

        try {
            $selection = [];
            foreach (['sites', 'sections', 'groups', 'types'] as $kind) {
                // left out means all of them, as the utility ticks every box
                $selection[$kind] = $plugin->itineraryService->resolveIds(
                    $kind,
                    in_array($kind, $passed, true) ? $this->$kind : ['*']
                );
            }
        } catch (InvalidArgumentException $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $manual = array_values(array_filter(array_map('trim', $this->manual), static fn($uri) => $uri !== ''));

        $destinationLists = $plugin->itineraryService->allTheUrls(
            $selection['sections'],
            $selection['groups'],
            $selection['types'],
            $manual,
            $selection['sites'],
            ['includeConfig' => $this->includeConfig || $this->includeconfig]
        );

        $destinationLists = array_filter($destinationLists, static fn($list) => count($list->destinations) > 0);
        $urls = array_sum(array_map(static fn($list) => count($list->destinations), $destinationLists));
        $sites = count($destinationLists);
        $tally = $urls . ($urls === 1 ? ' URL' : ' URLs') . " on $sites " . ($sites === 1 ? 'site' : 'sites');

        if ($urls === 0) {
            $this->stdout('Nothing to visit.' . PHP_EOL);
            return ExitCode::OK;
        }

        if ($this->snapshot) {
            $plugin->visitService->deleteAllSnapshots(!$this->queue);
        }

        if ($this->queue) {
            foreach ($destinationLists as $destinationList) {
                $plugin->visitService->createVisitJobs($destinationList, $this->snapshot);
            }
            $this->stdout("Queued $tally." . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $failures = 0;
        foreach ($destinationLists as $destinationList) {
            $failures += $plugin->visitService->visit($destinationList, $this->snapshot, true);
        }

        $this->stdout("Visited $tally, $failures failed." . PHP_EOL, $failures ? Console::FG_RED : Console::FG_GREEN);

        return $failures ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
