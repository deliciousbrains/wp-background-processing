<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Exception\BackgroundException;

/**
 * Describes the minimum functionality needed for an async request.
 */
interface BackgroundJobQueue extends AsyncRequest
{
    /**
     * Push an array of job data to the queue.
     *
     * @param array $data Data.
     *
     * @throws BackgroundException
     */
    public function pushToQueue(array $data): BackgroundJobQueue;


    /**
     * Save the queue to be processed later.
     *
     * @throws BackgroundException
     */
    public function save(): BackgroundJobQueue;


    /**
     * Cancel the background process.
     *
     * @throws BackgroundException
     */
    public function cancel(): void;
}
