<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoReport extends Model
{
    protected $fillable = [
        'user_id', 'name', 'url', 'report_type', 'status', 
        'score', 'recommendations', 'crawl_data', 'crawled_at'
    ];

    protected $casts = [
        'recommendations' => 'array',
        'crawl_data' => 'array',
        'crawled_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function keywords()
    {
        return $this->belongsToMany(SeoKeyword::class, 'seo_report_keywords')
                    ->withPivot('frequency', 'source')
                    ->withTimestamps();
    }

    public function reportKeywords()
    {
        return $this->hasMany(SeoReportKeyword::class);
    }
}
