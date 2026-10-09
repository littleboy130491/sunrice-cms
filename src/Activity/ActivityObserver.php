<?php

declare(strict_types=1);

namespace Sunrice\Activity;

use Illuminate\Database\Eloquent\Model;

/** Passes model events of the logged models to the ActivityLogger. */
class ActivityObserver
{
    public function __construct(protected ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->modelEvent($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->logger->modelEvent($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->logger->modelEvent($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->logger->modelEvent($model, 'restored');
    }

    public function forceDeleted(Model $model): void
    {
        $this->logger->modelEvent($model, 'forceDeleted');
    }
}
