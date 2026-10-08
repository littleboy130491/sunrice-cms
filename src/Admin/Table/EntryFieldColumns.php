<?php

declare(strict_types=1);

namespace Sunrice\Admin\Table;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sunrice\Fields\CustomField;
use Sunrice\Fields\FieldRegistry;
use Sunrice\Models\Asset;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\TermTranslation;
use Sunrice\Query\JsonField;
use Sunrice\Support\Locales;

/**
 * Optional entries-list columns for a collection's blueprint fields
 * (keyed `field.{handle}`): short, readable text for each value, and
 * sorting on the main-language value for text, number, date, choice and
 * toggle fields. Containers (group, repeater, flexible) and form uploads
 * have no single value to show and are left out.
 */
class EntryFieldColumns
{
    public const PREFIX = 'field.';

    /** Cell text is cut to this many characters. */
    public const LIMIT = 80;

    protected const SKIPPED = ['group', 'repeater', 'flexible', 'fieldset', 'file'];

    /**
     * Shown fields by handle, as their built-in base type with any preset
     * config applied.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $fields = [];

    /** @var array<string, string> handle => sort cast */
    protected array $sortCasts = [];

    /** @var array<string, array<int, string>> lookups by kind => id => text */
    protected array $labels = ['entries' => [], 'terms' => [], 'assets' => []];

    public function __construct(protected Collection $collection)
    {
        $registry = app(FieldRegistry::class);

        // The collection's blueprint first, then any other blueprint its
        // entries use; the first definition of a handle wins.
        $blueprintIds = Entry::query()->where('collection_id', $collection->id)
            ->whereNotNull('blueprint_id')->distinct()->pluck('blueprint_id')
            ->prepend($collection->blueprint_id)->filter()->unique()->values();
        $blueprints = Blueprint::query()->whereKey($blueprintIds)->get()
            ->sortBy(fn (Blueprint $b) => $blueprintIds->search($b->id));

        foreach ($blueprints as $blueprint) {
            foreach ($blueprint->schema()->fields() as $field) {
                $handle = $field['handle'] ?? null;
                $type = (string) ($field['type'] ?? 'text');
                if (! is_string($handle) || $handle === '' || isset($this->fields[$handle]) || ! $registry->has($type)) {
                    continue;
                }
                $fieldType = $registry->get($type);
                if ($fieldType instanceof CustomField) {
                    $field['config'] = array_merge($fieldType::config(), (array) ($field['config'] ?? []));
                    $type = $fieldType::baseType();
                }
                if (in_array($type, self::SKIPPED, true) || preg_match('/^[A-Za-z0-9_-]+$/', $handle) !== 1) {
                    continue;
                }
                $field['type'] = $type;
                $this->fields[$handle] = $field;

                // Toggles sort as text: not every database casts true/false to a number.
                $cast = $type === 'toggle' ? 'string' : $fieldType->sortCast();
                if ($cast !== null && ! ($field['config']['multiple'] ?? false)) {
                    $this->sortCasts[$handle] = $cast;
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $taken  labels of the table's own columns:
     *                                     a field with the same name is marked "(field)"
     * @return array<int, Column>
     */
    public function columns(array $taken = []): array
    {
        $taken = array_map('mb_strtolower', $taken);

        return array_map(
            function (string $handle, array $field) use ($taken) {
                $label = (string) (($field['label'] ?? null) ?: Str::headline($handle));
                if (in_array(mb_strtolower($label), $taken, true)) {
                    $label .= ' (field)';
                }

                return new Column(self::PREFIX.$handle, $label, sortable: isset($this->sortCasts[$handle]));
            },
            array_keys($this->fields),
            $this->fields,
        );
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(fn (string $handle) => self::PREFIX.$handle, array_keys($this->fields));
    }

    /**
     * Sorting for each sortable field column, by the main-language value.
     */
    public function applySorts(TableQuery $table): void
    {
        foreach ($this->sortCasts as $handle => $cast) {
            $table->sortUsing(self::PREFIX.$handle, function (Builder $query, string $direction) use ($handle, $cast): void {
                $value = EntryTranslation::query()->whereColumn('entry_id', (new Entry)->qualifyColumn('id'))
                    ->where('locale', Locales::main())->limit(1);
                $value->selectRaw(JsonField::castExpression($value, 'data', $handle, $cast));
                $query->orderBy($value, $direction);
            });
        }
    }

    /**
     * Load the titles, names and file names the shown reference fields
     * point at, for a page of entries (one query per kind).
     *
     * @param  iterable<Model>  $entries
     * @param  array<int, string>  $keys  visible column keys
     */
    public function preload(iterable $entries, array $keys): void
    {
        $ids = ['entries' => [], 'terms' => [], 'assets' => []];
        foreach ($this->shown($keys) as $handle => $field) {
            $kind = match ($field['type']) {
                'entries' => 'entries',
                'terms' => 'terms',
                'asset' => 'assets',
                'link' => 'link',
                default => null,
            };
            if ($kind === null) {
                continue;
            }
            foreach ($entries as $entry) {
                if (! $entry instanceof Entry) {
                    continue;
                }
                $value = $this->rawValue($entry, $handle);
                if ($kind === 'link') {
                    if (is_array($value) && is_numeric($value['entry_id'] ?? null)) {
                        $ids['entries'][] = (int) $value['entry_id'];
                    }

                    continue;
                }
                foreach ((array) $value as $id) {
                    if (is_numeric($id)) {
                        $ids[$kind][] = (int) $id;
                    }
                }
            }
        }

        $main = Locales::main();
        if ($ids['entries'] !== []) {
            $this->labels['entries'] = EntryTranslation::query()->whereIn('entry_id', array_unique($ids['entries']))
                ->where('locale', $main)->pluck('title', 'entry_id')->map(fn ($t) => (string) $t)->all();
        }
        if ($ids['terms'] !== []) {
            $this->labels['terms'] = TermTranslation::query()->whereIn('term_id', array_unique($ids['terms']))
                ->where('locale', $main)->pluck('name', 'term_id')->map(fn ($n) => (string) $n)->all();
        }
        if ($ids['assets'] !== []) {
            $this->labels['assets'] = Asset::query()->whereKey(array_unique($ids['assets']))->get(['id', 'title', 'filename'])
                ->mapWithKeys(fn (Asset $a) => [$a->id => (string) ($a->title ?: $a->filename)])->all();
        }
    }

    /**
     * Cell text for an entry's shown field columns, keyed by column key.
     *
     * @param  array<int, string>  $keys  visible column keys
     * @return array<string, string|null>
     */
    public function values(Entry $entry, array $keys): array
    {
        $out = [];
        foreach ($this->shown($keys) as $handle => $field) {
            $text = $this->format($this->rawValue($entry, $handle), $field);
            $out[self::PREFIX.$handle] = $text === null || $text === '' ? null : Str::limit($text, self::LIMIT, '…');
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, array<string, mixed>>
     */
    protected function shown(array $keys): array
    {
        return array_filter(
            $this->fields,
            fn (string $handle) => in_array(self::PREFIX.$handle, $keys, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    protected function rawValue(Entry $entry, string $handle): mixed
    {
        // The list loads only the main-language translation.
        $translation = $entry->translations->first();

        return $translation === null ? null : ($translation->data[$handle] ?? null);
    }

    /** @param array<string, mixed> $field */
    protected function format(mixed $value, array $field): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        return match ($field['type']) {
            'toggle' => $value ? 'Yes' : 'No',
            'select' => $this->join(array_map(fn ($v) => $this->optionLabel($field, $v), (array) $value)),
            'entries' => $this->join(array_map(fn ($id) => $this->labels['entries'][(int) $id] ?? "#{$id}", (array) $value)),
            'terms' => $this->join(array_map(fn ($id) => $this->labels['terms'][(int) $id] ?? "#{$id}", (array) $value)),
            'asset' => $this->join(array_map(fn ($id) => $this->labels['assets'][(int) $id] ?? "#{$id}", (array) $value)),
            'link' => is_array($value) ? $this->linkText($value) : null,
            default => $this->plain($value),
        };
    }

    /** @param array<string, mixed> $field */
    protected function optionLabel(array $field, mixed $value): string
    {
        foreach ((array) ($field['config']['options'] ?? []) as $option) {
            if (is_array($option) && (string) ($option['value'] ?? '') === (string) $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param array<string, mixed> $link */
    protected function linkText(array $link): ?string
    {
        if (is_string($link['label'] ?? null) && $link['label'] !== '') {
            return $link['label'];
        }
        if (($link['type'] ?? null) === 'entry' && is_numeric($link['entry_id'] ?? null)) {
            return $this->labels['entries'][(int) $link['entry_id']] ?? '#'.$link['entry_id'];
        }

        return is_string($link['url'] ?? null) ? $link['url'] : null;
    }

    /** Text without markup, on one line. */
    protected function plain(mixed $value): ?string
    {
        if (is_array($value)) {
            return $this->join(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value));
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (! is_scalar($value)) {
            return null;
        }
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) $value)), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @param array<int, string> $parts */
    protected function join(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn (string $p) => $p !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
