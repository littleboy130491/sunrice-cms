<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunrice\Activity\ActivityLogger;
use Sunrice\Permissions\PermissionRegistry;

class RolesController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Roles/Index', [
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'permissions_count' => $r->permissions_count,
                    'deletable' => request()->user()->can('delete', $r),
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Roles/Edit', [
            'role' => null,
            'permissionGroups' => app(PermissionRegistry::class)->grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $guard = config('sunrice.auth.guard', 'web');
        $known = app(PermissionRegistry::class)->names();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in($known)],
        ], [], ['permissions.*' => 'permission']);

        /** @var Role $role */
        $role = Role::create(['name' => $validated['name'], 'guard_name' => $guard]);
        foreach ($validated['permissions'] ?? [] as $name) {
            Permission::findOrCreate($name, $guard);
        }
        $role->syncPermissions($validated['permissions'] ?? []);
        app(ActivityLogger::class)->record('updated', $role, ['changes' => ['permissions']]);

        return redirect()->route('sunrice.admin.roles.edit', $role)->with('success', "Role \"{$role->name}\" created.");
    }

    public function edit(Role $role): Response
    {
        $this->authorize('view', $role);

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'editable' => request()->user()->can('update', $role),
            ],
            'permissionGroups' => app(PermissionRegistry::class)->grouped(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $guard = config('sunrice.auth.guard', 'web');
        $known = app(PermissionRegistry::class)->names();
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', function (string $attribute, mixed $value, \Closure $fail) use ($known, $guard): void {
                if (! in_array($value, $known, true) && ! Permission::query()->where('guard_name', $guard)->where('name', $value)->exists()) {
                    $fail("Unknown permission \"{$value}\".");
                }
            }],
        ], [], ['permissions.*' => 'permission']);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }
        if (isset($validated['permissions'])) {
            // The editor lists every permission the registry knows about,
            // including ones for collections created since the last sync.
            foreach (array_intersect($validated['permissions'], $known) as $name) {
                Permission::findOrCreate($name, $guard);
            }
            // Keep what the editor doesn't show (deleted collections and
            // taxonomies), so restoring one keeps this role's access.
            $hidden = array_values(array_diff(
                array_intersect($role->permissions->pluck('name')->all(), $known),
                app(PermissionRegistry::class)->visibleNames(),
            ));
            $before = $role->permissions->pluck('name')->sort()->values()->all();
            $role->syncPermissions(array_values(array_unique([...$validated['permissions'], ...$hidden])));
            if ($role->refresh()->permissions->pluck('name')->sort()->values()->all() !== $before) {
                app(ActivityLogger::class)->record('updated', $role, ['changes' => ['permissions']]);
            }
        }

        return back()->with('success', 'Role saved.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        if ($role->users()->exists()) {
            return back()->with('error', "Role \"{$role->name}\" is still assigned to users. Unassign it first.");
        }

        $role->delete();

        return redirect()->route('sunrice.admin.roles.index')->with('success', 'Role deleted.');
    }
}
