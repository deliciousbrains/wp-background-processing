<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Adapter\Out\Wp;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;

/**
 * BatchItem implementation for WordPress meta tables.
 */
final class WpBatchItem implements BatchItem
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var mixed
     */
    private $value;


    public function __construct(string $key, $value)
    {
        $this->key   = $key;
        $this->value = $value;
    }


    /**
     * The key (meta) of the batch item.
     */
    public function key(): string
    {
        return $this->key;
    }


    /**
     * The value of the item to be processed.
     *
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }
}
