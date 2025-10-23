<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = app_path('Models');

        $files = File::files($path);
        $modelNames = [];
        foreach ($files as $file) {
            $modelName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $snakeCaseModelName = $this->camelCaseToSnakeCase($modelName);
            $modelNames[] = strtolower($snakeCaseModelName);
        }

        foreach ($modelNames as $modelName) {
            Permission::updateOrCreate(['name' => 'create_' . $modelName]);
            Permission::updateOrCreate(['name' => 'read_' . $modelName]);
            Permission::updateOrCreate(['name' => 'update_' . $modelName]);
            Permission::updateOrCreate(['name' => 'delete_' . $modelName]);
        }

        Permission::updateOrCreate(['name' => 'view_pulse']);

        Permission::updateOrCreate(['name' => 'assign_role']);
        Permission::updateOrCreate(['name' => 'attach_permission']);
        Permission::updateOrCreate(['name' => 'detach_permission']);

        Permission::updateOrCreate(['name' => 'read_activity_log']);
    }

    private  function camelCaseToSnakeCase($input)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
