<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\MySql;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BatchTable;
use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\RepositoryException;
use mysqli;
use mysqli_sql_exception;
use Psr\Log\LoggerInterface;

final class SiteMetaTable implements BatchTable
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
     * @var int
     */
    private $siteId;

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

    public function __construct(LoggerInterface $logger, mysqli $mysqli, string $prefix, int $siteId, string $actionName)
    {
        $this->logger      = $logger;
        $this->mysqli      = $mysqli;
        $this->tableName   = "${prefix}sitemeta";
        $this->siteId      = $siteId;
        $this->batchPrefix = $actionName . '_batch_';
        $this->lockMetaKey = "{$actionName}_lock";
    }


    public function insert(BatchItem $item): void
    {
        $data = serialize($item->value());

        $data = $this->mysqli->escape_string($data);

        $query = "
                INSERT INTO {$this->tableName} (site_id, meta_key, meta_value)
                VALUES ({$this->siteId}, '{$item->key()}', \"${data}\");";

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
			WHERE meta_key LIKE '{$this->batchPrefix}%'
			ORDER BY meta_key ASC";

        $results = $this->mysqli->query($query);
        if ($results === false)
        {
            throw new mysqli_sql_exception('Cannot read batch items');
        }

        $batchItems = [];

        foreach ($results as $result)
        {
            $batchItems[] = new BatchItem($result['meta_key'], maybe_unserialize($result['meta_value']));
        }

        return $batchItems;
    }


    public function delete(BatchItem $item): void
    {
        $query = "
            DELETE FROM {$this->tableName} WHERE meta_key='{$item->key()}'";

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

        $this->mysqli->query('SET SESSION innodb_lock_wait_timeout = 2');

        $this->mysqli->begin_transaction();

        $query = "
            SELECT * FROM {$this->tableName}
            WHERE meta_key = '{$this->lockMetaKey}'
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
            INSERT INTO wp_sitemeta(site_id, meta_key, meta_value) 
            SELECT 1, '{$this->lockMetaKey}', false FROM DUAL
            WHERE NOT EXISTS (
                SELECT * FROM wp_sitemeta
                WHERE meta_key = '{$this->lockMetaKey}'
            );
        ";
        $result = $this->mysqli->query($query);
        if (false === $result)
        {
            throw new RepositoryException(
                'There was an issue creating the process locking row.'
            );
        }
    }
}
