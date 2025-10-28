<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentLecture extends Model
{
    use HasUuids;

    protected $fillable = [
        'enrollment_module_id',
        'lecture_id',
        'status',
        'is_watched',
        'progress',
        'completed_at',
    ];

    protected $casts = [
        'is_watched' => 'boolean',
        'progress' => 'float',
        'completed_at' => 'datetime',
    ];

    public function enrollmentModule()
    {
        return $this->belongsTo(EnrollmentModule::class, 'enrollment_module_id');
    }

    public function lecture()
    {
        return $this->belongsTo(Lecture::class);
    }

    public function materials()
    {
        return $this->hasMany(EnrollmentLectureMaterial::class);
    }
}
