<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Lecture extends Model implements HasMedia
{
    use InteractsWithMedia, HasUuids, HasFactory;
    protected $fillable = [
        'module_id',
        'title',
        'order',
        'duration',
    ];

    protected $appends = [
        'lecture_video',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function materials()
    {
        return $this->hasMany(LectureMaterial::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('lecture_video')->singleFile();
    }

    public function getLectureVideoAttribute()
    {
        return $this->getMedia('lecture_video')->last()?->getUrl() ?? null;
    }
}
