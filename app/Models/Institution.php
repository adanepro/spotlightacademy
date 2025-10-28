<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Institution extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone_number',
        'email',
        'region',
        'city',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'logo',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo');
    }

    public function getLogoAttribute()
    {
        return $this->getMedia('logo')->last()?->getUrl() ?? null;
    }

    public function trainers()
    {
        return $this->hasMany(Trainer::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
