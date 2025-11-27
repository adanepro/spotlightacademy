<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, HasMedia
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles, HasUuids, InteractsWithMedia;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'phone_number',
        'username',
        'email',
        'password',
        'status',
        'otp',
        'otp_sent_at',
        'verified_at',
        'type',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'otp',
        'current_team_id',
        'deleted_at',
        'media',
        'password',
        'verified_at',
        'updated_at',
        'otp_sent_at',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'profile_photo_path',
        'need_create_password',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'profile_image',
    ];

    // public function getProfileImageAttribute()
    // {
    //     return $this->getMedia('profile_image')->last()?->getUrl() ?? $this->profile_photo_url;
    // }

    public function getProfileImageAttribute()
    {
        // If user has uploaded profile image via Spatie
        if ($media = $this->getMedia('profile_image')->last()) {
            return $media->getUrl();
        }

        // Fallback: generate UI avatar using full_name (or username if not available)
        $name = urlencode($this->full_name ?? $this->username ?? 'User');
        return "https://ui-avatars.com/api/?name={$name}&color=7F9CF5&background=EBF4FF";
    }



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    // generate unique username
    public static function generateUniqueUsername($name)
    {
        $baseUsername = preg_replace('/\s+/', '', strtolower($name));
        $username = $baseUsername;
        $counter = 1;

        while (self::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    //Expert has many courses
    public function courses()
    {
        return $this->hasMany(Course::class, 'expert_id');
    }

    public function expert()
    {
        return $this->hasOne(Expert::class);
    }

    public function trainer()
    {
        return $this->hasOne(Trainer::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'causer');
    }
}
