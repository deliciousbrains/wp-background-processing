<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\MySql;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BatchTable;
use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\QueueBatchRepository;
use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;

/**
 * Batch repository implemented using mysqli.
 */
final class MySqlBatchItemRepository implements QueueBatchRepository
{
    /**
     * @var BatchTable
     */
    private $batchTable;

    /**
     * @var string
     */
    private $batchPrefix;

    public function __construct(BatchTable $table, string $actionName)
    {
        $this->batchPrefix = $actionName . '_batch_';
        $this->batchTable = $table;
    }


    /**
     * @inheritDoc
     */
    public function createBatchItem($value): QueueBatchRepository
    {
        $key = $this->generateKey();

        $item = new BatchItem($key, $value);

        $this->batchTable->insert($item);

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function batchItemsExist(): bool
    {
        return $this->batchTable->hasItems();
    }


    /**
     * @inheritDoc
     */
    public function readBatchItems(): array
    {
        return $this->batchTable->readAll();
    }


    /**
     * @inheritDoc
     */
    public function deleteBatchItem(BatchItem $item): QueueBatchRepository
    {
        $this->batchTable->delete($item);

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function persist(): QueueBatchRepository
    {
        $this->batchTable->persist();

        return $this;
    }


    /**
     * Generates a unique key based on microtime. BatchItems are
     * given a unique key to save to the database.
     */
    private function generateKey(): string
    {
        $length = 32;
        $unique = md5(microtime() . rand());
        $unique = substr($unique, 0, $length);
        return $this->batchPrefix . $unique;
    }


    public function tryGetLock(): bool
    {
        return $this->batchTable->tryGetLock();
    }
}
