<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\QueueBatchRepository;
use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;

/**
 * Repository implementation for WP that stores BatchItems.
 */
final class WpBatchItemRepository implements QueueBatchRepository
{
    /**
     * @var string
     */
    private $batchPrefix;

    public function __construct(string $actionName)
    {
        $this->batchPrefix = $actionName . '_batch_';
    }


    public function createBatchItem($value): QueueBatchRepository
    {
        $key = $this->generateKey();

        update_site_option($key, $value);

        return $this;
    }


    public function batchItemsExist(): bool
    {
        global $wpdb;

        $table  = $wpdb->options;
        $column = 'option_name';

        if (is_multisite())
        {
            $table  = $wpdb->sitemeta;
            $column = 'meta_key';
        }

        $key = $wpdb->esc_like($this->batchPrefix) . '%';

        $count = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(*)
			FROM {$table}
			WHERE {$column} LIKE %s
		", $key));

        return !($count > 0);
    }


    public function readBatchItems(): array
    {
        global $wpdb;

        $table        = $wpdb->options;
        $column       = 'option_name';
        $key_column   = 'option_id';
        $value_column = 'option_value';

        if (is_multisite())
        {
            $table        = $wpdb->sitemeta;
            $column       = 'meta_key';
            $key_column   = 'meta_id';
            $value_column = 'meta_value';
        }

        $key = $wpdb->esc_like($this->batchPrefix) . '%';

        $query = $wpdb->prepare("
			SELECT *
			FROM {$table}
			WHERE {$column} LIKE %s
			ORDER BY {$key_column} ASC
		", $key);

        $batchItems = [];

        $rawResults = $wpdb->get_results($query, ARRAY_A);

        foreach ($rawResults as $result)
        {
            $batchItems[] = new BatchItem($result[$column], maybe_unserialize($result[$value_column]));
        }

        return $batchItems;
    }


    public function deleteBatchItem(BatchItem $item): QueueBatchRepository
    {
        delete_site_option($item->key());
        return $this;
    }


    public function persist(): QueueBatchRepository
    {
        delete_site_transient($this->batchPrefix . 'process_lock');

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
        if (get_site_transient($this->batchPrefix) !== false)
        {
            return false;
        }

        $lock_duration = 60; // 1 minute
        $lock_duration = apply_filters($this->batchPrefix . 'queue_lock_time', $lock_duration);

        set_site_transient($this->batchPrefix . 'process_lock', microtime(), $lock_duration);

        return true;
    }
}
