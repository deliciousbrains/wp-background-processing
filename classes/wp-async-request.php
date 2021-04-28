<?php
declare(strict_types=1);

/**
 * WP Async Request
 *
 * @package WP-Background-Processing
 */

use Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out\AsyncRequest;
use Jetty\BackgroundProcessing\BackgroundProcess\Exception\AsyncException;

/**
 * Abstract WP_Async_Request class.
 *
 * @abstract
 */
abstract class WP_Async_Request implements AsyncRequest
{
    /**
     * Prefix
     *
     * (default value: 'wp')
     *
     * @var string
     *
     * @access protected
     */
    protected $prefix = 'wp';

    /**
     * Action
     *
     * (default value: 'async_request')
     *
     * @var string
     *
     * @access protected
     */
    protected $action = 'async_request';

    /**
     * Identifier
     *
     * @var mixed
     *
     * @access protected
     */
    protected $identifier;

    /**
     * Data
     *
     * (default value: array())
     *
     * @var array
     *
     * @access protected
     */
    protected $data = [];

    /**
     * Initiate new async request
     */
    public function __construct()
    {
        $this->identifier = $this->prefix . '_' . $this->action;

        add_action('wp_ajax_' . $this->identifier, [ $this, 'maybe_handle' ]);
        add_action('wp_ajax_nopriv_' . $this->identifier, [ $this, 'maybe_handle' ]);
    }


    public function data(array $data): AsyncRequest
    {
        $this->data = $data;

        return $this;
    }


    public function dispatch(): array
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
    public function maybe_handle(): void
    {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer($this->identifier, 'nonce');

        $this->handle();

        wp_die();
    }

    /**
     * Get query args
     *
     * @return array
     */
    protected function get_query_args(): array
    {
        if (property_exists($this, 'query_args'))
        {
            return $this->query_args;
        }

        $args = [
            'action' => $this->identifier,
            'nonce'  => wp_create_nonce($this->identifier),
        ];

        /**
         * Filters the post arguments used during an async request.
         *
         * @param array $url
         */
        return apply_filters($this->identifier . '_query_args', $args);
    }

    /**
     * Get query URL
     */
    protected function get_query_url(): string
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
        return apply_filters($this->identifier . '_query_url', $url);
    }

    /**
     * Get post args
     *
     * @return array
     */
    protected function get_post_args(): array
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
        return apply_filters($this->identifier . '_post_args', $args);
    }

    /**
     * Handle
     *
     * Override this method to perform any actions required
     * during the async request.
     */
    abstract protected function handle(): void;
}
