<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
     * Includes duplicate prevention to avoid sending multiple emails.
     */
    public function sendEmailVerificationNotification(): void
    {
        // Prevent duplicate emails even if called multiple times (e.g., from multiple API calls)
        // Cache key: user_id + email prevents duplicates for 5 minutes
        $cacheKey = 'verification_email_sent_' . $this->id . '_' . md5($this->email);
        
        // Check if email was already sent recently (within 5 minutes)
        if (Cache::has($cacheKey)) {
            Log::info('Skipping duplicate verification email', [
                'user_id' => $this->id,
                'email' => $this->email,
            ]);
            return;
        }
        
        // Mark email as sent for 5 minutes to prevent duplicates
        Cache::put($cacheKey, true, now()->addMinutes(5));
        
        // Send notification (queued for async processing)
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }

    /**
     * Send the password reset notification with custom template.
     * Uses notifyNow() to send immediately instead of queuing for instant delivery.
     */
    public function sendPasswordResetNotification($token): void
    {
        // Use notifyNow() to bypass queue and send immediately for password reset emails
        // This ensures instant delivery while keeping other notifications queued
        $this->notifyNow(new \App\Notifications\ResetPasswordNotification($token));
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
     * Get the license for this tenant.
     */
    public function license(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(License::class, 'tenant_id', 'id')->where('is_active', true);
    }

    /**
     * Get the active license for this tenant (alternative method).
     */
    public function activeLicense(): ?License
    {
        return License::where('tenant_id', $this->id)->where('is_active', true)->first();
    }

    /**
     * Get all licenses for this tenant.
     */
    public function licenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(License::class, 'tenant_id', 'id');
    }

    /**
     * Get the listening keywords for this user.
     */
    public function listeningKeywords()
    {
        return $this->hasMany(\App\Models\ListeningKeyword::class);
    }

    /**
     * Get cached user with roles and permissions
     */
    public static function getCached($id)
    {
        return Cache::remember("user_{$id}", 3600, function() use ($id) {
            return static::with(['roles.permissions', 'team'])->find($id);
        });
    }

    /**
     * Get cached users for tenant
     */
    public static function getCachedForTenant($tenantId, $limit = 100)
    {
        return Cache::remember("users_tenant_{$tenantId}_{$limit}", 1800, function() use ($tenantId, $limit) {
            return static::where('tenant_id', $tenantId)
                ->with(['roles', 'team'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user can bypass tenant scope
     */
    public function canBypassTenantScope(): bool
    {
        return $this->isSuperAdmin();
    }
}
