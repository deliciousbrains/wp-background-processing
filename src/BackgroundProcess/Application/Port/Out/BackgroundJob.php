<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

/**
 * Describes a background job.
 */
interface BackgroundJob extends AsyncRequest
{
    /**
     * Sets the data to be used by the background job.
     *
     * @param array $data The data needed to process the background job.
     */
    public function setJobData(array $data): void;
}
