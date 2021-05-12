<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\MySql;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BatchTable;
use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\RepositoryException;
use mysqli;
use mysqli_sql_exception;
use Psr\Log\LoggerInterface;

final class OptionsTable implements BatchTable
{
    /**
     * @var mysqli
     */
    private $mysqli;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $batchPrefix;

    /**
     * @var string
     */
    private $lockMetaKey;

    /**
     * @var LoggerInterface Logs messages
     */
    private $logger;

    public function __construct(LoggerInterface $logger, mysqli $mysqli, string $prefix, string $actionName)
    {
        $this->logger      = $logger;
        $this->mysqli      = $mysqli;
        $this->tableName   = "${prefix}options";
        $this->batchPrefix = $actionName . '_batch_';
        $this->lockMetaKey = "lock_{$actionName}";
    }


    public function insert(BatchItem $item): void
    {
        $data = serialize($item->value());

        $data = $this->mysqli->escape_string($data);

        $query = "
                INSERT INTO {$this->tableName} (option_name, option_value, autoload)
                VALUES ('{$item->key()}', \"${data}\", 'no');";

        $result = $this->mysqli->query($query);

        if ($result === false)
        {
            throw new mysqli_sql_exception($this->mysqli->error);
        }
    }


    public function readAll(): array
    {
        $query = "
			SELECT *
			FROM {$this->tableName}
			WHERE option_name LIKE '{$this->batchPrefix}%'
			ORDER BY option_name ASC";

        $results = $this->mysqli->query($query);
        if ($results === false)
        {
            throw new mysqli_sql_exception('Cannot read batch items');
        }

        $batchItems = [];

        foreach ($results as $result)
        {
            $batchItems[] = new BatchItem($result['option_name'], maybe_unserialize($result['option_value']));
        }

        return $batchItems;
    }


    public function delete(BatchItem $item): void
    {
        $query = "
            DELETE FROM {$this->tableName} WHERE option_name='{$item->key()}'";

        $result = $this->mysqli->query($query);

        if ($result === false)
        {
            throw new mysqli_sql_exception('Could not delete item');
        }
    }


    public function persist(): void
    {
        $result = $this->mysqli->commit();
        if ($result === false)
        {
            throw new mysqli_sql_exception('Could not commit changes');
        }
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


    public function hasItems(): bool
    {
        return count($this->readAll()) > 0;
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
}
