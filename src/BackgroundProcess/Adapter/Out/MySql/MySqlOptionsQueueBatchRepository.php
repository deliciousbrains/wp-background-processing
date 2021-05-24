<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\MySql;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BatchTable;
use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\QueueBatchRepository;
use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\RepositoryException;
use Psr\Log\LoggerInterface;

/**
 * Batch repository implemented using mysqli.
 */
final class MySqlOptionsQueueBatchRepository implements QueueBatchRepository
{

    /** @var string The queued data prefix */
    private $batchPrefix;

    /** @var BatchTable The table (to be removed) to lock and queue data in */
    private $batchTable;

    /** @var string The meta key for locking */
    private $lockMetaKey;

    /** @var LoggerInterface Implement to log errors */
    private $logger;

    /** @var \mysqli Connection for MySQL database */
    private $mysqli;

    /** @var string The table name */
    private $tableName;


    /**
     * MySqlBatchItemRepository constructor.
     *
     * @param LoggerInterface $logger Implementation to log errors
     * @param \mysqli $mysqli Connection for MySQL database
     * @param string $tablePrefix The database prefix
     * @param BatchTable $table The BatchTable instance
     * @param string $actionName The background job definition name
     */
    public function __construct(
        LoggerInterface $logger,
        \mysqli $mysqli,
        string $tablePrefix,
        BatchTable $table,
        string $actionName
    ) {
        $this->batchPrefix = $actionName . '_batch_';
        $this->batchTable  = $table;
        $this->lockMetaKey = "lock_{$actionName}";
        $this->logger      = $logger;
        $this->mysqli      = $mysqli;
        $this->tableName   = "${tablePrefix}options";
    }


    /**
     * {@inheritdoc}
     */
    public function createBatchItem($value): QueueBatchRepository
    {
        // Prepare key and value for insertion
        $key  = $this->generateKey();
        $data = $this->mysqli->escape_string(
            serialize($value)
        );

        // Execute query
        $query = "
            INSERT INTO {$this->tableName} (option_name, option_value, autoload)
            VALUES ('${key}', '${data}', 'no');
        ";
        $result = $this->mysqli->query($query);
        if ($result === false)
        {
            throw new RepositoryException(
                'Could not insert a background job item into the wp_options queue.',
                0,
                new \mysqli_sql_exception($this->mysqli->error)
            );
        }

        return $this;
    }


    public function batchItemsExist(): bool
    {
        return $this->batchTable->hasItems();
    }


    /**
     * {@inheritdoc}
     */
    public function readBatchItems(): array
    {
        return $this->batchTable->readAll();
    }


    public function deleteBatchItem(BatchItem $item): QueueBatchRepository
    {
        $this->batchTable->delete($item);

        return $this;
    }


    public function persist(): QueueBatchRepository
    {
        $this->batchTable->persist();

        return $this;
    }


    public function tryGetLock(): bool
    {
        return $this->batchTable->tryGetLock();
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
}
