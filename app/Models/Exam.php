<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Exam extends Model
{
    use HasUuids,  HasFactory;
    protected $fillable = [
        'course_id',
        'title',
        'questions',
        'start_date',
        'end_date',
        'status',
        'duration_minutes',
        'type',
        'created_by',
        'for',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'questions' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function enrollmentExams()
    {
        return $this->hasMany(EnrollmentExam::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Trainer::class, 'created_by');
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_by = Auth::user()->trainer->id;
        });
    }

    public function updateStatus()
    {
        $now = now();
        if ($now->lt($this->start_date)) {
            $this->status = 'upcoming';
        } elseif ($now->between($this->start_date, $this->end_date)) {
            $this->status = 'ongoing';
        } else {
            $this->status = 'closed';
        }
        $this->save();
    }

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults();
    // }
}
