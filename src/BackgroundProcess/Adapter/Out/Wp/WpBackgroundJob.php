<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\AsyncRequest;

/**
 * Implements a background job for use with WordPress. Override handle() to
 * process the request.
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

        parent::__construct($actionName);
    }


    final public function dispatch(array $data = []): array
    {
        $request = new WpAjaxRequest($this->actionName);
        return $request->dispatch($data);
    }
}
