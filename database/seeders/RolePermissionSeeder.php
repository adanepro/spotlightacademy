<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);

        $permissionPrefixes = [
            'read_',
            'create_',
            'update_',
            'delete_',
        ];


        /**
         *Super_Admin */
        $Admin = Role::where('name', 'Admin')->first();
        $AdminPermissions = [];
        foreach ($permissionPrefixes as $prefix) {
            $AdminPermissions[] = "{$prefix}role";
            $AdminPermissions[] = "{$prefix}permission";
            $AdminPermissions[] = "{$prefix}user";
        }

        $AdminPermissions[] = "assign_role";
        $AdminPermissions[] = "attach_permission";
        $AdminPermissions[] = "detach_permission";
        $AdminPermissions[] = "read_activity_log";
        $AdminPermissions[] = "view_pulse";

        $Admin->givePermissionTo($AdminPermissions);
    }
}
