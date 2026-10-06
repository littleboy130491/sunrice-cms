<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\FieldsetFactory;
use Sunrice\Fields\BlueprintSchema;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array<int,array<string,mixed>> $fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<FieldsetFactory>
 */
class Fieldset extends Model
{
    /** @use HasFactory<FieldsetFactory> */
    use HasFactory;

    protected $table = 'sunrice_fieldsets';

    protected $guarded = [];

    protected $casts = ['fields' => 'array'];

    protected static function newFactory(): FieldsetFactory
    {
        return FieldsetFactory::new();
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
