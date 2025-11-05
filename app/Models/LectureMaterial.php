<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LectureMaterial extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, HasFactory;
    protected $fillable = [
        'lecture_id',
        'title',
        'order',
    ];

    protected $appends = [
        'lecture_notes',
    ];

    public function lecture()
    {
        return $this->belongsTo(Lecture::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('lecture_notes')->singleFile();
    }

    public function getLectureNotesAttribute()
    {
        return $this->getMedia('lecture_notes')->last()?->getUrl() ?? null;
    }
}
