<?php

namespace App\Support\Traits;

use Illuminate\Support\Str;

trait HasSecondaryUuid
{
    /**
     * Boot the trait.
     */
    protected static function bootHasSecondaryUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Find a model by its UUID.
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Find a model by its UUID or throw an exception.
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }
}
