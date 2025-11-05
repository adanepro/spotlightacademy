<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentExam extends Model
{
    use HasUuids;

    protected $fillable = [
        'enrollment_id',
        'exam_id',
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
        return $this->belongsTo(EnrollmentExam::class, 'remedial_of');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function submission()
    {
        return $this->hasOne(ExamSubmission::class, 'enrollment_exam_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
