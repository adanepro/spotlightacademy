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
        Schema::create('enrollment_lecture_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_lecture_id');
            $table->uuid('lecture_material_id');
            $table->foreign('enrollment_lecture_id')->references('id')->on('enrollment_lectures')->onDelete('cascade');
            $table->foreign('lecture_material_id')->references('id')->on('lecture_materials')->onDelete('cascade');
            $table->boolean('is_viewed')->default(false);
            $table->boolean('is_downloaded')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_lecture_materials');
    }
};
