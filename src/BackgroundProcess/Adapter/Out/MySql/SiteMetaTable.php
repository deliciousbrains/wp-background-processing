<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\MySql;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BatchTable;
use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;
use mysqli;
use mysqli_sql_exception;

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

    public function __construct(mysqli $mysqli, string $prefix, int $siteId, string $actionName)
    {
        $this->mysqli      = $mysqli;
        $this->tableName   = "${prefix}sitemeta";
        $this->siteId      = $siteId;
        $this->batchPrefix = $this->batchPrefix = $actionName . '_batch_';
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
        $this->mysqli->query('SET SESSION innodb_lock_wait_timeout = 2');

        $this->mysqli->begin_transaction();

        $query = "
            SELECT * FROM {$this->tableName}
            WHERE meta_key = '{$this->getLockMetaKey()}'
            FOR UPDATE";

        $result = $this->mysqli->query($query);

        return $result !== false;
    }


    public function hasItems(): bool
    {
        return count($this->readAll()) > 0;
    }


    /**
     * Retrieves the meta_key for locking
     */
    private function getLockMetaKey(): string
    {
        return "{$this->batchPrefix}lock";
    }
}
