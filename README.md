# WP Background Processing

WP Background Processing can be used to fire off non-blocking asynchronous requests or as a background processing tool, allowing you to queue tasks. Check out the [example plugin](https://github.com/A5hleyRich/wp-background-processing-example) or read the [accompanying article](https://deliciousbrains.com/background-processing-wordpress/).

Inspired by [TechCrunch WP Asynchronous Tasks](https://github.com/techcrunch/wp-async-task).

__Requires PHP 5.6+__

## Install

The recommended way to install this library in your project is by loading it through Composer:

```shell
composer require deliciousbrains/wp-background-processing
```

It is highly recommended to prefix wrap the library class files using [the Mozart package](https://packagist.org/packages/coenjacobs/mozart), to prevent collisions with other projects using this same library.

## Usage

### Async Request

Async requests are useful for pushing slow one-off tasks such as sending emails to a background process. Once the request has been dispatched it will process in the background instantly.

Extend the `WP_Async_Request` class:

```php
class WP_Example_Request extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $prefix = 'my_plugin';

	/**
	 * @var string
	 */
	protected $action = 'example_request';

	/**
	 * Handle a dispatched request.
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		// Actions to perform.
	}

}
```

#### `protected $prefix`

Should be set to a unique prefix associated with your plugin, theme, or site's custom function prefix.

#### `protected $action`

Should be set to a unique name.

#### `protected function handle()`

Should contain any logic to perform during the non-blocking request. The data passed to the request will be accessible via `$_POST`.

#### Dispatching Requests

Instantiate your request:

```php
$this->example_request = new WP_Example_Request();
```

Add data to the request if required:

```php
$this->example_request->data( array( 'value1' => $value1, 'value2' => $value2 ) );
```

Fire off the request:

```php
$this->example_request->dispatch();
```

Chaining is also supported:

```php
$this->example_request->data( array( 'data' => $data ) )->dispatch();
```

### Background Process

Background processes work in a similar fashion to async requests, but they allow you to queue tasks. Items pushed onto the queue will be processed in the background once the queue has been saved and dispatched. Queues will also scale based on available server resources, so higher end servers will process more items per batch. Once a batch has completed, the next batch will start instantly.

Health checks run by default every 5 minutes to ensure the queue is running when queued items exist. If the queue has failed it will be restarted.

Queues work on a first in first out basis, which allows additional items to be pushed to the queue even if it’s already processing. Saving a new batch of queued items and dispatching while another background processing instance is already running will result in the dispatch shortcutting out and the existing instance eventually picking up the new items and processing them when it is their turn.

Extend the `WP_Background_Process` class:

```php
class WP_Example_Process extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $prefix = 'my_plugin';

	/**
	 * @var string
	 */
	protected $action = 'example_process';

	/**
	 * Perform task with queued item.
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		// Actions to perform.

		return false;
	}

	/**
	 * Complete processing.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		// Show notice to user or perform some other arbitrary task...
	}

}
```

#### `protected $prefix`

Should be set to a unique prefix associated with your plugin, theme, or site's custom function prefix.

#### `protected $action`

Should be set to a unique name.

#### `protected function task( $item )`

Should contain any logic to perform on the queued item. Return `false` to remove the item from the queue or return `$item` to push it back onto the queue for further processing. If the item has been modified and is pushed back onto the queue the current state will be saved before the batch is exited.

#### `protected function complete()`

Optionally contain any logic to perform once the queue has completed.

#### Dispatching Processes

Instantiate your process:

```php
$this->example_process = new WP_Example_Process();
```

**Note:** You must instantiate your process unconditionally. All requests should do this, even if nothing is pushed to the queue.

Push items to the queue:

```php
foreach ( $items as $item ) {
    $this->example_process->push_to_queue( $item );
}
```

An item can be any valid PHP value, string, integer, array or object. If needed, the $item is serialized when written to the database.

Save and dispatch the queue:

```php
$this->example_process->save()->dispatch();
```

#### Handling serialized objects in queue items

Queue items that contain non-scalar values are serialized when stored in the database. To avoid potential security issues during unserialize, this library provides the option to set the `allowed_classes` option when calling `unserialize()` which limits which classes can be instantiated. It's kept internally as the protected `$allowed_batch_data_classes` property.

To maintain backward compatibility the default value is `true`, meaning that any serialized object will be instantiated. Please note that this default behavior may change in a future major release.

We encourage all users of this library to take advantage of setting a strict value for `$allowed_batch_data_classes`. If possible, set the value to `false` to disallow any objects from being instantiated, or a very limited list of class names, see examples below.

Objects in the serialized string that are not allowed to be instantiated will instead get the class type `__PHP_Incomplete_Class`.

##### Overriding the default `$allowed_batch_data_classes`

The default behavior can be overridden by passing an array of allowed classes to the constructor:

``` php
$allowed_batch_data_classes = array( MyCustomItem::class, MyItemHelper::class );
$this->example_process = new WP_Example_Process( $allowed_batch_data_classes );
```

Or, set the value to `false`:

``` php
$this->example_process = new WP_Example_Process( false );
```


Another way to change the default is to override the `$allowed_batch_data_classes` property in your process class:

``` php
class WP_Example_Process extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $prefix = 'my_plugin';

	/**
	 * @var string
	 */
	protected $action = 'example_process';

	/**
	 *
	 * @var bool|array
	 */
	protected $allowed_batch_data_classes = array( MyCustomItem::class, MyItemHelper::class );
	...

```

#### Background Process Status

A background process can be queued, processing, paused, cancelled, or none of the above (not started or has completed).

##### Queued

To check whether a background process has queued items use `is_queued()`.

```php
if ( $this->example_process->is_queued() ) {
    // Do something because background process has queued items, e.g. add notice in admin UI.
}
```

##### Processing

To check whether a background process is currently handling a queue of items use `is_processing()`.

```php
if ( $this->example_process->is_processing() ) {
    // Do something because background process is running, e.g. add notice in admin UI.
}
```

##### Paused

You can pause a background process with `pause()`.

```php
$this->example_process->pause();
```

The currently processing batch will continue until it either completes or reaches the time or memory limit. At that point it'll unlock the process and either complete the batch if the queue is empty, or perform a dispatch that will result in the handler removing the healthcheck cron and firing a "paused" action.

To check whether a background process is currently paused use `is_paused()`.

```php
if ( $this->example_process->is_paused() ) {
    // Do something because background process is paused, e.g. add notice in admin UI.
}
```

You can perform an action in response to background processing being paused by handling the "paused" action for the background process's identifier ($prefix + $action).

```php
add_action( 'my_plugin_example_process_paused', function() {
    // Do something because background process is paused, e.g. add notice in admin UI.
});
```

You can resume a background process with `resume()`.

```php
$this->example_process->resume();
```

You can perform an action in response to background processing being resumed by handling the "resumed" action for the background process's identifier ($prefix + $action).

```php
add_action( 'my_plugin_example_process_resumed', function() {
    // Do something because background process is resumed, e.g. add notice in admin UI.
});
```

##### Cancelled

You can cancel a background process with `cancel()`.

```php
$this->example_process->cancel();
```

The currently processing batch will continue until it either completes or reaches the time or memory limit. At that point it'll unlock the process and either complete the batch if the queue is empty, or perform a dispatch that will result in the handler removing the healthcheck cron, deleting all batches of queued items and firing a "cancelled" action.

To check whether a background process is currently cancelled use `is_cancelled()`.

```php
if ( $this->example_process->is_cancelled() ) {
    // Do something because background process is cancelled, e.g. add notice in admin UI.
}
```

You can perform an action in response to background processing being cancelled by handling the "cancelled" action for the background process's identifier ($prefix + $action).

```php
add_action( 'my_plugin_example_process_cancelled', function() {
    // Do something because background process is paused, e.g. add notice in admin UI.
});
```

The "cancelled" action fires once the queue has been cleared down and cancelled status removed. After which `is_cancelled()` will no longer be true as the background process is now dormant.

##### Active

To check whether a background process has queued items, is processing, is paused, or is cancelling, use `is_active()`.

```php
if ( $this->example_process->is_active() ) {
    // Do something because background process is active, e.g. add notice in admin UI.
}
```

If a background process is not active, then it either has not had anything queued yet and not started, or has finished processing all queued items.

### BasicAuth

If your site is behind BasicAuth, both async requests and background processes will fail to complete. This is because WP Background Processing relies on the [WordPress HTTP API](https://developer.wordpress.org/plugins/http-api/), which requires you to attach your BasicAuth credentials to requests. The easiest way to do this is using the following filter:

```php
function wpbp_http_request_args( $r, $url ) {
	$r['headers']['Authorization'] = 'Basic ' . base64_encode( USERNAME . ':' . PASSWORD );

	return $r;
}
add_filter( 'http_request_args', 'wpbp_http_request_args', 10, 2);
```

## Contributing

Contributions are welcome via Pull Requests, but please do raise an issue before
working on anything to discuss the change if there isn't already an issue. If there
is an approved issue you'd like to tackle, please post a comment on it to let people know
you're going to have a go at it so that effort isn't wasted through duplicated work.

### Unit & Style Tests

When working on the library, please add unit tests to the appropriate file in the
`tests` directory that cover your changes.

#### Setting Up

We use the standard WordPress test libraries for running unit tests.

Please run the following command to set up the libraries:

```shell
bin/install-wp-tests.sh db_name db_user db_pass
```

Substitute `db_name`, `db_user` and `db_pass` as appropriate.

Please be aware that running the unit tests is a **destructive operation**, *database
tables will be cleared*, so please use a database name dedicated to running unit tests.
The standard database name usually used by the WordPress community is `wordpress_test`, e.g.

```shell
bin/install-wp-tests.sh wordpress_test root root
```

Please refer to the [Initialize the testing environment locally](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/#3-initialize-the-testing-environment-locally)
section of the WordPress Handbook's [Plugin Integration Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
entry should you run into any issues.

#### Running Unit Tests

To run the unit tests, simply run:

```shell
make test-unit
```

If the `composer` dependencies aren't in place, they'll be automatically installed first.

#### Running Style Tests

It's important that the code in the library use a consistent style to aid in quickly
understanding it, and to avoid some common issues. `PHP_Code_Sniffer` is used with
mostly standard WordPress rules to help check for consistency.

To run the style tests, simply run:

```shell
make test-style
```

If the `composer` dependencies aren't in place, they'll be automatically installed first.

#### Running All Tests

To make things super simple, just run the following to run all tests:

```shell
make
```

If the `composer` dependencies aren't in place, they'll be automatically installed first.

#### Creating a PR

When creating a PR, please make sure to mention which GitHub issue is being resolved
at the top of the description, e.g.:

`Resolves #123`

The unit and style tests will be run automatically, the PR will not be eligible for
merge unless they pass, and the branch is up-to-date with `master`.

## License

[GPLv2+](http://www.gnu.org/licenses/gpl-2.0.html)
