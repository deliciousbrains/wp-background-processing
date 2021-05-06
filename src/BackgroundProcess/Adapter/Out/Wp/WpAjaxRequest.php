<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\AsyncRequest;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\BackgroundException;
use WP_Error;

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
            throw new BackgroundException(
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
        return [
            'action' => $this->actionName,
            'nonce'  => wp_create_nonce($this->actionName),
        ];
    }


    /**
     * Get query URL
     */
    private function generateQueryUrl(): string
    {
        return admin_url('admin-ajax.php');
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
        return [
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => $data,
            'cookies'   => $_COOKIE,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ];
    }
}
