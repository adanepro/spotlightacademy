<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RolePermissionController extends Controller
{
    public function attachPermission(Request $request, Role $role)
    {
        Gate::authorize('attachPermission', $role);
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'required|uuid|exists:permissions,uuid|distinct',
        ]);

        $role->givePermissionTo($request->permissions);

        return response()->json(['message' => 'Permissions attached successfully']);
    }

    public function detachPermission(Request $request, Role $role)
    {
        Gate::authorize('detachPermission', $role);
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'required|uuid|exists:permissions,uuid|distinct',
        ]);

        foreach ($request->permissions as $value) {
            $role->revokePermissionTo($value);
        }

        return response()->json(['message' => 'Permissions detached successfully']);
    }
}
