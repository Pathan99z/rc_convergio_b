<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSeoSite extends Model
{
    protected $fillable = [
        'user_id', 'site_url', 'site_name', 'is_connected', 
        'gsc_property', 'last_synced', 'crawl_data',
        'google_access_token', 'google_refresh_token', 'google_token_expires_at'
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'last_synced' => 'datetime',
        'crawl_data' => 'array',
        'google_token_expires_at' => 'datetime'
    ];

    protected $hidden = [
        'google_access_token',
        'google_refresh_token'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
