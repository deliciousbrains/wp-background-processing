<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

/**
 * Describes the minimum functionality needed for an async request.
 */
interface BackgroundProcess extends AsyncRequest
{
    /**
     * Push an array of job data to the queue.
     *
     * @param array $data Data.
     */
    public function push_to_queue(array $data): BackgroundProcess;


    /**
     * Save the queue to be processed later.
     */
    public function save(): BackgroundProcess;


    /**
     * Cancel the background process.
     */
    public function cancel_process(): void;
}
