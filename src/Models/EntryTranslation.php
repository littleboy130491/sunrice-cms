<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Support\Locales;

class EntryTranslation extends Model
{
    use HasFactory;

    protected $table = 'sunrice_entry_translations';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'seo' => 'array',
        'draft' => 'array',
        'is_ready' => 'boolean',
        'content_published_at' => 'datetime',
    ];

    protected static function newFactory(): \Sunrice\Database\Factories\EntryTranslationFactory
    {
        return \Sunrice\Database\Factories\EntryTranslationFactory::new();
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return HasMany<Revision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class)->orderByDesc('created_at');
    }

    /**
     * Whether this translation's live content has unsaved draft changes.
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->draft !== null;
    }

    /**
     * Main language was published more recently than this translation —
     * shown as the "Outdated" badge in the admin. Always false on the
     * main translation itself.
     */
    public function isOutdated(): bool
    {
        if (Locales::isMain($this->locale) || ! $this->is_ready) {
            return false;
        }

        $main = $this->entry?->mainTranslation();

        return $main?->content_published_at !== null
            && $this->content_published_at !== null
            && $main->content_published_at->gt($this->content_published_at);
    }
}
