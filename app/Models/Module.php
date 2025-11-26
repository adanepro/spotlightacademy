<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasUuids, HasFactory;
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class);
    }

    public function quizzes()
    {
        return $this->hasMany(CourseQuize::class);
    }

    public function enrollmentModules()
    {
        return $this->hasMany(EnrollmentModule::class);
    }
}
