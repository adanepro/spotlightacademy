<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Enrollment extends Model
{
    use HasUuids, LogsActivity;

    protected $fillable = [
        'student_id',
        'course_id',
        'started_at',
        'status',
        'completed_at',
        'progress',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function modules()
    {
        return $this->hasMany(EnrollmentModule::class);
    }

    public function projectSubmissions()
    {
        return $this->hasMany(ProjectSubmission::class);
    }

    public function examSubmissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function quizSubmissions()
    {
        return $this->hasMany(QuizSubmission::class);
    }

    public function quizzes()
    {
        return $this->hasMany(EnrollmentQuiz::class);
    }

    public function projects()
    {
        return $this->hasMany(EnrollmentProject::class);
    }

    public function exams()
    {
        return $this->hasMany(EnrollmentExam::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }


    public function calculateProgress()
    {
        // Get all modules
        $modules = $this->modules()->with('lectures')->get();

        // Flatten all lectures from modules
        $lectures = $modules->flatMap->lectures;

        // Lectures
        $totalLectures = $lectures->count();
        $completedLecturesProgress = $lectures->where('status', 'completed')->sum('progress');

        // Modules
        $totalModules = $modules->count();
        $completedModulesProgress = $modules->where('status', 'completed')->sum('progress');

        // Projects
        $projects = $this->projectSubmissions()->get();
        $totalProjects = $projects->count();
        $completedProjects = $projects->where('status', 'passed')->count();

        // Exams
        $exams = $this->examSubmissions()->get();
        $totalExams = $exams->count();
        $completedExams = $exams->where('status', 'passed')->count();

        // Quizzes
        $quizzes = $this->quizSubmissions()->get();
        $totalQuizzes = $quizzes->count();
        $completedQuizzes = $quizzes->where('status', 'passed')->count();

        // Total items
        $totalItems = $totalModules + $totalProjects + $totalExams + $totalQuizzes + $totalLectures;
        if ($totalItems === 0) return 0;

        // Total progress
        $progressSum = $completedModulesProgress + $completedLecturesProgress +
            ($completedProjects * 100) +
            ($completedExams * 100) +
            ($completedQuizzes * 100);

        return round($progressSum / $totalItems, 2);
    }
}
