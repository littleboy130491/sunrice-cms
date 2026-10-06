<?php

declare(strict_types=1);

namespace Sunrice\Actions\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sunrice\Actions\Entries\TrashEntry;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;
use Sunrice\Models\Revision;

/**
 * Deletes a user and decides what happens to what they created:
 *
 * - `reassign`: their entries and uploads move to another user;
 * - `keep`: entries and uploads stay, without an author;
 * - `delete`: their entries go to the trash (restorable until emptied);
 *   uploads stay, without an uploader, since other content may use them.
 *
 * Revision history never names the deleted user afterwards.
 */
class DeleteUser
{
    public const MODES = ['reassign', 'keep', 'delete'];

    /**
     * @return array{entries: int, assets: int}
     */
    public static function counts(Model $user): array
    {
        return [
            'entries' => Entry::query()->where('author_id', $user->getKey())->count(),
            'assets' => Asset::query()->where('uploaded_by', $user->getKey())->count(),
        ];
    }

    public function handle(Model $user, string $mode = 'keep', ?Model $reassignTo = null): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['content' => 'Choose what happens to this user\'s content.']);
        }
        if ($mode === 'reassign' && ($reassignTo === null || $reassignTo->is($user))) {
            throw ValidationException::withMessages(['reassign_to' => 'Choose another user to receive the content.']);
        }

        DB::transaction(function () use ($user, $mode, $reassignTo): void {
            $id = $user->getKey();
            $newOwner = $mode === 'reassign' && $reassignTo !== null ? $reassignTo->getKey() : null;

            if ($mode === 'delete') {
                Entry::query()->where('author_id', $id)->each(function (Entry $entry): void {
                    app(TrashEntry::class)->handle($entry);
                });
            }

            // Trashed entries too, so a later restore isn't tied to a ghost.
            Entry::withTrashed()->where('author_id', $id)->update(['author_id' => $newOwner]);
            Asset::withTrashed()->where('uploaded_by', $id)->update(['uploaded_by' => $newOwner]);
            Revision::query()->where('user_id', $id)->update(['user_id' => null]);

            $user->delete();
        });
    }
}
