<?php

namespace webdna\craftsiteseer\batchers;

use craft\base\Batchable;

/**
 * @since 4.14.0
 */
class DestinationBatcher implements Batchable
{
    public function __construct(
        private array $destinations,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->destinations);
    }

    /**
     * @inheritdoc
     */
    public function getSlice(int $offset, int $limit): iterable
    {
        return array_slice($this->destinations, $offset, $limit);
    }
}