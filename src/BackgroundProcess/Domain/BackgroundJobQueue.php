<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Domain;

/**
 * Describes the minimum functionality needed for an async request.
 */
interface BackgroundJobQueue extends AsyncRequest
{
    /**
     * Push an array of job data to the queue.
     *
     * @param array $data Data.
     */
    public function pushToQueue(array $data): BackgroundJobQueue;


    /**
     * Save the queue to be processed later.
     */
    public function save(): BackgroundJobQueue;


    /**
     * Cancel the background process.
     */
    public function cancel(): void;
}
