<?php

declare(strict_types=1);

namespace Sunrice\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Models\ApiToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs the MCP request in as the owner of the bearer token, so every
 * tool runs with that user's permissions. The user also needs the
 * "Connect AI agents" permission (sunrice.ai-access).
 */
class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findValid((string) $request->bearerToken());
        $user = $token === null ? null : $token->user;

        if ($token === null || ! $user instanceof Authenticatable) {
            return response()->json(['error' => 'Missing or invalid access token. Create one in the admin under AI access.'], 401)
                ->header('WWW-Authenticate', 'Bearer');
        }
        if (! method_exists($user, 'can') || ! $user->can('sunrice.ai-access')) {
            return response()->json(['error' => 'This user may not connect AI agents (permission "Connect AI agents").'], 403);
        }

        Auth::guard(config('sunrice.auth.guard', 'web'))->setUser($user);
        Auth::shouldUse(config('sunrice.auth.guard', 'web'));
        // The activity log shows these changes as the AI agent's, by token name.
        app(ActivityLogger::class)->actingVia('ai', $token->name);

        // At most once a minute, not on every call.
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
