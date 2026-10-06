<?php

declare(strict_types=1);

namespace Sunrice\Policies;

use Sunrice\Models\Entry;

/**
 * Entry abilities resolve to sunrice.entries.{collectionId}.{action}.
 * `update`/`delete` additionally allow `edit-own`/`delete-own` when
 * the user authored the entry. Super Admin passes via Gate::before.
 */
class EntryPolicy
{
    protected function can(mixed $user, Entry|int $entryOrCollection, string $action): bool
    {
        $collectionId = $entryOrCollection instanceof Entry ? $entryOrCollection->collection_id : $entryOrCollection;

        return $user->can("sunrice.entries.{$collectionId}.{$action}");
    }

    public function view(mixed $user, Entry $entry): bool
    {
        return $this->can($user, $entry, 'view');
    }

    public function viewAny(mixed $user, int $collectionId): bool
    {
        return $this->can($user, $collectionId, 'view');
    }

    public function create(mixed $user, int $collectionId): bool
    {
        return $this->can($user, $collectionId, 'create');
    }

    public function update(mixed $user, Entry $entry): bool
    {
        if ($this->can($user, $entry, 'edit')) {
            return true;
        }

        return $this->can($user, $entry, 'edit-own') && $entry->author_id === $user->getAuthIdentifier();
    }

    /**
     * Edit the entry's secondary-language translations. Anyone who can
     * edit the entry can translate it; the translate permission alone
     * allows translations only (not the main language, not publishing).
     */
    public function translate(mixed $user, Entry $entry): bool
    {
        return $this->update($user, $entry) || $this->can($user, $entry, 'translate');
    }

    /**
     * Reordering a collection changes every entry's position.
     */
    public function reorder(mixed $user, int $collectionId): bool
    {
        return $this->can($user, $collectionId, 'edit');
    }

    public function delete(mixed $user, Entry $entry): bool
    {
        if ($this->can($user, $entry, 'delete')) {
            return true;
        }

        return $this->can($user, $entry, 'delete-own') && $entry->author_id === $user->getAuthIdentifier();
    }

    public function publish(mixed $user, Entry $entry): bool
    {
        return $this->can($user, $entry, 'publish');
    }
}
