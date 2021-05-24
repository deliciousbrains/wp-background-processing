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
final class MySqlOptionsQueueRepository implements QueueBatchRepository
{

    /** @var string The queued data prefix */
    private $batchPrefix;

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
     * @param string $actionName The background job definition name
     */
    public function __construct(
        LoggerInterface $logger,
        \mysqli $mysqli,
        string $tablePrefix,
        string $actionName
    ) {
        $this->batchPrefix = $actionName . '_batch_';
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
        return count($this->readBatchItems()) > 0;
    }


    /**
     * {@inheritdoc}
     */
    public function readBatchItems(): array
    {
        $query = "
			SELECT *
			FROM {$this->tableName}
			WHERE option_name LIKE '{$this->batchPrefix}%'
			ORDER BY option_name ASC";

        $results = $this->mysqli->query($query);
        if ($results === false)
        {
            throw new RepositoryException(
                'Cannot read batch items',
                0,
                new \mysqli_sql_exception($this->mysqli->error)
            );
        }

        $batchItems = [];
        foreach ($results as $result)
        {
            $batchItems[] = new BatchItem($result['option_name'], maybe_unserialize($result['option_value']));
        }

        return $batchItems;
    }


    public function deleteBatchItem(BatchItem $item): QueueBatchRepository
    {
        $query = "DELETE FROM {$this->tableName} WHERE option_name='{$item->key()}'";

        $result = $this->mysqli->query($query);

        if ($result === false)
        {
            throw new RepositoryException(
                'Could not delete item.',
                0,
                new \mysqli_sql_exception($this->mysqli->error)
            );
        }

        return $this;
    }


    public function persist(): QueueBatchRepository
    {
        $result = $this->mysqli->commit();
        if ($result === false)
        {
            throw new RepositoryException(
                'Could not commit changes for the background process.',
                0,
                new \mysqli_sql_exception($this->mysqli->error)
            );
        }

        return $this;
    }


    public function tryGetLock(): bool
    {
        $this->mysqli->query('SET SESSION innodb_lock_wait_timeout = 2');

        try
        {
            $this->tryCreateLockRow();
        }
        catch (RepositoryException $repositoryException)
        {
            $this->logger->critical(
                'Could not create the row for locking background processes.',
                [
                    'exception' => $repositoryException
                ]
            );
            return false;
        }

        $this->mysqli->begin_transaction();

        $query = "
            SELECT * FROM {$this->tableName}
            WHERE option_name = '{$this->lockMetaKey}'
            FOR UPDATE";

        $result = $this->mysqli->query($query);

        return $result !== false;
    }


    /**
     * Attempt to create a single row for the process so that a lock can be created
     *
     * @throws RepositoryException If there was an error connecting to the DB to create the row
     */
    private function tryCreateLockRow(): void
    {
        $query = "
            INSERT INTO {$this->tableName}(option_name, option_value, autoload) 
            SELECT '{$this->lockMetaKey}', false, 'no' FROM DUAL
            WHERE NOT EXISTS (
                SELECT * FROM {$this->tableName}
                WHERE option_name = '{$this->lockMetaKey}'
            );
        ";
        $result = $this->mysqli->query($query);
        if (false === $result)
        {
            // The lock already exists, we just timed out
            if ($this->mysqli->errno === 1205)
            {
                return;
            }
            throw new RepositoryException(
                'There was an issue creating the process locking row.'
            );
        }
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
