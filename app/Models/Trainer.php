<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Trainer extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, HasFactory;

    protected $fillable = [
        'user_id',
        'institution_id',
        'course_ids',
        'qualification',
        'social_links',
        'expertise',
        'certifications',
        'bio',
        'status',
    ];

    protected $casts = [
        'course_ids' => 'array',
        'social_links' => 'array',
        'expertise' => 'array',
        'certifications' => 'array',
        'status' => 'boolean',
    ];

    protected $appends = [
        'is_assigned',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image');
    }

    public function getProfileImageAttribute()
    {
        return $this->getMedia('profile_image')->last()?->getUrl() ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_trainer', 'trainer_id', 'course_id')
        ->withTimestamps();
    }

    public function getIsAssignedAttribute(): bool
    {
        return $this->courses()->exists();
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'created_by');
    }

    public function quizzes()
    {
        return $this->hasMany(CourseQuize::class, 'created_by');
    }
}
