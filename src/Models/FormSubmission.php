<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormSubmission extends Model
{
    use HasFactory, MassPrunable, SoftDeletes;

    protected $table = 'sunrice_form_submissions';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    protected static function newFactory(): \Sunrice\Database\Factories\FormSubmissionFactory
    {
        return \Sunrice\Database\Factories\FormSubmissionFactory::new();
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Nothing is pruned while sunrice.forms.prune_after_days is null.
     */
    public function prunable(): Builder
    {
        $days = config('sunrice.forms.prune_after_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<=', now()->subDays((int) $days));
    }
}
