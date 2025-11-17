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
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->string('title');
            $table->json('questions')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'closed'])->default('upcoming');
            $table->integer('duration_minutes')->nullable();
            $table->enum('type', ['mcq', 'short_answer'])->default('mcq');
            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('trainers')->onDelete('cascade');
            $table->enum('for', ['all', 'failed'])->default('all');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
