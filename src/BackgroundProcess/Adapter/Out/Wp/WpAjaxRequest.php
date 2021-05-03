<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\AsyncRequest;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\AsyncException;

/**
 * Defines an AJAX request in WordPress.
 */
final class WpAjaxRequest implements AsyncRequest
{
    /**
     * @var string
     */
    private $actionName;


    public function __construct(string $actionName)
    {
        $this->actionName = $actionName;
    }


    public function dispatch(array $data = []): array
    {
        $url  = add_query_arg($this->generateQueryArguments(), $this->generateQueryUrl());
        $args = $this->generatePostArguments($data);

        $value = wp_remote_post(esc_url_raw($url), $args);

        if ($value instanceof WP_Error)
        {
            throw new AsyncException(
                $value->get_error_message()
            );
        }

        return $value;
    }


    /**
     * Get query args
     *
     * @return array
     */
    private function generateQueryArguments(): array
    {
        if (property_exists($this, 'query_args'))
        {
            return $this->query_args;
        }

        $args = [
            'action' => $this->actionName,
            'nonce'  => wp_create_nonce($this->actionName),
        ];

        /**
         * Filters the post arguments used during an async request.
         *
         * @param array $url
         */
        return apply_filters($this->actionName . '_query_args', $args);
    }


    /**
     * Get query URL
     */
    private function generateQueryUrl(): string
    {
        if (property_exists($this, 'query_url'))
        {
            return $this->query_url;
        }

        $url = admin_url('admin-ajax.php');

        /**
         * Filters the post arguments used during an async request.
         *
         * @param string $url
         */
        return apply_filters($this->actionName . '_query_url', $url);
    }


    /**
     * Get post args
     *
     * @param array $data The data, if any, to include in the request.
     *
     * @return array
     */
    private function generatePostArguments(array $data = []): array
    {
        if (property_exists($this, 'post_args'))
        {
            return $this->post_args;
        }

        $args = [
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => $data,
            'cookies'   => $_COOKIE,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ];

        /**
         * Filters the post arguments used during an async request.
         *
         * @param array $args
         */
        return apply_filters($this->actionName . '_post_args', $args);
    }
}
