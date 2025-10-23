<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAnyPermission', Role::class);

        $roles = Permission::when($request->search, function ($query, $search) {
            return $query->where('name', 'like', "%$search%")
                ->orWhereHas('permissions', function ($query) use ($search) {
                    $query->where('name', 'like', "%$search%");
                });
        })->get();

        return $roles;
    }
}
