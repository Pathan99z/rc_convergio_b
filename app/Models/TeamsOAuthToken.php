<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TeamsOAuthToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get a valid token for a user.
     */
    public static function getValidTokenForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->whereNotNull('access_token')
            ->first();
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Get the user that owns the token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
