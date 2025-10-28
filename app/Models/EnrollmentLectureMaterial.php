<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EnrollmentLectureMaterial extends Model
{
    use HasUuids;
    protected $fillable = [
        'enrollment_lecture_id',
        'lecture_material_id',
        'is_viewed',
        'is_downloaded',
    ];

    protected $casts = [
        'is_viewed' => 'boolean',
        'is_downloaded' => 'boolean',
    ];

    public function enrollmentLecture()
    {
        return $this->belongsTo(EnrollmentLecture::class, 'enrollment_lecture_id');
    }

    public function lectureMaterial()
    {
        return $this->belongsTo(LectureMaterial::class);
    }
}
