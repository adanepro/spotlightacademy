<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quiz_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_quiz_id');
            $table->uuid('quiz_id');
            $table->uuid('module_id');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            $table->uuid('enrollment_id');
            $table->uuid('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
            $table->foreign('enrollment_quiz_id')->references('id')->on('enrollment_quizzes')->onDelete('cascade');
            $table->foreign('quiz_id')->references('id')->on('course_quizes')->onDelete('cascade');
            $table->json('answers')->nullable();
            $table->enum('status', ['submitted', 'in_review', 'passed', 'failed'])->default('submitted');
            $table->text('review_comments')->nullable();
            $table->string('link')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_submissions');
    }
};
