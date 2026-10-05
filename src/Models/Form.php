<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Fields\BlueprintSchema;

class Form extends Model
{
    use HasFactory;

    protected $table = 'sunrice_forms';

    protected $guarded = [];

    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
    ];

    protected static function newFactory(): \Sunrice\Database\Factories\FormFactory
    {
        return \Sunrice\Database\Factories\FormFactory::new();
    }

    /** @return HasMany<FormSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function schema(): BlueprintSchema
    {
        return BlueprintSchema::make($this->fields ?? []);
    }
}
