<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Exception\AsyncException;

/**
 * Describes the minimum functionality needed for an async request.
 */
interface AsyncRequest
{
    /**
     * Sets the data that is sent and received on both sides of the request.
     *
     * @param array $data Data.
     */
    public function data(array $data): AsyncRequest;


    /**
     * Dispatch the async request.
     *
     * @return array<string, string>
     *
     * @throws AsyncException
     */
    public function dispatch(): array;
}
