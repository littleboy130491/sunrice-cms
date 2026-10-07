<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
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
use Sunrice\Actions\Users\DeleteUser;
use Sunrice\Models\Asset;
use Sunrice\Models\Entry;

class UsersController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $model = config('sunrice.auth.user_model');
        $this->authorize('viewAny', $model);

        // What each user created, for the delete dialog.
        $entries = Entry::query()->whereNotNull('author_id')->groupBy('author_id')->selectRaw('author_id, count(*) as total')->pluck('total', 'author_id');
        $assets = Asset::query()->whereNotNull('uploaded_by')->groupBy('uploaded_by')->selectRaw('uploaded_by, count(*) as total')->pluck('total', 'uploaded_by');

        return Inertia::render('Users/Index', [
            'users' => $model::query()->with('roles:id,name')->orderBy('name')->get()
                ->map(fn ($u) => [
                    'id' => $u->getKey(),
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name'),
                    'content' => ['entries' => (int) ($entries[$u->getKey()] ?? 0), 'assets' => (int) ($assets[$u->getKey()] ?? 0)],
                    // What this user may do to that account (super admins are protected).
                    'can' => [
                        'update' => request()->user()->can('update', $u),
                        'delete' => request()->user()->can('delete', $u),
                    ],
                ]),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', config('sunrice.auth.user_model'));

        return $this->editor(null);
    }

    public function edit(int|string $user): Response
    {
        $model = config('sunrice.auth.user_model');
        $user = $model::findOrFail($user);
        $this->authorize('update', $user);

        return $this->editor($user);
    }

    /** The user editor page (create and edit). */
    protected function editor(?Model $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user === null ? null : [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute('email'),
                'roles' => $user->getAttribute('roles')->pluck('name'),
                'content' => DeleteUser::counts($user),
                'is_self' => (string) $user->getKey() === (string) request()->user()->getAuthIdentifier(),
            ],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            // Who could receive this user's content when deleting them.
            'others' => $user === null ? [] : config('sunrice.auth.user_model')::query()
                ->whereKeyNot($user->getKey())->orderBy('name')->get()
                ->map(fn (Model $u) => ['id' => $u->getKey(), 'name' => $u->getAttribute('name'), 'email' => $u->getAttribute('email')]),
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

        return redirect()->route('sunrice.admin.users.index')->with('success', "User {$user->email} created.");
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

    public function destroy(Request $request, int|string $user, DeleteUser $delete): RedirectResponse
    {
        $model = config('sunrice.auth.user_model');
        $user = $model::findOrFail($user);
        if ((string) $user->getKey() === (string) $request->user()->getAuthIdentifier()) {
            return back()->with('error', 'You cannot delete yourself.');
        }
        $this->authorize('delete', $user);

        $validated = $request->validate([
            'content' => ['nullable', Rule::in(DeleteUser::MODES)],
            'reassign_to' => ['nullable', 'required_if:content,reassign'],
        ]);
        $reassignTo = ($validated['content'] ?? null) === 'reassign'
            ? $model::query()->find($validated['reassign_to'])
            : null;
        if (($validated['content'] ?? null) === 'reassign' && $reassignTo === null) {
            return back()->withErrors(['reassign_to' => 'Choose another user to receive the content.']);
        }

        $delete->handle($user, $validated['content'] ?? 'keep', $reassignTo);

        return redirect()->route('sunrice.admin.users.index')->with('success', match ($validated['content'] ?? 'keep') {
            'reassign' => "User deleted. Their content now belongs to {$reassignTo?->getAttribute('name')}.",
            'delete' => 'User deleted and their entries moved to the trash.',
            default => 'User deleted. Their content was kept without an author.',
        });
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
