<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoReportKeyword extends Model
{
    protected $fillable = [
        'seo_report_id', 'seo_keyword_id', 'frequency', 'source'
    ];

    public function seoReport()
    {
        return $this->belongsTo(SeoReport::class);
    }

    public function seoKeyword()
    {
        return $this->belongsTo(SeoKeyword::class);
    }
}
