<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoKeyword extends Model
{
    protected $fillable = [
        'user_id', 'keyword', 'search_volume', 'difficulty', 
        'cpc', 'competition', 'target_url', 'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reportKeywords()
    {
        return $this->hasMany(SeoReportKeyword::class);
    }
}
