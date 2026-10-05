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
use Spatie\Permission\Models\Role;
use Sunrice\Permissions\PermissionRegistry;

class RolesController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('can:sunrice.manage-roles');
    }

    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'permissions_count' => $r->permissions_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
        ]);

        Role::create(['name' => $validated['name'], 'guard_name' => config('sunrice.auth.guard', 'web')]);

        return back()->with('success', "Role \"{$validated['name']}\" created.");
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionGroups' => app(PermissionRegistry::class)->grouped(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', config('sunrice.auth.guard', 'web'))],
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return back()->with('success', 'Role saved.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->users()->exists(), 422, 'Role still assigned to users.');

        $role->delete();

        return redirect()->route('sunrice.admin.roles.index')->with('success', 'Role deleted.');
    }
}
