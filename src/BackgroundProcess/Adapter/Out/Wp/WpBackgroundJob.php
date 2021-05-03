<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

/**
 * WP Async Request
 *
 * @package WP-Background-Processing
 */

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\AsyncRequest;

/**
 * Abstract WP_Async_Request class.
 *
 * @abstract
 */
abstract class WpBackgroundJob extends WpAjaxHandler implements AsyncRequest
{
    /**
     * @var string
     */
    private $actionName;

    public function __construct(string $actionName)
    {
        $this->actionName = $actionName;
    }


    final public function dispatch(array $data = []): array
    {
        $request = new WpAjaxRequest($this->actionName);
        return $request->dispatch($data);
    }
}
