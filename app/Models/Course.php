<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Course extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, HasFactory;

    protected $fillable = [
        'expert_id',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'course_image',
        'course_trailer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('course_image')->singleFile();
        $this->addMediaCollection('course_trailer')->singleFile();
    }

    public function getCourseImageAttribute()
    {
        return $this->getMedia('course_image')->last()?->getUrl() ?? null;
    }

    public function getCourseTrailerAttribute()
    {
        return $this->getMedia('course_trailer')->last()?->getUrl() ?? null;
    }

    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }

    public function trainers()
    {
        return $this->belongsToMany(Trainer::class, 'course_trainer', 'course_id', 'trainer_id')
            ->withTimestamps();
    }

    public function getIsAssignedAttribute()
    {
        return $this->expert()->exists();
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
