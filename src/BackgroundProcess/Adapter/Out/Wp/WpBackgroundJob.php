<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\BackgroundJob;

/**
 * Implements a background job for use with WordPress. Override handle() to
 * process the request.
 */
abstract class WpBackgroundJob extends WpAjaxHandler implements BackgroundJob
{
    /**
     * @var string
     */
    private $actionName;

    /**
     * @var array
     */
    private $jobData;

    public function __construct(string $actionName)
    {
        $this->actionName = $actionName;
        $this->jobData    = [];

        parent::__construct($actionName);
    }


    final public function dispatch(array $data = []): array
    {
        $requestData = array_merge($data, $this->jobData);

        $request = new WpAjaxRequest($this->actionName);
        return $request->dispatch($requestData);
    }


    final public function setJobData(array $data): void
    {
        $this->jobData = $data;
    }
}
