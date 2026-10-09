<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Admin\Export\CsvExporter;
use Sunrice\Admin\Table\Column;
use Sunrice\Admin\Table\TableQuery;
use Sunrice\Resources\Action;
use Sunrice\Resources\ActionFailed;
use Sunrice\Resources\Filter;
use Sunrice\Resources\Resource;
use Sunrice\Sunrice;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic CRUD for host-app models registered via
 * Sunrice::registerResource() (T14.2). Authorization requires the
 * Sunrice resource permission AND the model's own policy when one
 * is registered.
 */
class ResourceController extends Controller
{
    /** @return class-string<resource> */
    protected function resource(string $key): string
    {
        $class = app(Sunrice::class)->resource($key);
        abort_if($class === null, 404);

        return $class;
    }

    protected function checkAbility(Request $request, string $key, string $ability, ?Model $record = null): void
    {
        abort_unless($this->allows($request, $key, $ability, $record), 403);
    }

    /** The resource permission, plus the model policy's matching ability when it has a policy. */
    protected function allows(Request $request, string $key, string $ability, ?Model $record = null): bool
    {
        $user = $request->user();
        if (! $user->can("sunrice.resources.{$key}.{$ability}")) {
            return false;
        }

        /** @var class-string<resource> $class */
        $class = $this->resource($key);
        $modelClass = $class::model();
        if (Gate::getPolicyFor($modelClass) !== null) {
            $policyAbility = ['edit' => 'update', 'export' => 'viewAny', 'view' => 'viewAny'][$ability] ?? $ability;

            return $user->can($policyAbility, $record ?? $modelClass);
        }

        return true;
    }

    public function index(Request $request, string $resource): Response
    {
        $this->checkAbility($request, $resource, 'view');
        $class = $this->resource($resource);

        $query = $class::query($class::model()::query()->with($class::with()));
        $table = $this->table($class, $query)->apply($request);

        return Inertia::render('Resources/Index', [
            'resource' => [
                'key' => $resource,
                'label' => $class::label(),
                'singularLabel' => $class::singularLabel(),
            ],
            'columns' => $class::columns(),
            'rows' => $table->defaultPerPage(TablePreferencesController::perPageFor($request, "resource.{$resource}", 20))->paginate($request),
            'meta' => $table->meta(),
            'filters' => array_values(array_filter(array_map(fn (Filter $f) => $this->filterMeta($f), $class::filters()))),
            'visibleColumns' => TablePreferencesController::columnsFor(
                (int) $request->user()->getAuthIdentifier(),
                "resource.{$resource}",
                array_column($class::columns(), 'key'),
            ),
            'can' => [
                'create' => $request->user()->can("sunrice.resources.{$resource}.create"),
                'delete' => $request->user()->can("sunrice.resources.{$resource}.delete"),
            ],
            'bulkActions' => array_values(array_map(
                fn (Action $action) => $action->toArray(),
                array_filter($class::actions(), fn (Action $action) => $action->isBulk()
                    && $request->user()->can("sunrice.resources.{$resource}.{$action->getAbility()}")),
            )),
        ]);
    }

    public function create(Request $request, string $resource): Response
    {
        $this->checkAbility($request, $resource, 'create');
        $class = $this->resource($resource);

        return Inertia::render('Resources/Edit', [
            'resource' => ['key' => $resource, 'label' => $class::label(), 'singularLabel' => $class::singularLabel()],
            'fields' => $this->adminSchema($class, $resource),
            'record' => null,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $this->checkAbility($request, $resource, 'create');
        $class = $this->resource($resource);

        $input = (array) $request->input('attributes', $request->all());
        $validated = Validator::make($input, $class::rules())->validate();

        $model = $class::model()::make($this->fillableData($class, $validated));
        $model->save();
        $this->syncRelations($model, $class, $input);
        $this->log('created', $model, $class);

        return redirect()->route('sunrice.admin.resources.index', $resource)->with('success', $class::singularLabel().' created.');
    }

    public function edit(Request $request, string $resource, int $id): Response
    {
        $class = $this->resource($resource);
        $model = $class::model()::query()->with($class::with())->findOrFail($id);
        $this->checkAbility($request, $resource, 'edit', $model);

        return Inertia::render('Resources/Edit', [
            'resource' => ['key' => $resource, 'label' => $class::label(), 'singularLabel' => $class::singularLabel()],
            'fields' => $this->adminSchema($class, $resource),
            'record' => $this->recordValues($model, $class),
            'actions' => array_values(array_map(
                fn (Action $action) => $action->toArray($model),
                array_filter($class::actions(), fn (Action $action) => $this->mayRun($request, $resource, $action, $model)),
            )),
        ]);
    }

    /** POST {admin}/resources/{resource}/{id}/actions/{action}: run a record's action. */
    public function action(Request $request, string $resource, int $id, string $action): RedirectResponse
    {
        $class = $this->resource($resource);
        $definition = $class::action($action);
        if ($definition === null || $definition->opensUrl()) {
            abort(404);
        }
        $model = $class::query($class::model()::query())->findOrFail($id);
        $this->checkAbility($request, $resource, $definition->getAbility(), $model);
        abort_unless($definition->passesAuthorize($request->user(), $model), 403);

        if (! $definition->isVisibleFor($model)) {
            return back()->with('error', "\"{$definition->getLabel()}\" isn't available for this ".strtolower($class::singularLabel()).'.');
        }

        try {
            $message = $definition->run($model);
        } catch (ActionFailed $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->log($definition->getLabel(), $model, $class);

        return back()->with('success', $message ?? "{$definition->getLabel()}: done.");
    }

    /**
     * Note a change in the activity log, as "{resource label}".
     *
     * @param  class-string<resource>  $class
     * @param  array<string, mixed>  $properties
     */
    protected function log(string $action, Model $model, string $class, array $properties = []): void
    {
        app(ActivityLogger::class)->record(Str::limit(Str::lower($action), 30, ''), $model, $properties, Str::lower($class::singularLabel()));
    }

    /** Whether the signed-in user may see and run an action on a record. */
    protected function mayRun(Request $request, string $resource, Action $action, Model $model): bool
    {
        return $this->allows($request, $resource, $action->getAbility(), $model)
            && $action->isVisibleFor($model)
            && $action->passesAuthorize($request->user(), $model);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $class = $this->resource($resource);
        $model = $class::model()::query()->findOrFail($id);
        $this->checkAbility($request, $resource, 'edit', $model);

        $input = (array) $request->input('attributes', $request->all());
        $validated = Validator::make($input, $class::rules($model))->validate();

        $model->fill($this->fillableData($class, $validated));
        $model->save();
        $this->syncRelations($model, $class, $input);
        $changes = array_values(array_diff(array_keys($model->getChanges()), ActivityLogger::IGNORED));
        if ($changes !== []) {
            $this->log('updated', $model, $class, ['changes' => $changes]);
        }

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $class = $this->resource($resource);
        $model = $class::model()::query()->findOrFail($id);
        $this->checkAbility($request, $resource, 'delete', $model);

        $model->delete();
        $this->log('deleted', $model, $class);

        return redirect()->route('sunrice.admin.resources.index', $resource)->with('success', $class::singularLabel().' deleted.');
    }

    public function bulk(Request $request, string $resource): RedirectResponse
    {
        $class = $this->resource($resource);
        $ids = (array) $request->input('ids', []);

        $key = (string) $request->input('action', 'delete');
        if ($key !== 'delete') {
            return $this->bulkAction($request, $resource, $class, $key, $ids);
        }

        // Check every record first, so a refusal never leaves a half-done delete.
        $models = $class::model()::query()->whereIn('id', $ids)->get();
        foreach ($models as $model) {
            $this->checkAbility($request, $resource, 'delete', $model);
        }
        $count = 0;
        foreach ($models as $model) {
            $model->delete();
            $this->log('deleted', $model, $class);
            $count++;
        }

        return back()->with('success', "Deleted {$count} ".($count === 1 ? $class::singularLabel() : $class::label()).'.');
    }

    /**
     * Run a bulk() action on the selected records: each one is checked
     * first; records it isn't available for are skipped.
     *
     * @param  class-string<resource>  $class
     * @param  array<mixed>  $ids
     */
    protected function bulkAction(Request $request, string $resource, string $class, string $key, array $ids): RedirectResponse
    {
        $definition = $class::action($key);
        if ($definition === null || ! $definition->isBulk()) {
            abort(404);
        }

        $models = $class::query($class::model()::query())->whereIn((new ($class::model()))->getKeyName(), $ids)->get();
        foreach ($models as $model) {
            $this->checkAbility($request, $resource, $definition->getAbility(), $model);
        }

        $done = 0;
        $skipped = 0;
        $failures = [];
        foreach ($models as $model) {
            if (! $definition->isVisibleFor($model) || ! $definition->passesAuthorize($request->user(), $model)) {
                $skipped++;

                continue;
            }
            try {
                $definition->run($model);
                $this->log($definition->getLabel(), $model, $class);
                $done++;
            } catch (ActionFailed $e) {
                $failures[] = $e->getMessage();
            }
        }

        $summary = "{$definition->getLabel()}: {$done} ".($done === 1 ? strtolower($class::singularLabel()) : strtolower($class::label())).'.';
        if ($skipped > 0) {
            $summary .= " {$skipped} skipped (not available for ".($skipped === 1 ? 'it' : 'them').').';
        }
        if ($failures !== []) {
            return back()->with('error', $summary.' '.count($failures).' failed: '.$failures[0]);
        }

        return back()->with('success', $summary);
    }

    public function export(Request $request, string $resource, CsvExporter $csv): StreamedResponse
    {
        $this->checkAbility($request, $resource, 'export');
        $class = $this->resource($resource);

        $table = $this->table($class, $class::query($class::model()::query()->with($class::with())))->apply($request);

        return $csv->download($table, $class::columns(), "{$resource}.csv");
    }

    /**
     * The index query with the resource's search, filters and sortable columns.
     *
     * @param  class-string<resource>  $class
     * @param  Builder<Model>  $query
     */
    protected function table(string $class, Builder $query): TableQuery
    {
        $table = TableQuery::for($query)
            ->searchable($class::searchable())
            ->sortable(array_values(array_map(
                fn (Column $c) => $c->key,
                array_filter($class::columns(), fn (Column $c) => $c->sortable),
            )));
        foreach ($class::filters() as $filter) {
            $table->filter($filter->column, fn (Builder $q, mixed $value) => $filter->apply($q, $value));
        }

        return $table;
    }

    /**
     * A filter as the table's filter dropdowns expect it, or null for
     * kinds the table can't show (date ranges).
     *
     * @return array{key: string, label: string, type: string, options: array<int, array{value: string, label: string}>}|null
     */
    protected function filterMeta(Filter $filter): ?array
    {
        $meta = $filter->toMeta();
        $options = match ($meta['type']) {
            'boolean' => ['1' => 'Yes', '0' => 'No'],
            'select' => $meta['options'],
            default => null,
        };
        if ($options === null) {
            return null;
        }

        return [
            'key' => $meta['key'],
            'label' => $meta['label'],
            'type' => 'select',
            'options' => array_map(fn ($value, $label) => ['value' => (string) $value, 'label' => (string) $label], array_keys($options), $options),
        ];
    }

    /**
     * Admin schema with the resource key injected into each field's
     * config so belongs_to fields can query the options endpoint.
     *
     * @param  class-string<resource>  $class
     * @return array<int, array<string, mixed>>
     */
    protected function adminSchema(string $class, string $resource): array
    {
        return array_map(function (array $field) use ($resource): array {
            $field['config'] = ($field['config'] ?? []) + ['resource' => $resource];

            return $field;
        }, $class::adminSchema());
    }

    /**
     * Strip relationship fields — they are synced, not filled.
     *
     * @param  class-string<resource>  $class
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function fillableData(string $class, array $validated): array
    {
        foreach ($class::fields() as $field) {
            if ($field->isRelationship()) {
                unset($validated[$field->attribute]);
            }
        }

        return $validated;
    }

    /** @param class-string<resource> $class */
    /**
     * @param  class-string<resource>  $class
     * @param  array<string, mixed>  $validated
     */
    protected function syncRelations(Model $model, string $class, array $validated): void
    {
        foreach ($class::fields() as $field) {
            if (! $field->isRelationship() || ! array_key_exists($field->attribute, $validated)) {
                continue;
            }
            $relation = $model->{$field->relationshipName()}();
            $value = $validated[$field->attribute];
            if ($relation instanceof BelongsToMany) {
                $relation->sync((array) $value);
            } elseif ($relation instanceof BelongsTo) {
                $relation->associate($value)->save();
            }
        }
    }

    /**
     * @param  class-string<resource>  $class
     * @return array<string, mixed>
     */
    protected function recordValues(Model $model, string $class): array
    {
        $values = $model->attributesToArray();
        foreach ($class::fields() as $field) {
            if (! $field->isRelationship()) {
                continue;
            }
            $relation = $model->{$field->relationshipName()}();
            $values[$field->attribute] = $relation instanceof BelongsToMany
                ? $model->{$field->relationshipName()}->pluck($relation->getModel()->getKeyName())->all()
                : ($relation instanceof BelongsTo
                    ? $model->{$relation->getForeignKeyName()}
                    : null);
        }

        return $values;
    }
}
