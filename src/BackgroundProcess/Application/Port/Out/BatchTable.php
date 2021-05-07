<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\RepositoryException;

/**
 * Defines a database table used to store BatchItems.
 */
interface BatchTable
{
    /**
     * Inserts a single BatchItem into the table.
     *
     * @param BatchItem $item The item to insert.
     *
     * @throws RepositoryException
     */
    public function insert(BatchItem $item): void;


    /**
     * Tries to get a lock on the table data for processing.
     * If a lock is acquired, this will return true.
     */
    public function tryGetLock(): bool;


    /**
     * Check whether any items exist in the table to be processed.
     *
     * @throws RepositoryException
     */
    public function hasItems(): bool;


    /**
     * Reads all batch items from the table.
     *
     * @throws RepositoryException
     *
     * @return array<BatchItem>
     */
    public function readAll(): array;


    /**
     * Deletes an existing BatchItem from the table.
     *
     * @throws RepositoryException
     *
     * @param BatchItem $item
     */
    public function delete(BatchItem $item): void;


    /**
     * Persists any changes not yet committed to the table.
     *
     * This should always be called after finishing any operations.
     *
     * @throws RepositoryException
     */
    public function persist(): void;
}
