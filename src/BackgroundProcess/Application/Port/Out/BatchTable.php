<?php
declare(strict_types=1);

namespace Jetty\BackgroundProcessing\BackgroundProcess\Application\Port\Out;

use Jetty\BackgroundProcessing\BackgroundProcess\Domain\BatchItem;

interface BatchTable
{
    public function insert(BatchItem $item): void;

    public function tryGetLock(): bool;

    public function hasItems(): bool;

    public function readAll(): array;

    public function delete(BatchItem $item): void;

    public function persist(): void;
}
