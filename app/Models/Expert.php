<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expert extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, HasFactory;

    protected $fillable = [
        'user_id',
        'qualification',
        'social_links',
        'expertise',
        'certifications',
        'bio',
        'status',
    ];

    protected $casts = [
        'social_links' => 'array',
        'expertise' => 'array',
        'certifications' => 'array',
        'status' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image');
    }

    public function getProfileImageAttribute()
    {
        return $this->getMedia('profile_image')->last()?->getUrl() ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function modules()
    {
        return $this->hasManyThrough(Module::class, Course::class);
    }

    public function getIsAssignedAttribute(): bool
    {
        return $this->courses()->exists();
    }
}
