<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_name',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
        ];
    }

    /**
     * Send the email verification notification with a custom route.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }

    /**
     * Get the tenant ID for this user.
     * In this system, each user is their own tenant.
     */
    public function getTenantIdAttribute(): int
    {
        return $this->id;
    }

    /**
     * Get the social media accounts for this user.
     */
    public function socialAccounts()
    {
        return $this->hasMany(\App\Models\SocialAccount::class);
    }

    /**
     * Get the social media posts for this user.
     */
    public function socialMediaPosts()
    {
        return $this->hasMany(\App\Models\SocialMediaPost::class);
    }

    /**
     * Get the listening keywords for this user.
     */
    public function listeningKeywords()
    {
        return $this->hasMany(\App\Models\ListeningKeyword::class);
    }
}
