<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\FormFactory;
use Sunrice\Fields\BlueprintSchema;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array<int,array<string,mixed>> $fields
 * @property array<string,mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<FormFactory>
 */
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    protected $table = 'sunrice_forms';

    protected $guarded = [];

    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
    ];

    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
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
