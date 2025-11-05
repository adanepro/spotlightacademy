<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class QuizSubmission extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    protected $fillable = [
        'enrollment_quiz_id',
        'enrollment_id',
        'quiz_id',
        'module_id',
        'course_id',
        'answers',
        'status',
        'review_comments',
        'link',
    ];

    protected $casts = [
        'answers' => 'array',
    ];

    protected $appends = [
        'quiz_file',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function quiz()
    {
        return $this->belongsTo(CourseQuize::class);
    }

    public function enrollmentQuiz()
    {
        return $this->belongsTo(EnrollmentQuiz::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function getQuizFileAttribute()
    {
        return $this->getMedia('quiz_file')->last()?->getUrl() ?? null;
    }
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('quiz_file')->singleFile();
    }
}
