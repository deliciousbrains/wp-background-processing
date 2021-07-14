<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BackgroundJobQueue;
use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\QueueBatchRepository;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\BackgroundException;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\RepositoryException;
use Psr\Log\LoggerInterface;

/**
 * Defines a background job queue that operates on multiple pieces in the
 * background.
 */
abstract class WpBackgroundJobQueue extends WpAjaxHandler implements BackgroundJobQueue
{
    /**
     * @var string
     */
    private $identifier;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var QueueBatchRepository
     */
    private $batchRepository;

    /**
     * @var int
     */
    private $start_time = 0;

    /**
     * Initiate new background process
     */
    public function __construct(string $actionName, QueueBatchRepository $batchRepository, LoggerInterface $logger)
    {
        $this->identifier = $actionName;
        parent::__construct($actionName);

        $this->logger = $logger;

        $this->cron_hook_identifier     = $this->identifier . '_cron';
        $this->cron_interval_identifier = $this->identifier . '_cron_interval';

        add_action($this->cron_hook_identifier, function(): void {
            $this->handleCronHealthcheck();
        });
        add_filter('cron_schedules', function($schedules) {
            return $this->scheduleCronHealthcheck($schedules);
        });

        $this->batchRepository = $batchRepository;
    }


    final public function dispatch(array $data = []): array
    {
        $this->logger->debug('Starting background process.');

        // Schedule the cron healthcheck.
        $this->scheduleEvent();

        // Perform remote post.
        $request = new WpAjaxRequest($this->identifier);
        return $request->dispatch($data);
    }


    final public function pushToQueue(array $data): BackgroundJobQueue
    {
        try
        {
            $this->batchRepository->createBatchItem($data);
            $this->logger->debug(
                'Item pushed to queue.',
                ['data' => $data]
            );
        }
        catch (RepositoryException $exception)
        {
            throw new BackgroundException(
                'Could not push item to background job queue.',
                0,
                $exception
            );
        }

        return $this;
    }


    final public function save(): BackgroundJobQueue
    {
        try
        {
            $this->batchRepository->persist();
        }
        catch (RepositoryException $exception)
        {
            throw new BackgroundException(
                'Could not save background job queue.',
            0,
            $exception
            );
        }

        return $this;
    }


    final public function cancel(): void
    {
        try
        {
            if (!$this->isQueueEmpty())
            {
                $batch = $this->batchRepository->readBatchItems();

                foreach ($batch as $item)
                {
                    $this->batchRepository->deleteBatchItem($item);
                }

                wp_clear_scheduled_hook($this->cron_hook_identifier);
            }
        }
        catch (RepositoryException $exception)
        {
            throw new BackgroundException(
                'An error occurred while trying to cancel a background process.',
                0,
                $exception
            );
        }
    }


    /**
     * Maybe process queue
     *
     * Checks whether data exists within the queue and that
     * the process is not already running.
     */
    final protected function maybeHandle(): void
    {
        // Don't lock up other requests while processing
        session_write_close();

        if (!$this->batchRepository->tryGetLock())
        {
            $this->logger->debug('Process is already running... Exiting.');
            // Background process already running.
            wp_die();
        }

        if ($this->isQueueEmpty())
        {
            $this->logger->debug('Process has no data');
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
        $this->start_time = time(); // Set start time of current process.
        try
        {
            $items = $this->batchRepository->readBatchItems();
            $this->logger->debug(
                'Processing batch items.',
                ['items' => $items]
            );

            $currentItem = 0;

            while (!$this->timeExceeded() && !$this->memoryExceeded() && count($items) > $currentItem)
            {
                $item = $items[$currentItem];

                $this->handleTask($item->value());

                $this->batchRepository->deleteBatchItem($item);

                if ($this->timeExceeded() || $this->memoryExceeded())
                {
                    // Batch limits reached.
                    break;
                }

                $currentItem++;
            }

            $this->batchRepository->persist();

            $this->logger->debug('Ending process.');

            // Start next batch or complete process.
            if (!$this->isQueueEmpty())
            {
                $this->logger->debug('Queue is not empty, starting another process.');
                $this->dispatch();
            }
            else
            {
                $this->logger->debug('Queue is empty and we are done!');
                $this->complete();
            }
        }
        catch (RepositoryException $exception)
        {
            $this->logger->critical(
                'Could not process queue.',
                ['exception' => $exception]
            );
        }
        finally
        {
            wp_die();
        }
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
        if ($this->isProcessRunning())
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
     * Is queue empty
     */
    private function isQueueEmpty(): bool
    {
        try
        {
            return !$this->batchRepository->batchItemsExist();
        }
        catch (RepositoryException $exception)
        {
            $this->logger->critical(
                'Could not determine if background job queue has any items.',
                ['exception' => $exception]
            );
        }

        return true;
    }


    /**
     * Is process running
     *
     * Check whether the current process is already running
     * in a background process.
     */
    private function isProcessRunning(): bool
    {
        if (get_site_transient($this->identifier . '_process_lock'))
        {
            // Process already running.
            return true;
        }

        return false;
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
