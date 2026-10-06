<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Export\CsvExporter;
use Sunrice\Admin\Table\TableQuery;
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
        $user = $request->user();
        abort_unless($user->can("sunrice.resources.{$key}.{$ability}"), 403);

        /** @var class-string<resource> $class */
        $class = $this->resource($key);
        $modelClass = $class::model();
        if (Gate::getPolicyFor($modelClass) !== null) {
            $policyAbility = ['edit' => 'update', 'export' => 'viewAny', 'view' => 'viewAny'][$ability] ?? $ability;
            $target = $record ?? $modelClass;
            abort_unless($user->can($policyAbility, $target), 403);
        }
    }

    public function index(Request $request, string $resource): Response
    {
        $this->checkAbility($request, $resource, 'view');
        $class = $this->resource($resource);

        $query = $class::query($class::model()::query()->with($class::with()));
        $table = TableQuery::for($query)
            ->searchable($class::searchable())
            ->apply($request);

        return Inertia::render('Resources/Index', [
            'resource' => [
                'key' => $resource,
                'label' => $class::label(),
                'singularLabel' => $class::singularLabel(),
            ],
            'columns' => $class::columns(),
            'rows' => $table->paginate($request),
            'meta' => $table->meta(),
            'can' => [
                'create' => $request->user()->can("sunrice.resources.{$resource}.create"),
            ],
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
        ]);
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

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $class = $this->resource($resource);
        $model = $class::model()::query()->findOrFail($id);
        $this->checkAbility($request, $resource, 'delete', $model);

        $model->delete();

        return redirect()->route('sunrice.admin.resources.index', $resource)->with('success', $class::singularLabel().' deleted.');
    }

    public function bulk(Request $request, string $resource): RedirectResponse
    {
        $class = $this->resource($resource);
        $ids = (array) $request->input('ids', []);

        $count = 0;
        foreach ($class::model()::query()->whereIn('id', $ids)->get() as $model) {
            $this->checkAbility($request, $resource, 'delete', $model);
            $model->delete();
            $count++;
        }

        return back()->with('success', "Deleted {$count} ".($count === 1 ? $class::singularLabel() : $class::label()).'.');
    }

    public function export(Request $request, string $resource, CsvExporter $csv): StreamedResponse
    {
        $this->checkAbility($request, $resource, 'export');
        $class = $this->resource($resource);

        $table = TableQuery::for($class::query($class::model()::query()->with($class::with())))
            ->searchable($class::searchable())
            ->apply($request);

        return $csv->download($table, $class::columns(), "{$resource}.csv");
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
