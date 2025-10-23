<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $hidden = [
        'pivot',
        'guard_name',
        'created_at',
        'updated_at',
    ];

    public function revokeAllPermissions()
    {
        return $this->syncPermissions([]);
    }

    // // get permissions count
    // public function getPermissionsCountAttribute()
    // {
    //     return $this->permissions->count();
    // }
}
