<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('can:sunrice.manage-users');
    }

    public function index(): Response
    {
        $model = config('sunrice.auth.user_model');

        return Inertia::render('Users/Index', [
            'users' => $model::query()->with('roles:id,name')->orderBy('name')->get()
                ->map(fn ($u) => [
                    'id' => $u->getKey(),
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name'),
                ]),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $model = config('sunrice.auth.user_model');
        $user = $model::create(Arr::except($validated, 'roles'));
        $user->syncRoles($validated['roles'] ?? []);

        return back()->with('success', "User {$user->email} created.");
    }

    public function update(Request $request, int|string $user): RedirectResponse
    {
        $model = config('sunrice.auth.user_model');
        $user = $model::findOrFail($user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }
        $user->update(Arr::except($validated, 'roles'));

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return back()->with('success', 'User saved.');
    }

    public function destroy(Request $request, int|string $user): RedirectResponse
    {
        $model = config('sunrice.auth.user_model');
        $user = $model::findOrFail($user);

        abort_if((string) $user->getKey() === (string) $request->user()->getAuthIdentifier(), 422, 'You cannot delete yourself.');

        $user->delete();

        return back()->with('success', 'User deleted.');
    }
}
