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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $model = config('sunrice.auth.user_model');
        $this->authorize('viewAny', $model);

        return Inertia::render('Users/Index', [
            'users' => $model::query()->with('roles:id,name')->orderBy('name')->get()
                ->map(fn ($u) => [
                    'id' => $u->getKey(),
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name'),
                    // What this user may do to that account (super admins are protected).
                    'can' => [
                        'update' => request()->user()->can('update', $u),
                        'delete' => request()->user()->can('delete', $u),
                    ],
                ]),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', config('sunrice.auth.user_model'));
        $model = config('sunrice.auth.user_model');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique((new $model)->getTable(), 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $this->guardSuperAdminRole($request, [], $validated['roles'] ?? []);

        $model = config('sunrice.auth.user_model');
        $user = $model::create(Arr::except($validated, 'roles'));
        $user->syncRoles($validated['roles'] ?? []);

        return back()->with('success', "User {$user->email} created.");
    }

    public function update(Request $request, int|string $user): RedirectResponse
    {
        $model = config('sunrice.auth.user_model');
        $user = $model::findOrFail($user);
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }
        // Check the role change before saving anything, so a refused
        // change doesn't leave the other fields half-saved.
        if (isset($validated['roles'])) {
            $this->guardSuperAdminRole($request, $user->roles->pluck('name')->all(), $validated['roles']);
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
        if ((string) $user->getKey() === (string) $request->user()->getAuthIdentifier()) {
            return back()->with('error', 'You cannot delete yourself.');
        }
        $this->authorize('delete', $user);

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    /**
     * Only a super admin may give or take away the super-admin role.
     *
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    protected function guardSuperAdminRole(Request $request, array $before, array $after): void
    {
        $role = config('sunrice.super_admin_role');
        if ($role === null || in_array($role, $before, true) === in_array($role, $after, true)) {
            return;
        }

        if (! $request->user()->can('assignSuperAdmin', config('sunrice.auth.user_model'))) {
            throw ValidationException::withMessages(['roles' => 'Only a super admin can change who is a super admin.']);
        }
    }
}
