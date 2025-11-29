<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ExamSubmission extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;
    protected $fillable = [
        'enrollment_exam_id',
        'enrollment_id',
        'exam_id',
        'course_id',
        'status',
        'answers',
        'review_comments',
        'link',
    ];

    protected $casts = [
        'answers' => 'array',
    ];

    protected $appends = [
        'exam_file',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function enrollmentExam()
    {
        return $this->belongsTo(EnrollmentExam::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('exam_file')->singleFile();
    }

    public function getExamFileAttribute()
    {
        return $this->getMedia('exam_file')->last()?->getUrl() ?? null;
    }
}
