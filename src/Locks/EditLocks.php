<?php

declare(strict_types=1);

namespace Sunrice\Locks;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * WordPress-style edit locks. An open editor holds the lock of what it
 * edits (an entry or global in one language, or a term) and renews it
 * every few seconds; a lock nobody renews expires on its own. Someone
 * else can take it over, and the previous editor learns it on its next
 * renewal.
 *
 * Locks live in the cache: they're short-lived and per-server state is
 * fine as long as every server shares the cache store.
 */
class EditLocks
{
    /** Seconds a lock lives without being renewed. */
    public const TTL = 120;

    /**
     * Take the lock if it's free (or already ours) and renew it.
     *
     * @return array{status: 'ok'}|array{status: 'locked', by: string, since: int}|array{status: 'taken', by: string}
     */
    public function acquire(string $key, string $editor, int|string $userId, string $userName): array
    {
        $taken = $this->store()->pull($this->takenKey($key, $editor));
        if (is_array($taken)) {
            // Told once: opening the item again later takes the lock as usual.
            return ['status' => 'taken', 'by' => (string) ($taken['by'] ?? '')];
        }

        $lock = ['editor' => $editor, 'user_id' => $userId, 'name' => $userName, 'since' => time()];
        if ($this->store()->add($this->lockKey($key), $lock, self::TTL)) {
            return ['status' => 'ok'];
        }

        $current = $this->current($key);
        // Our own tab, or another tab of ours (or a closed one that hasn't
        // expired yet): a person is never locked out by themselves.
        if ($current === null || $current['editor'] === $editor || (string) $current['user_id'] === (string) $userId) {
            $lock['since'] = $current['since'] ?? time();
            $this->store()->put($this->lockKey($key), $lock, self::TTL);

            return ['status' => 'ok'];
        }

        return ['status' => 'locked', 'by' => $current['name'], 'since' => $current['since']];
    }

    /**
     * Take the lock from whoever holds it; they're told on their next renewal.
     */
    public function takeOver(string $key, string $editor, int|string $userId, string $userName): void
    {
        $current = $this->current($key);
        if ($current !== null && $current['editor'] !== $editor) {
            $this->store()->put($this->takenKey($key, $current['editor']), ['by' => $userName], 600);
        }
        $this->store()->put($this->lockKey($key), ['editor' => $editor, 'user_id' => $userId, 'name' => $userName, 'since' => time()], self::TTL);
    }

    public function release(string $key, string $editor): void
    {
        if (($this->current($key)['editor'] ?? null) === $editor) {
            $this->store()->forget($this->lockKey($key));
        }
    }

    /** @return array{editor: string, user_id: int|string, name: string, since: int}|null */
    public function current(string $key): ?array
    {
        $lock = $this->store()->get($this->lockKey($key));

        return is_array($lock) && isset($lock['editor'], $lock['name'], $lock['since'])
            ? ['editor' => (string) $lock['editor'], 'user_id' => $lock['user_id'] ?? 0, 'name' => (string) $lock['name'], 'since' => (int) $lock['since']]
            : null;
    }

    public static function key(string $type, int $id, string $locale = ''): string
    {
        return $type.':'.$id.($locale === '' ? '' : ':'.$locale);
    }

    protected function lockKey(string $key): string
    {
        return 'sunrice:edit-lock:'.$key;
    }

    protected function takenKey(string $key, string $editor): string
    {
        return 'sunrice:edit-lock-taken:'.$key.':'.$editor;
    }

    protected function store(): Repository
    {
        return Cache::store(config('sunrice.cache.store'));
    }
}
