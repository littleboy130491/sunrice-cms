<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sunrice_assets';

    protected $guarded = [];

    protected $casts = [
        'sizes' => 'array',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'version' => 'integer',
    ];

    protected static function newFactory(): \Sunrice\Database\Factories\AssetFactory
    {
        return \Sunrice\Database\Factories\AssetFactory::new();
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
     * @return \Illuminate\Support\Collection<int, array{source_type: string, source_id: int, field_path: ?string}>
     */
    public function usages(): \Illuminate\Support\Collection
    {
        return Reference::query()
            ->where('target_type', 'asset')
            ->where('target_id', $this->id)
            ->get(['source_type', 'source_id', 'field_path']);
    }
}
