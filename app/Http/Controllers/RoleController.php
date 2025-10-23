<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::when($request->search, function ($query, $search) {
            return $query->where('name', 'like', "%$search%")
                ->orWhereHas('permissions', function ($query) use ($search) {
                    $query->where('name', 'like', "%$search%");
                });
        })->with('permissions')
            ->get()->map(function ($role) {

                $role->users_count = $role->users->count();
                $role->permissions_count = $role->permissions->count();
                unset($role->users);

                return $role;
            });

        return $roles;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Role::class);

        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|exists:permissions,uuid',
            'description' => 'nullable|string',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return $role;
    }

    public function show(Role $role)
    {
        Gate::authorize('view', $role);

        return $role->load('permissions');
    }

    public function update(Request $request, Role $role)
    {
        Gate::authorize('update', $role);

        $request->validate([
            'name' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|exists:permissions,uuid',
            'description' => 'nullable|string',
        ]);

        if ($role->name === 'Super_Admin') {
            return response()->json(['message' => 'You cannot update Super Admin role'], 423);
        }

        $role->update([
            'name' => $request->name ?? $role->name,
            'description' => $request->description ?? $role->description,
        ]);

        if ($request->permissions) {
            $role->revokeAllPermissions();
            $role->syncPermissions($request->permissions);
        }

        return $role;
    }

    public function destroy(Role $role)
    {
        Gate::authorize('delete', $role);

        if ($role->name === 'Super_Admin') {
            return response()->json(['message' => 'You cannot delete Super Admin role'], 423);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Role is in use'], 423);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function assignRole(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'required|exists:roles,uuid',
        ]);

        $user->syncRoles($request->roles);

        return $user->load('roles');
    }
}
