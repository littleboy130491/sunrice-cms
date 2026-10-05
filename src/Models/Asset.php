<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Sunrice\Database\Factories\AssetFactory;

/**
 * @property int $id
 * @property int|null $folder_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string|null $mime_type
 * @property int|null $size
 * @property array<string,mixed> $meta
 * @property array<string,string> $sizes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $caption
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<AssetFactory>
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes, \Sunrice\References\HasReferences;

    protected $table = 'sunrice_assets';

    protected $guarded = [];

    protected $casts = [
        'sizes' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'version' => 'integer',
    ];

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    /** @return BelongsTo<AssetFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'folder_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Public URL of the original or a generated size, cache-busted by version.
     */
    public function url(?string $size = null): string
    {
        $path = $this->path;

        if ($size !== null && isset($this->sizes[$size])) {
            $path = $this->sizes[$size];
        }

        $url = Storage::disk($this->disk)->url($path);

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$this->version;
    }

    /**
     * Records (entries, globals, terms...) that use this asset, via the
     * references table.
     *
     * @return Collection<int, array{source_type: string, source_id: int, field_path: ?string}>
     */
    public function usages(): Collection
    {
        return Reference::query()
            ->where('target_type', 'asset')
            ->where('target_id', $this->id)
            ->get(['source_type', 'source_id', 'field_path'])
            ->map(fn (Reference $r) => [
                'source_type' => $r->source_type,
                'source_id' => $r->source_id,
                'field_path' => $r->field_path,
            ]);
    }
}
