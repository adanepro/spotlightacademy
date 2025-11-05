<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProjectSubmission extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    protected $fillable = [
        'enrollment_project_id',
        'enrollment_id',
        'project_id',
        'course_id',
        'status',
        'review_comments',
        'link',
    ];

    protected $appends = [
        'project_file',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function enrollmentProject()
    {
        return $this->belongsTo(EnrollmentProject::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function student()
    {
        return $this->enrollment->student;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('project_file')->singleFile();
    }

    public function getProjectFileAttribute()
    {
        return $this->getMedia('project_file')->last()?->getUrl() ?? null;
    }

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults();
    // }
}
