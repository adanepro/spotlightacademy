<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CourseQuize extends Model implements HasMedia
{
    use InteractsWithMedia, HasUuids, HasFactory, LogsActivity;
    protected $fillable = [
        'module_id',
        'questions',
        'created_by',
    ];

    protected $casts = [
        'questions' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function submissions()
    {
        return $this->hasMany(QuizSubmission::class);
    }

    public function enrollmentQuizzes()
    {
        return $this->hasMany(EnrollmentQuiz::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
