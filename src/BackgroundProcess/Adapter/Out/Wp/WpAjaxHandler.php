<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

/**
 * Handler for an AJAX request in WordPress. Extend this class to handle a specific AJAX request.
 */
abstract class WpAjaxHandler
{
    /**
     * @var string
     */
    private $actionName;

    public function __construct(string $actionName)
    {
        $this->actionName = $actionName;

        add_action('wp_ajax_' . $this->actionName, function(): void {
            $this->maybeHandle();
        });
        add_action('wp_ajax_nopriv_' . $this->actionName, function(): void {
            $this->maybeHandle();
        });
    }


    /**
     * Maybe handle the request.
     *
     * Check for the correct nonce and pass to handler.
     */
    protected function maybeHandle(): void
    {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer($this->actionName, 'nonce');

        $this->handle();

        wp_die();
    }


    /**
     * Handle the request.
     *
     * Override this method to perform any actions required
     * during the async request.
     */
    abstract protected function handle(): void;
}
