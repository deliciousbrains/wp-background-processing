<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Domain;

/**
 * A single batch item to be referenced in background processes.
 */
interface BatchItem
{
    /**
     * The key of the BatchItem.
     */
    public function key(): string;


    /**
     * The value of the BatchItem. This data will be processed by a background job queue.
     *
     * @return mixed
     */
    public function value();
}
