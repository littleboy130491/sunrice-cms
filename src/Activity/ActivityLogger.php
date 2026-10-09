<?php

declare(strict_types=1);

namespace Sunrice\Activity;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sunrice\Models\ActivityLog;
use Sunrice\Models\ApiToken;
use Sunrice\Models\Asset;
use Sunrice\Models\AssetFolder;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Collection;
use Sunrice\Models\Entry;
use Sunrice\Models\EntryTranslation;
use Sunrice\Models\Fieldset;
use Sunrice\Models\Form;
use Sunrice\Models\FormSubmission;
use Sunrice\Models\GlobalSet;
use Sunrice\Models\GlobalValue;
use Sunrice\Models\Menu;
use Sunrice\Models\MenuItem;
use Sunrice\Models\Setting;
use Sunrice\Models\Taxonomy;
use Sunrice\Models\Term;
use Sunrice\Models\TermTranslation;
use Throwable;

/**
 * Writes the activity log: who created, changed or deleted what.
 *
 * Model events of Sunrice's own models (and the user and role models) are
 * recorded by ActivityObserver; controllers record what model events
 * don't show (reordering, a role's permissions, resources). Within one
 * request, repeated changes to the same thing (an entry and its
 * translation, or several saves) become one line.
 *
 * Only changes made by a signed-in user, or from the console / queue
 * ("System"), are logged: a visitor's form submission is not admin
 * activity.
 */
class ActivityLogger
{
    /** Attributes whose change alone isn't worth a line. */
    public const IGNORED = ['updated_at', 'created_at', 'deleted_at', 'sort_order', 'remember_token', 'last_used_at', 'sizes', 'content_published_at', 'is_ready'];

    /** Setting keys that are someone's own preferences, not site changes. */
    public const IGNORED_SETTINGS = ['table_columns.', 'table_per_page.'];

    /**
     * Lines written in this request, by subject, for merging: "entry:12" => [id, action].
     *
     * @var array<string, array{0: int, 1: string}>
     */
    protected array $written = [];

    protected int $paused = 0;

    /**
     * The models observed for the log, with their subject type.
     *
     * @return array<class-string<Model>, string>
     */
    public function observed(): array
    {
        /** @var array<class-string<Model>, string> $models */
        $models = array_filter([
            Entry::class => 'entry',
            EntryTranslation::class => 'entry',
            Term::class => 'term',
            TermTranslation::class => 'term',
            Collection::class => 'collection',
            Taxonomy::class => 'taxonomy',
            Blueprint::class => 'blueprint',
            Fieldset::class => 'fieldset',
            Form::class => 'form',
            FormSubmission::class => 'form submission',
            GlobalSet::class => 'global set',
            GlobalValue::class => 'global set',
            Menu::class => 'menu',
            MenuItem::class => 'menu item',
            Asset::class => 'asset',
            AssetFolder::class => 'asset folder',
            ApiToken::class => 'AI access token',
            Setting::class => 'setting',
            (string) config('sunrice.auth.user_model') => 'user',
            (string) config('permission.models.role') => 'role',
        ], fn (string $type, string $class) => $class !== '' && class_exists($class), ARRAY_FILTER_USE_BOTH);

        return $models;
    }

    public function enabled(): bool
    {
        return $this->paused === 0 && (bool) config('sunrice.activity.enabled', true);
    }

    /**
     * Run something without logging it (imports, demo content).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutLogging(Closure $callback): mixed
    {
        $this->paused++;
        try {
            return $callback();
        } finally {
            $this->paused--;
        }
    }

    /** Forget this request's lines, so a queue job or a new request starts fresh. */
    public function reset(): void
    {
        $this->written = [];
    }

    /**
     * A model event (from ActivityObserver).
     *
     * @param  'created'|'updated'|'deleted'|'restored'|'forceDeleted'  $event
     */
    public function modelEvent(Model $model, string $event): void
    {
        if (! $this->enabled() || ($event === 'deleted' && $this->softDeleting($model) === null)) {
            return;
        }

        [$type, $subject] = $this->subjectOf($model);
        if ($type === null) {
            return;
        }

        $changes = [];
        $action = match ($event) {
            'created' => 'created',
            'restored' => 'restored',
            'forceDeleted' => 'deleted',
            'deleted' => $this->softDeleting($model) ? 'trashed' : 'deleted',
            default => 'updated',
        };

        $isPart = $subject !== $model; // a translation, value or item of its parent
        if ($event === 'updated' || $isPart) {
            $changes = $this->changedKeys($model);
            if ($event === 'updated' && $changes === []) {
                return;
            }
            if ($isPart) {
                $locale = (string) ($model->getAttribute('locale') ?? '');
                $suffix = $locale !== '' ? " ({$locale})" : '';
                $changes = match (true) {
                    // Menu items: '"Home" added', '"Home" url'.
                    $model instanceof MenuItem => $event === 'updated'
                        ? array_map(fn (string $key) => $this->menuItemLabel($model)." {$key}", $changes)
                        : [$this->menuItemLabel($model).' '.($action === 'created' ? 'added' : 'removed')],
                    // Translations and global values: 'title (en)', 'title (en, draft)', 'translation (id) added'.
                    $event === 'updated' => array_map(fn (string $key) => str_starts_with($key, 'draft.')
                        ? substr($key, 6).($locale !== '' ? " ({$locale}, draft)" : ' (draft)')
                        : "{$key}{$suffix}", $changes),
                    default => [($model instanceof GlobalValue ? 'values' : 'translation').$suffix.' '.($action === 'created' ? 'added' : 'removed')],
                };
                $action = 'updated';
            }
        }
        if ($model instanceof Setting) {
            // A setting is always there; saving it the first time is a change too.
            $action = $event === 'deleted' ? 'deleted' : 'updated';
            if ($event === 'created' && is_array($model->getAttribute('value'))) {
                $changes = array_map('strval', array_keys($model->getAttribute('value')));
            }
        }
        if ($subject instanceof Entry && $event === 'updated' && ! $isPart && $model->wasChanged('status')) {
            $action = $model->getAttribute('status') === 'published' ? 'published' : 'unpublished';
            $changes = array_values(array_diff($changes, ['status'])); // the action says it
        }
        if ($model instanceof FormSubmission && $action === 'created') {
            return; // sent by visitors
        }

        $this->record($action, $subject, $changes === [] ? [] : ['changes' => array_values(array_unique($changes))], $type);
    }

    /**
     * Add a line. `$subject` is a model, or a label for things without one.
     * Lines for the same subject in one request are merged.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, Model|string $subject, array $properties = [], ?string $type = null): ?ActivityLog
    {
        if (! $this->enabled()) {
            return null;
        }
        $actor = $this->actor();
        if ($actor === false) {
            return null;
        }

        try {
            if (is_string($subject)) {
                $type ??= 'system';
                $id = null;
                $label = $subject;
            } else {
                $type ??= $this->subjectOf($subject)[0] ?? Str::snake(class_basename($subject), ' ');
                $id = (string) $subject->getKey();
                $label = $this->labelOf($subject);
            }

            $key = $id === null ? "{$type}::{$label}" : "{$type}:{$id}";
            if (isset($this->written[$key]) && $this->mergeable($this->written[$key][1], $action)) {
                return $this->merge($this->written[$key][0], $action, $label, $properties, $key);
            }

            $line = ActivityLog::query()->create([
                'user_id' => $actor?->getAuthIdentifier(),
                'user_name' => $actor === null ? null : (string) ($actor->getAttribute('name') ?? $actor->getAttribute('email') ?? '#'.$actor->getAuthIdentifier()),
                'action' => $action,
                'subject_type' => $type,
                'subject_id' => $id,
                'subject_label' => Str::limit($label, 250),
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => request()->route() === null ? null : request()->ip(),
                'created_at' => now(),
            ]);
            $this->written[$key] = [$line->id, $action];

            return $line;
        } catch (Throwable $e) {
            // The log must never stop the change itself (e.g. before `migrate`).
            Log::warning('Sunrice activity log: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Who is acting: the signed-in user; null for the console and queue
     * (shown as "System"); false for visitors, who aren't logged.
     *
     * @return (Authenticatable&Model)|false|null
     */
    protected function actor(): Model|false|null
    {
        $user = auth(config('sunrice.auth.guard', 'web'))->user();
        if ($user instanceof Model) {
            return $user;
        }

        // No route: the console, a queue job or the scheduler ("System").
        // A web request without a signed-in user is a visitor.
        return request()->route() === null ? null : false;
    }

    /** A second event on a subject updates its line rather than adding one. */
    protected function mergeable(string $existing, string $incoming): bool
    {
        return in_array($existing, ['created', 'updated', 'published', 'unpublished'], true)
            && in_array($incoming, ['updated', 'published', 'unpublished'], true);
    }

    /** @param array<string, mixed> $properties */
    protected function merge(int $id, string $action, string $label, array $properties, string $key): ?ActivityLog
    {
        $line = ActivityLog::query()->find($id);
        if ($line === null) {
            return null;
        }
        $existing = $line->properties ?? [];
        $changes = array_values(array_unique([...(array) ($existing['changes'] ?? []), ...(array) ($properties['changes'] ?? [])]));
        // "created" stays; "published" is more telling than "updated".
        $newAction = $line->action === 'created' || $action === 'updated' ? $line->action : $action;
        $line->forceFill([
            'action' => $newAction,
            'subject_label' => Str::limit($label, 250),
            'properties' => array_filter([...$existing, ...$properties, 'changes' => $line->action === 'created' ? null : ($changes ?: null)]) ?: null,
        ])->save();
        $this->written[$key] = [$line->id, $newAction];

        return $line;
    }

    /**
     * The subject type and model a change belongs to: translations, global
     * values and menu items count as their parent; null for what isn't
     * logged (a person's table preferences).
     *
     * @return array{0: string|null, 1: Model}
     */
    public function subjectOf(Model $model): array
    {
        $types = $this->observed();
        $type = $types[$model::class] ?? null;
        foreach ($types as $class => $t) {
            if ($type === null && $model instanceof $class) {
                $type = $t;
            }
        }

        $parent = match (true) {
            $model instanceof EntryTranslation => Entry::withTrashed()->find($model->entry_id),
            $model instanceof TermTranslation => Term::withTrashed()->find($model->term_id),
            $model instanceof GlobalValue => GlobalSet::query()->find($model->getAttribute('global_id')),
            $model instanceof MenuItem => Menu::query()->find($model->menu_id),
            default => $model,
        };
        if ($model instanceof MenuItem) {
            $type = 'menu';
        }
        if ($model instanceof Setting && Str::startsWith((string) $model->key, self::IGNORED_SETTINGS)) {
            $type = null;
        }

        return [$parent === null ? null : $type, $parent ?? $model];
    }

    /** A readable name for a subject, as it is now. */
    public function labelOf(Model $model): string
    {
        $label = match (true) {
            $model instanceof Entry => $this->entryTitle($model),
            $model instanceof Term => $model->mainTranslation()?->name ?? $model->translations()->value('name'),
            $model instanceof Setting => match ((string) $model->key) {
                'site' => 'Site settings',
                'homepage_entry_id' => 'Homepage',
                default => Str::headline((string) $model->key),
            },
            $model instanceof Asset => $model->getAttribute('title') ?: $model->getAttribute('filename'),
            default => $model->getAttribute('title') ?? $model->getAttribute('name') ?? $model->getAttribute('email') ?? $model->getAttribute('handle'),
        };

        $label = is_string($label) && trim($label) !== '' ? $label : '#'.$model->getKey();
        // "About us (Pages)": entries and terms with where they live.
        $parent = match (true) {
            $model instanceof Entry => Collection::withTrashed()->whereKey($model->collection_id)->value('title'),
            $model instanceof Term => Taxonomy::withTrashed()->whereKey($model->taxonomy_id)->value('title'),
            default => null,
        };

        return is_string($parent) && $parent !== '' ? "{$label} ({$parent})" : $label;

    }

    protected function entryTitle(Entry $entry): ?string
    {
        return $entry->mainTranslation()?->title ?? $entry->translations()->value('title');
    }

    protected function menuItemLabel(MenuItem $item): string
    {
        $labels = (array) $item->getAttribute('labels');

        return '"'.(string) (reset($labels) ?: 'menu item').'"';
    }

    /**
     * Names of what changed: attributes, and the fields inside JSON ones
     * ("data.body", "seo.title").
     *
     * @return array<int, string>
     */
    protected function changedKeys(Model $model): array
    {
        $keys = [];
        foreach (array_keys($model->getChanges()) as $key) {
            // A draft being cleared is part of publishing, not an edit.
            if (in_array($key, self::IGNORED, true) || ($key === 'draft' && $model->getAttribute('draft') === null)) {
                continue;
            }
            $before = $model->getOriginal($key);
            $after = $model->getAttribute($key);
            if (is_string($before) && is_array($after)) {
                $before = json_decode($before, true);
            }
            if (is_array($before) && is_array($after) && ! array_is_list($after)) {
                $fields = array_keys(array_filter(
                    array_replace(array_fill_keys(array_keys($before), null), $after),
                    fn ($value, $field) => ($before[$field] ?? null) != $value,
                    ARRAY_FILTER_USE_BOTH,
                ));
                foreach ($fields as $field) {
                    // A setting's value is its fields: "name", not "value.name".
                    $keys[] = $model instanceof Setting ? (string) $field : "{$key}.{$field}";
                }

                continue;
            }
            $keys[] = $key;
        }

        return $keys;
    }

    /** For "deleted": true when soft-deleting, false when deleting for good, null when not a delete to log. */
    protected function softDeleting(Model $model): ?bool
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return false;
        }

        // A force delete fires "deleted" and then "forceDeleted": log the latter only.
        return method_exists($model, 'isForceDeleting') && $model->isForceDeleting() ? null : true;
    }
}
