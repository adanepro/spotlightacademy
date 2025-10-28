<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentModule extends Model
{
    use HasUuids;
    protected $fillable = [
        'enrollment_id',
        'module_id',
        'status',
        'progress',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'progress' => 'float',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function lectures()
    {
        return $this->hasMany(EnrollmentLecture::class);
    }
}
