<?php

declare(strict_types=1);

namespace Sunrice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An access token for AI agents (MCP). Stores a SHA-256 hash; the plain
 * token ("sr_…") is only returned by issue().
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 */
class ApiToken extends Model
{
    protected $table = 'sunrice_api_tokens';

    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Create a token for a user; returns the model and the plain token.
     *
     * @return array{0: ApiToken, 1: string}
     */
    public static function issue(int|string $userId, string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = 'sr_'.Str::random(48);
        $token = static::query()->create([
            'user_id' => $userId,
            'name' => $name,
            'token' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    public static function findValid(string $plain): ?self
    {
        if (! str_starts_with($plain, 'sr_')) {
            return null;
        }
        $token = static::query()->where('token', hash('sha256', $plain))->first();

        return $token === null || ($token->expires_at !== null && $token->expires_at->isPast()) ? null : $token;
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('sunrice.auth.user_model');

        return $this->belongsTo($model, 'user_id');
    }
}
