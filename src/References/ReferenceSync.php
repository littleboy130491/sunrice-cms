<?php

declare(strict_types=1);

namespace Sunrice\References;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\GlobalValue;
use Sunrice\Models\Reference;
use Sunrice\Models\TermTranslation;

/**
 * Rebuilds a record's sunrice_references rows on every save, from its
 * field definitions (asset, entries, terms, link fields and
 * data-asset-id attributes inside rich text).
 */
class ReferenceSync
{
    /**
     * @param  array<int, array{target_type: string, target_id: int, field_path?: string}>  $references
     */
    public function sync(Model $source, array $references): void
    {
        $sourceType = static::sourceType($source);

        DB::transaction(function () use ($source, $sourceType, $references): void {
            Reference::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $source->getKey())
                ->delete();

            $rows = collect($references)
                ->unique(fn (array $r) => $r['target_type'].':'.$r['target_id'].':'.($r['field_path'] ?? ''))
                ->map(fn (array $r) => [
                    'source_type' => $sourceType,
                    'source_id' => $source->getKey(),
                    'target_type' => $r['target_type'],
                    'target_id' => $r['target_id'],
                    'field_path' => $r['field_path'] ?? null,
                ])
                ->all();

            if ($rows !== []) {
                Reference::query()->insert($rows);
            }
        });
    }

    public static function sourceType(Model $model): string
    {
        return match (true) {
            $model instanceof EntryTranslation => 'entry',
            $model instanceof TermTranslation => 'term',
            $model instanceof GlobalValue => 'global',
            default => strtolower(class_basename($model)),
        };
    }
}
