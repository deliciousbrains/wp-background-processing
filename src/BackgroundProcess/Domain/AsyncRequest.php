<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Domain;

use Jetty\BackgroundProcessing\BackgroundProcess\Exception\AsyncException;

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
     * @throws AsyncException
     */
    public function dispatch(array $data = []): array;
}
