<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Exception\BackgroundException;

/**
 * Describes the minimum functionality needed for an async request.
 */
interface AsyncRequest
{
    /**
     * Dispatch the async request.
     *
     * @return array<string, string>
     *
     * @throws BackgroundException
     */
    public function dispatch(array $data = []): array;
}
