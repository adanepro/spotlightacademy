<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentQuiz extends Model
{
    use HasUuids;

    protected $fillable = [
        'enrollment_id',
        'quiz_id',
        'module_id',
        'status',
        'progress',
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

    public function quiz()
    {
        return $this->belongsTo(CourseQuize::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function submission()
    {
        return $this->hasOne(QuizSubmission::class, 'enrollment_quiz_id');
    }       
}
