<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Models\ApiToken;

/**
 * AI access: the signed-in user's access tokens for AI agents (MCP),
 * and how to connect an agent.
 */
class AiAccessController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('sunrice.ai-access');

        return Inertia::render('AiAccess/Index', [
            'tokens' => ApiToken::query()->where('user_id', $request->user()->getAuthIdentifier())->latest('id')->get()
                ->map(fn (ApiToken $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'last_used_at' => $t->last_used_at?->diffForHumans(),
                    'created_at' => $t->created_at?->toDateString(),
                ]),
            'endpoint' => url('/'.trim((string) config('sunrice.mcp.path', 'mcp'), '/')),
            'enabled' => (bool) config('sunrice.mcp.enabled', true),
            // Shown once, right after creating.
            'newToken' => $request->session()->get('sunrice_new_token'),
            'canWriteTemplates' => (bool) config('sunrice.mcp.templates', true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('sunrice.ai-access');
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        [, $plain] = ApiToken::issue($request->user()->getAuthIdentifier(), $validated['name']);

        return back()->with('sunrice_new_token', $plain)->with('success', 'Token created. Copy it now: it won\'t be shown again.');
    }

    public function destroy(Request $request, ApiToken $token): RedirectResponse
    {
        Gate::authorize('sunrice.ai-access');
        abort_unless((string) $token->user_id === (string) $request->user()->getAuthIdentifier(), 404);

        $token->delete();

        return back()->with('success', 'Token revoked. Agents using it can no longer connect.');
    }
}
