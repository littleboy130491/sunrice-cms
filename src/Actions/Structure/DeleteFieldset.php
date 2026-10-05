<?php

declare(strict_types=1);

namespace Sunrice\Actions\Structure;

use DomainException;
use Sunrice\Models\Blueprint;
use Sunrice\Models\Fieldset;

class DeleteFieldset
{
    public function handle(Fieldset $fieldset): void
    {
        $usedBy = $this->usedBy($fieldset);

        if ($usedBy !== []) {
            throw new DomainException(
                'Fieldset "'.$fieldset->handle.'" is used by blueprint(s): '.implode(', ', $usedBy).'.'
            );
        }

        $fieldset->delete();
    }

    /**
     * @return array<int, string> blueprint handles including this fieldset
     */
    protected function usedBy(Fieldset $fieldset): array
    {
        $handles = [];
        foreach (Blueprint::all() as $blueprint) {
            if ($this->treeContains($blueprint->fields ?? [], $fieldset->handle)) {
                $handles[] = $blueprint->handle;
            }
        }

        return $handles;
    }

    /** @param array<int, array<string, mixed>> $fields */
    protected function treeContains(array $fields, string $handle): bool
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'fieldset' && ($field['config']['fieldset'] ?? null) === $handle) {
                return true;
            }
            if ($this->treeContains($field['config']['fields'] ?? [], $handle)) {
                return true;
            }
        }

        return false;
    }
}
