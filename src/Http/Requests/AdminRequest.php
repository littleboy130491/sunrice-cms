<?php

declare(strict_types=1);

namespace Sunrice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Sunrice\Models\Collection;

/**
 * Base request for admin endpoints. Child classes override
 * `policyAbility()` / `policyTarget()` to run the check, or `authorize()`
 * entirely.
 */
class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->policyAbility();
        if ($ability === null) {
            return true;
        }

        $target = $this->policyTarget();

        return $target === null
            ? $this->user(config('sunrice.auth.guard'))->can($ability)
            : $this->user(config('sunrice.auth.guard'))->can($ability, $target);
    }

    protected function policyAbility(): ?string
    {
        return null;
    }

    protected function policyTarget(): mixed
    {
        return null;
    }

    /**
     * The collection resolved from the route for collection-scoped
     * policies: route parameter may be a model or a handle/id.
     */
    protected function collectionId(): ?int
    {
        $collection = $this->route('collection');

        return $collection instanceof Collection ? $collection->id : (is_numeric($collection) ? (int) $collection : null);
    }
}
