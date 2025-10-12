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
        'tenant_id',
        'team_id',
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
     * For admin-created users, use the assigned tenant_id.
     * For public registrations, fall back to user ID for backward compatibility.
     */
    public function getTenantIdAttribute(): int
    {
        // If tenant_id is explicitly set in database and not 0, use it (for admin-created users)
        if (isset($this->attributes['tenant_id']) && $this->attributes['tenant_id'] > 0) {
            return (int) $this->attributes['tenant_id'];
        }
        
        // Fall back to user ID for backward compatibility (public registrations and default value 0)
        return $this->id ?? 0;
    }

    /**
     * Get the team that the user belongs to.
     */
    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the team memberships for this user.
     */
    public function teamMemberships(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Get the teams that this user belongs to.
     */
    public function teams(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Team::class, TeamMember::class, 'user_id', 'id', 'id', 'team_id');
    }
}
