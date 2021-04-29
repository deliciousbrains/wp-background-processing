<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Domain;

use Jetty\BackgroundProcessing\BackgroundProcess\Exception\AsyncException;

/**
 * Abstract base class for asynchronous requests in PHP.
 *
 * @abstract
 */
abstract class BackgroundJob implements AsyncRequest
{
    /**
     * The name of the action.
     *
     * @var string
     */
    private $actionName;


    /**
     * Data that is passed across the request.
     *
     * @var array
     */
    private $requestData = [];


    /**
     * Create a new async request with the provided action name.
     *
     * @param string $actionName The action name of the request. This name should be URL-safe.
     */
    public function __construct(string $actionName)
    {
        $this->actionName = $actionName;
    }


    /**
     * @param array $data
     *
     * @return $this
     */
    final public function data(array $data): AsyncRequest
    {
        $this->requestData = $data;

        return $this;
    }


    /**
     * Dispatch the async request.
     *
     * @return array<string, string>
     *
     * @throws AsyncException
     */
    abstract public function dispatch(): array;


    /**
     * The action name of the request.
     */
    final protected function actionName(): string
    {
        return $this->actionName;
    }


    /**
     * The data to be used to make the request.
     *
     * @return array
     */
    final protected function requestData(): array
    {
        return $this->requestData;
    }


    /**
     * Handle
     *
     * Override this method to perform any actions required
     * during the async request.
     */
    abstract protected function handle(): void;
}
