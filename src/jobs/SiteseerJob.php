<?php

namespace webdna\craftsiteseer\jobs;

use webdna\craftsiteseer\Siteseer;
use webdna\craftsiteseer\batchers\DestinationBatcher;

use craft\helpers\Queue as QueueHelper;
use craft\i18n\Translation;
use craft\queue\BaseBatchedJob;
use webdna\craftsiteseer\models\DestinationList;
use yii\queue\Queue;
use yii\queue\RetryableJobInterface;

/**
 * @since 4.14.0
 */
class SiteseerJob extends BaseBatchedJob implements RetryableJobInterface
{
    /**
     * @var array[]
     */
    public array $destinations;
    public int $siteId;
    public bool $takeSnapshots;

    /**
     * Used to set the progress on the appropriate queue.
     *
     * @see self::setProgressHandler()
     */
    private Queue $queue;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->batchSize = 10;
    }

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        return 3600;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < 2;
    }

    /**
     * Generates the cache for a batch of site URIs in one go.
     */
    public function execute($queue): void
    {
        // Decrement (increase) priority so that subsequent batches are prioritised.
        if ($this->itemOffset === 0 && $this->priority > 0) {
            $this->priority--;
        }

        $this->queue = $queue;

        /** @var array $siteUris */
        $destinations = $this->data()->getSlice($this->itemOffset, $this->batchSize); 
        $destinationList = new DestinationList([
            'siteId' => $this->siteId,
            'destinations' => $destinations
        ]);
        Siteseer::getInstance()->visitService->visit($destinationList, $this->takeSnapshots);
        $this->itemOffset += count($destinations);

        // Spawn another job if there are more items
        if ($this->itemOffset < $this->totalItems()) {
            $nextJob = clone $this;
            $nextJob->batchIndex++;
            QueueHelper::push($nextJob, $this->priority, 0, $this->ttr, $queue);
        }
    }

    /**
     * Handles setting the progress.
     */
    public function setProgressHandler(int $count, int $total, string $label = null): void
    {
        $progress = $total > 0 ? ($count / $total) : 0;

        $this->setProgress($this->queue, $progress, $label);
    }

    /**
     * @inheritdoc
     */
    protected function loadData(): DestinationBatcher
    {
        return new DestinationBatcher($this->destinations);
    }

    /**
     * @inheritdoc
     */
    protected function processItem(mixed $item): void
    {
    }

     /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Translation::prep('siteseer', 'Going on a trip', []);
    }
}