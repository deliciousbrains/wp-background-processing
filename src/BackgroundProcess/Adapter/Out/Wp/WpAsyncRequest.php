<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

/**
 * WP Async Request
 *
 * @package WP-Background-Processing
 */


use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BackgroundJob;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\AsyncException;

/**
 * Abstract WP_Async_Request class.
 *
 * @abstract
 */
abstract class WpAsyncRequest extends BackgroundJob
{
    public function __construct(string $actionName)
    {
        parent::__construct(`{$actionName}_AsyncRequest`);

        add_action('wp_ajax_' . $this->actionName(), function() {
            $this->maybe_handle();
        });
        add_action('wp_ajax_nopriv_' . $this->actionName(), function() {
            $this->maybe_handle();
        });
    }


    final public function dispatch(): array
    {
        $url  = add_query_arg($this->get_query_args(), $this->get_query_url());
        $args = $this->get_post_args();

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
     * Maybe handle
     *
     * Check for correct nonce and pass to handler.
     */
    private function maybe_handle(): void
    {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer($this->actionName(), 'nonce');

        $this->handle();

        wp_die();
    }


    /**
     * Get query args
     *
     * @return array
     */
    private function get_query_args(): array
    {
        if (property_exists($this, 'query_args'))
        {
            return $this->query_args;
        }

        $args = [
            'action' => $this->actionName(),
            'nonce'  => wp_create_nonce($this->actionName()),
        ];

        /**
         * Filters the post arguments used during an async request.
         *
         * @param array $url
         */
        return apply_filters($this->actionName() . '_query_args', $args);
    }


    /**
     * Get query URL
     */
    private function get_query_url(): string
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
        return apply_filters($this->actionName() . '_query_url', $url);
    }


    /**
     * Get post args
     *
     * @return array
     */
    private function get_post_args(): array
    {
        if (property_exists($this, 'post_args'))
        {
            return $this->post_args;
        }

        $args = [
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => $this->data,
            'cookies'   => $_COOKIE,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ];

        /**
         * Filters the post arguments used during an async request.
         *
         * @param array $args
         */
        return apply_filters($this->actionName() . '_post_args', $args);
    }
}
