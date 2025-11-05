<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentProject extends Model
{
    use HasUuids;

    protected $fillable = [
        'enrollment_id',
        'project_id',
        'status',
        'progress',
        'remedial_of',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'float',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function remedialOf()
    {
        return $this->belongsTo(EnrollmentProject::class, 'remedial_of');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function submission()
    {
        return $this->hasOne(ProjectSubmission::class, 'enrollment_project_id');
    }
}
