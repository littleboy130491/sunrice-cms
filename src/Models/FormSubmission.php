<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Sunrice\Database\Factories\FormSubmissionFactory;

/**
 * @property int $id
 * @property int $form_id
 * @property array<string,mixed> $data
 * @property array<string,mixed> $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<FormSubmissionFactory>
 */
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory, Prunable, SoftDeletes;

    protected $table = 'sunrice_form_submissions';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Nothing is pruned while sunrice.forms.prune_after_days is null.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('sunrice.forms.prune_after_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<=', now()->subDays((int) $days));
    }

    /**
     * Prunable hook — remove the submission's uploaded files
     * (file-type fields under form-uploads/{handle}) before deletion.
     */
    public function pruning(): void
    {
        $disk = Storage::disk(config('sunrice.forms.upload_disk'));
        $prefix = 'form-uploads/';

        foreach ($this->form?->schema()->fields() ?? [] as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }
            $path = $this->data[$field['handle']] ?? null;
            if (is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
