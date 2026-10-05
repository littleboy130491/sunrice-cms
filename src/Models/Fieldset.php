<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Fields\BlueprintSchema;

class Fieldset extends Model
{
    use HasFactory;

    protected $table = 'sunrice_fieldsets';

    protected $guarded = [];

    protected $casts = ['fields' => 'array'];

    protected static function newFactory(): \Sunrice\Database\Factories\FieldsetFactory
    {
        return \Sunrice\Database\Factories\FieldsetFactory::new();
    }

    public function schema(): BlueprintSchema
    {
        return BlueprintSchema::make($this->fields ?? []);
    }

    /**
     * Memoized lookup used by flexible blocks and fieldset includes.
     */
    public static function schemaForHandle(string $handle): ?BlueprintSchema
    {
        static $cache = [];

        if (! array_key_exists($handle, $cache)) {
            $fieldset = static::query()->where('handle', $handle)->first();
            $cache[$handle] = $fieldset?->schema();
        }

        return $cache[$handle];
    }
}
