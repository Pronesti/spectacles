<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait HasPublicId
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['external_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public static function bootHasPublicId(): void
    {
        static::updating(function (Model $model): void {
            if ($model->isDirty('external_id')) {
                throw ValidationException::withMessages(['external_id' => __('validation.prohibited', ['attribute' => 'external_id'])]);
            }
        });
    }
}
