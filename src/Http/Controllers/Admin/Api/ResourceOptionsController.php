<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin\Api;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunrice\Resources\Resource;
use Sunrice\Sunrice;

/**
 * Search endpoint backing the belongs_to / belongs_to_many select
 * fields on resource edit pages (T14.3).
 */
class ResourceOptionsController extends Controller
{
    public function index(Request $request, string $resource, string $field): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can("sunrice.resources.{$resource}.view"), 403);

        /** @var class-string<resource>|null $class */
        $class = app(Sunrice::class)->resource($resource);
        abort_if($class === null, 404);

        $resourceField = collect($class::fields())
            ->first(fn ($f) => $f->attribute === $field && $f->isRelationship());
        abort_if($resourceField === null, 404);

        $model = $class::model()::make();
        $relation = $model->{$resourceField->relationshipName()}();
        abort_unless($relation instanceof BelongsTo || $relation instanceof BelongsToMany, 404);

        $labelColumn = $resourceField->relationshipLabelColumn() ?? 'id';
        $related = $relation->getRelated();

        $query = $related->newQuery()
            ->select([$related->getKeyName(), $labelColumn])
            ->orderBy($labelColumn)
            ->limit(50);

        if ($search = $request->string('q')->toString()) {
            $query->whereLike($labelColumn, "%{$search}%");
        }

        return response()->json([
            'options' => $query->get()
                ->map(fn ($row) => ['id' => $row->getKey(), 'label' => (string) ($row->{$labelColumn} ?? $row->getKey())])
                ->all(),
        ]);
    }
}
