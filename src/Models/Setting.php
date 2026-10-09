<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;
use Sunrice\Events\ContentChanged;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    protected $table = 'sunrice_settings';

    protected $guarded = [];

    protected $casts = ['value' => 'array'];

    /**
     * Read once per request: pages look up the same settings (homepage,
     * site settings) many times while rendering.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = once(fn () => static::query()->where('key', $key)->first()?->value);

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        // Later reads in this request see the new value.
        Once::flush();

        ContentChanged::dispatch('setting_saved');
    }
}
