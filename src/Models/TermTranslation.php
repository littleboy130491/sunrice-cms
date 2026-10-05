<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Sunrice\Database\Factories\TermTranslationFactory;

/**
 * @property int $id
 * @property int $term_id
 * @property int $taxonomy_id
 * @property string $locale
 * @property string $name
 * @property string $slug
 * @property array<string,mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<TermTranslationFactory>
 */
class TermTranslation extends Model
{
    /** @use HasFactory<TermTranslationFactory> */
    use HasFactory;

    protected $table = 'sunrice_term_translations';

    protected $guarded = [];

    protected $attributes = ['data' => '{}'];

    protected $casts = ['data' => 'array'];

    protected static function newFactory(): TermTranslationFactory
    {
        return TermTranslationFactory::new();
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
