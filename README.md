# WP Background Processing

WP Background Processing can be used to fire off non-blocking asynchronous requests or as a background processing tool, allowing you to queue tasks. Check out the [example plugin](https://github.com/A5hleyRich/wp-background-processing-example) or read the [accompanying article](https://deliciousbrains.com/wordpress-background-processing/).

Inspired by [TechCrunch WP Asynchronous Tasks](https://github.com/techcrunch/wp-async-task).

### Async Request

Extend the `WP_Async_Request` class:

```
class WP_Example_Request extends WP_Async_Request {

	/**
	 * @var string
	 */
	protected $action = 'example_request';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		// Actions to perform
	}

}
```

#### `protected $action`

Should be set to a unique name.

#### `protected function handle()`

Should contain any logic to perform during the non-blocking request. The data passed to the request will be accessible via `$_POST`.

#### Dispatching Requests

Instantiate your request:

`$this->example_request = new WP_Example_Request();`

Add data to the request if required:

`$this->example_request->data( array( 'value1' => $value1, 'value2' => $value2 );`

Fire off the request:

`$this->example_request->dispatch();`

Chaining is also supported:

`$this->example_request->data( array( 'data' => $data )->dispatch();`

### Background Process

Extend the `WP_Background_Process` class:

```
class WP_Example_Process extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'example_process';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		// Actions to perform

		return false;
	}

	/**
	 * Complete
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

#### `protected $action`

Should be set to a unique name.

#### `protected function task( $item )`

Should contain any logic to perform on the queued item. Return `false` to remove the item from the queue or return `$item` to push it back onto the queue for further processing. If the item has been modified and is pushed back onto the queue the current state will be saved before the batch is exited.

#### `protected function complete()`

Optionally contain any logic to perform once the queue has completed.

#### Dispatching Processes

Instantiate your request:

`$this->example_process = new WP_Example_Process();`

Push items to the queue:

```
foreach ( $items as $item ) {
    $this->example_process->push_to_queue( $item );
}
```

Save and dispatch the queue:

`$this->example_process->save()->dispatch();`