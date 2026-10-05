<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermTranslation extends Model
{
    use HasFactory;

    protected $table = 'sunrice_term_translations';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    protected static function newFactory(): \Sunrice\Database\Factories\TermTranslationFactory
    {
        return \Sunrice\Database\Factories\TermTranslationFactory::new();
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
