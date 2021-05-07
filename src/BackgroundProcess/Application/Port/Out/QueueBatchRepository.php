<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\RepositoryException;

/**
 * Defines a repository to hold BatchItems for use with background job queues.
 */
interface QueueBatchRepository
{
    /**
     * Creates a new BatchItem that will be processed in a background job queue.
     *
     * @param $value mixed The value to store.
     *
     * @throws RepositoryException
     */
    public function createBatchItem($value): QueueBatchRepository;


    /**
     * Check to see if any batch items currently exist.
     *
     * @throws RepositoryException
     */
    public function batchItemsExist(): bool;


    /**
     * Reads and returns all available BatchItems.
     *
     * @throws RepositoryException
     *
     * @return array<BatchItem>
     */
    public function readBatchItems(): array;


    /**
     * Removes a BatchItem.
     *
     * @param BatchItem $item The BatchItem to remove.
     *
     * @throws RepositoryException
     */
    public function deleteBatchItem(BatchItem $item): QueueBatchRepository;


    public function tryGetLock(): bool;


    /**
     * Persists any repository changes made in memory.
     *
     * @throws RepositoryException
     */
    public function persist(): QueueBatchRepository;
}
