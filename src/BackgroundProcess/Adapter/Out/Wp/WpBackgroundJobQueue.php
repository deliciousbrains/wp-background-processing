<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BackgroundJobQueue;
use stdClass;

/**
 * WP Background Process
 *
 * @package WP-Background-Processing
 */

/**
 * Abstract WP_Background_Process class.
 *
 * @abstract
 *
 * @extends WP_Async_Request
 */
abstract class WpBackgroundJobQueue extends WpAjaxHandler implements BackgroundJobQueue
{
    private $identifier;

    /**
     * Initiate new background process
     */
    public function __construct(string $actionName)
    {
        $this->identifier = $actionName;
        parent::__construct($actionName);

        $this->cron_hook_identifier     = $this->identifier . '_cron';
        $this->cron_interval_identifier = $this->identifier . '_cron_interval';

        add_action($this->cron_hook_identifier, function(): void {
            $this->handleCronHealthcheck();
        });
        add_filter('cron_schedules', function($schedules): void {
            $this->scheduleCronHealthcheck($schedules);
        });
    }


    public function dispatch(array $data = []): array
    {
        // Schedule the cron healthcheck.
        $this->scheduleEvent();

        // Perform remote post.
        $request = new WpAjaxRequest($this->identifier);
        return $request->dispatch($data);
    }


    public function pushToQueue(array $data): BackgroundJobQueue
    {
        $this->data[] = $data;

        return $this;
    }


    public function save(): BackgroundJobQueue
    {
        $key = $this->generateKey();

        if (!empty($this->data))
        {
            update_site_option($key, $this->data);
        }

        return $this;
    }


    public function cancel(): void
    {
        if (!$this->isQueueEmpty())
        {
            $batch = $this->getBatch();

            $this->delete($batch->key);

            wp_clear_scheduled_hook($this->cron_hook_identifier);
        }
    }

    /**
     * Maybe process queue
     *
     * Checks whether data exists within the queue and that
     * the process is not already running.
     */
    protected function maybeHandle(): void
    {
        // Don't lock up other requests while processing
        session_write_close();

        if ($this->is_process_running())
        {
            // Background process already running.
            wp_die();
        }

        if ($this->isQueueEmpty())
        {
            // No data to process.
            wp_die();
        }

        check_ajax_referer($this->identifier, 'nonce');

        $this->handle();

        wp_die();
    }

    /**
     * Handle
     *
     * Pass each queue item to the task handler, while remaining
     * within server memory and time limit constraints.
     */
    final protected function handle(): void
    {
        $this->lockProcess();

        do
        {
            $batch = $this->getBatch();

            foreach ($batch->data as $key => $value)
            {
                $task = $this->handleTask($value);

                if ($task !== false)
                {
                    $batch->data[$key] = $task;
                }
                else
                {
                    unset($batch->data[$key]);
                }

                if ($this->timeExceeded() || $this->memoryExceeded())
                {
                    // Batch limits reached.
                    break;
                }
            }

            // Update or delete current batch.
            if (!empty($batch->data))
            {
                $this->update($batch->key, $batch->data);
            }
            else
            {
                $this->delete($batch->key);
            }
        } while (!$this->timeExceeded() && !$this->memoryExceeded() && !$this->isQueueEmpty());

        $this->unlockProcess();

        // Start next batch or complete process.
        if (!$this->isQueueEmpty())
        {
            $this->dispatch();
        }
        else
        {
            $this->complete();
        }

        wp_die();
    }

    /**
     * Handle an individual queue task.
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    abstract protected function handleTask($item);

    /**
     * Update queue
     *
     * @param string $key  Key.
     * @param array  $data Data.
     */
    private function update(string $key, array $data): void
    {
        if (!empty($data))
        {
            update_site_option($key, $data);
        }
    }

    /**
     * Delete queue
     *
     * @param string $key Key.
     */
    private function delete(string $key): void
    {
        delete_site_option($key);
    }

    /**
     * Schedule cron healthcheck
     *
     * @access public
     *
     * @param mixed $schedules Schedules.
     *
     * @return mixed
     */
    private function scheduleCronHealthcheck($schedules)
    {
        $interval = apply_filters($this->identifier . '_cron_interval', 5);

        if (property_exists($this, 'cron_interval'))
        {
            $interval = apply_filters($this->identifier . '_cron_interval', $this->cron_interval);
        }

        // Adds every 5 minutes to the existing schedules.
        $schedules[$this->identifier . '_cron_interval'] = [
            'interval' => MINUTE_IN_SECONDS * $interval,
            'display'  => sprintf(__('Every %d Minutes'), $interval),
        ];

        return $schedules;
    }

    /**
     * Handle cron healthcheck
     *
     * Restart the background process if not already running
     * and data exists in the queue.
     */
    private function handleCronHealthcheck(): void
    {
        if ($this->is_process_running())
        {
            // Background process already running.
            exit;
        }

        if ($this->isQueueEmpty())
        {
            // No data to process.
            $this->clearScheduledEvent();
            exit;
        }

        $this->handle();

        exit;
    }

    /**
     * Generate key
     *
     * Generates a unique key based on microtime. Queue items are
     * given a unique key so that they can be merged upon save.
     *
     * @param int $length Length.
     */
    private function generateKey(int $length = 64): string
    {
        $unique  = md5(microtime() . rand());
        $prepend = $this->identifier . '_batch_';

        return substr($prepend . $unique, 0, $length);
    }

    /**
     * Is queue empty
     */
    private function isQueueEmpty(): bool
    {
        global $wpdb;

        $table  = $wpdb->options;
        $column = 'option_name';

        if (is_multisite())
        {
            $table  = $wpdb->sitemeta;
            $column = 'meta_key';
        }

        $key = $wpdb->esc_like($this->identifier . '_batch_') . '%';

        $count = $wpdb->get_var($wpdb->prepare("
			SELECT COUNT(*)
			FROM {$table}
			WHERE {$column} LIKE %s
		", $key));

        return $count > 0 ? false : true;
    }

    /**
     * Is process running
     *
     * Check whether the current process is already running
     * in a background process.
     */
    private function is_process_running()
    {
        if (get_site_transient($this->identifier . '_process_lock'))
        {
            // Process already running.
            return true;
        }

        return false;
    }

    /**
     * Lock process
     *
     * Lock the process so that multiple instances can't run simultaneously.
     * Override if applicable, but the duration should be greater than that
     * defined in the time_exceeded() method.
     */
    private function lockProcess(): void
    {
        $this->start_time = time(); // Set start time of current process.

        $lock_duration = property_exists($this, 'queue_lock_time') ? $this->queue_lock_time : 60; // 1 minute
        $lock_duration = apply_filters($this->identifier . '_queue_lock_time', $lock_duration);

        set_site_transient($this->identifier . '_process_lock', microtime(), $lock_duration);
    }

    /**
     * Unlock process
     *
     * Unlock the process so that other instances can spawn.
     *
     * @return $this
     */
    private function unlockProcess()
    {
        delete_site_transient($this->identifier . '_process_lock');

        return $this;
    }

    /**
     * Get batch
     *
     * @return stdClass Return the first batch from the queue
     */
    private function getBatch(): stdClass
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

        $key = $wpdb->esc_like($this->identifier . '_batch_') . '%';

        $query = $wpdb->get_row($wpdb->prepare("
			SELECT *
			FROM {$table}
			WHERE {$column} LIKE %s
			ORDER BY {$key_column} ASC
			LIMIT 1
		", $key));

        $batch       = new stdClass();
        $batch->key  = $query->$column;
        $batch->data = maybe_unserialize($query->$value_column);

        return $batch;
    }

    /**
     * Memory exceeded
     *
     * Ensures the batch process never exceeds 90%
     * of the maximum WordPress memory.
     */
    private function memoryExceeded(): bool
    {
        $memory_limit   = $this->getMemoryLimit() * 0.9; // 90% of max memory
        $current_memory = memory_get_usage(true);
        $return         = false;

        if ($current_memory >= $memory_limit)
        {
            $return = true;
        }

        return apply_filters($this->identifier . '_memory_exceeded', $return);
    }

    /**
     * Get memory limit
     */
    private function getMemoryLimit(): int
    {
        if (function_exists('ini_get'))
        {
            $memory_limit = ini_get('memory_limit');
        }
        else
        {
            // Sensible default.
            $memory_limit = '128M';
        }

        if (!$memory_limit || intval($memory_limit) === - 1)
        {
            // Unlimited, set to 32GB.
            $memory_limit = '32000M';
        }

        return wp_convert_hr_to_bytes($memory_limit);
    }

    /**
     * Time exceeded.
     *
     * Ensures the batch never exceeds a sensible time limit.
     * A timeout limit of 30s is common on shared hosting.
     */
    private function timeExceeded(): bool
    {
        $finish = $this->start_time + apply_filters($this->identifier . '_default_time_limit', 20); // 20 seconds
        $return = false;

        if (time() >= $finish)
        {
            $return = true;
        }

        return apply_filters($this->identifier . '_time_exceeded', $return);
    }

    /**
     * Complete.
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    private function complete(): void
    {
        // Unschedule the cron healthcheck.
        $this->clearScheduledEvent();
    }

    /**
     * Schedule event
     */
    private function scheduleEvent(): void
    {
        if (!wp_next_scheduled($this->cron_hook_identifier))
        {
            wp_schedule_event(time(), $this->cron_interval_identifier, $this->cron_hook_identifier);
        }
    }

    /**
     * Clear scheduled event
     */
    private function clearScheduledEvent(): void
    {
        $timestamp = wp_next_scheduled($this->cron_hook_identifier);

        if ($timestamp)
        {
            wp_unschedule_event($timestamp, $this->cron_hook_identifier);
        }
    }
}
