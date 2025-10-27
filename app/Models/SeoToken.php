<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'site_url'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    protected $hidden = [
        'access_token',
        'refresh_token'
    ];

    /**
     * Get the user that owns the token
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if token is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the active token for a user
     */
    public static function getForUser($userId)
    {
        return static::where('user_id', $userId)->latest()->first();
    }

    /**
     * Store or update token for a user
     */
    public static function storeForUser($userId, $accessToken, $refreshToken = null, $expiresIn = 3600, $siteUrl = null)
    {
        return static::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds($expiresIn),
                'site_url' => $siteUrl
            ]
        );
    }
}
