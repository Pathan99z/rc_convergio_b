<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'tenant_id',
        'report_type',
        'format',
        'status',
        'parameters',
        'filename',
        'file_path',
        'download_url',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this report job.
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope a query to only include jobs for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by report type.
     */
    public function scopeByReportType($query, $reportType)
    {
        return $query->where('report_type', $reportType);
    }

    /**
     * Scope a query to filter by format.
     */
    public function scopeByFormat($query, $format)
    {
        return $query->where('format', $format);
    }

    /**
     * Scope a query to only include non-expired jobs.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if the job is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the job is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the job is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the file size in human readable format.
     */
    public function getFileSizeAttribute(): ?string
    {
        if (!$this->file_path || !file_exists($this->file_path)) {
            return null;
        }

        $bytes = filesize($this->file_path);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Generate a signed download URL.
     */
    public function generateDownloadUrl(): string
    {
        if (!$this->file_path || !file_exists($this->file_path)) {
            throw new \Exception('File not found');
        }

        // If download_url already exists, return it
        if ($this->download_url) {
            return $this->download_url;
        }

        // Generate a temporary signed URL (implement based on your storage system)
        $filename = $this->filename ?: basename($this->file_path);
        $url = url("/api/tracking/reports/{$this->job_id}/download");
        
        $this->update(['download_url' => $url]);
        
        return $url;
    }

    /**
     * Mark job as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark job as completed.
     */
    public function markAsCompleted(string $filePath): void
    {
        $filename = basename($filePath);
        
        // Verify file exists before marking as completed
        if (!file_exists($filePath)) {
            throw new \Exception("Report file was not created at: {$filePath}");
        }

        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'filename' => $filename,
            'download_url' => null, // Will be generated when needed
            'completed_at' => now(),
            'expires_at' => now()->addDays(30), // Reports expire after 30 days
        ]);
    }

    /**
     * Mark job as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}