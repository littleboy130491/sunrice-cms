<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use Sunrice\Models\Blueprint;
use Sunrice\Models\Entry;

/**
 * Renames a field handle inside a blueprint's field tree — the
 * stored entry data keeps its keys, so the rename must be applied to
 * every entry's data too. Used by the `sunrice:rename-field` command.
 */
class RenameFieldHandle
{
    /**
     * Renames the handle everywhere: blueprint definition + data of
     * every entry in every collection using the blueprint.
     *
     * @return array{updated_entries: int}
     */
    public function handle(Blueprint $blueprint, string $from, string $to): array
    {
        if ($blueprint->schema()->field($from) === null) {
            throw new \InvalidArgumentException("Field \"{$from}\" does not exist in blueprint \"{$blueprint->handle}\".");
        }
        if ($blueprint->schema()->field($to) !== null) {
            throw new \InvalidArgumentException("Field \"{$to}\" already exists in blueprint \"{$blueprint->handle}\".");
        }

        $blueprint->fields = $this->renameInTree($blueprint->fields ?? [], $from, $to);
        $blueprint->save();

        $updated = 0;
        Entry::query()
            ->whereIn('collection_id', $blueprint->collections()->pluck('id'))
            ->orWhere('blueprint_id', $blueprint->id)
            ->chunkById(200, function ($entries) use ($from, $to, &$updated): void {
                foreach ($entries as $entry) {
                    $dirty = false;
                    foreach ($entry->translations()->get() as $translation) {
                        foreach (['data', 'draft.data'] as $path) {
                            if ($path === 'data' && $translation->data !== []) {
                                $data = $translation->data;
                                if (array_key_exists($from, $data)) {
                                    $data[$to] = $data[$from];
                                    unset($data[$from]);
                                    $translation->data = $data;
                                    $dirty = true;
                                }
                            } elseif (is_array($translation->draft) && array_key_exists($from, $translation->draft['data'] ?? [])) {
                                $draft = $translation->draft;
                                $draft['data'][$to] = $draft['data'][$from];
                                unset($draft['data'][$from]);
                                $translation->draft = $draft;
                                $dirty = true;
                            }
                        }
                        if ($dirty) {
                            $translation->save();
                        }
                    }
                    if ($dirty) {
                        $updated++;
                    }
                }
            });

        return ['updated_entries' => $updated];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    protected function renameInTree(array $fields, string $from, string $to): array
    {
        foreach ($fields as &$field) {
            if (($field['handle'] ?? null) === $from) {
                $field['handle'] = $to;
            }
            if (isset($field['config']['fields'])) {
                $field['config']['fields'] = $this->renameInTree($field['config']['fields'], $from, $to);
            }
        }

        return $fields;
    }
}
