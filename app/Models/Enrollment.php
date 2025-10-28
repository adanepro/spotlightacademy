<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id',
        'course_id',
        'started_at',
        'status',
        'completed_at',
        'progress',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function modules()
    {
        return $this->hasMany(EnrollmentModule::class);
    }

    public function calculateProgress()
    {
        $totalModules = $this->modules()->count() ?? 0;

        $completedModules = $this->modules()->where('status', 'completed')->sum('progress') ?? 0;

        return round(($completedModules / $totalModules) * 100, 2) ?? 0;
    }
}
