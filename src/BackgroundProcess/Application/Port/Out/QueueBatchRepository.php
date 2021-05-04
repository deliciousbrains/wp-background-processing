<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;

/**
 * Defines a repository to hold BatchItems for use with background job queues.
 */
interface QueueBatchRepository
{
    /**
     * Creates a new BatchItem that will be processed in a background job queue.
     *
     * @param $value mixed The value to store.
     */
    public function createBatchItem($value): QueueBatchRepository;


    /**
     * Check to see if any batch items currently exist.
     */
    public function batchItemsExist(): bool;


    /**
     * Reads and returns all available BatchItems.
     */
    public function readBatchItems(): array;


    /**
     * Removes a BatchItem.
     *
     * @param BatchItem $item The BatchItem to remove.
     */
    public function deleteBatchItem(BatchItem $item): QueueBatchRepository;


    public function tryGetLock(): bool;


    /**
     * Persists any repository changes made in memory.
     */
    public function persist(): QueueBatchRepository;
}
