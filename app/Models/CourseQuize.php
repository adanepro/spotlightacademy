<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CourseQuize extends Model implements HasMedia
{
    use InteractsWithMedia, HasUuids;
    protected $fillable = [
        'module_id',
        'questions',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'questions' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

}
