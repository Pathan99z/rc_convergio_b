<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'tenant_id',
        'type',
        'status',
        'parameters',
        'filename',
        'file_path',
        'download_url',
        'total_records',
        'processed_records',
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
     * Get the tenant that owns this export job.
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
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
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
     * Get the progress percentage.
     */
    public function getProgressPercentage(): int
    {
        if (!$this->total_records || $this->total_records === 0) {
            return 0;
        }

        return min(100, round(($this->processed_records / $this->total_records) * 100));
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

        // Generate a simple download URL (since we don't have the route yet)
        $filename = $this->filename ?: basename($this->file_path);
        $url = url("/api/tracking/export/{$this->job_id}/download");
        
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
    public function markAsCompleted(string $filePath, int $totalRecords): void
    {
        $filename = basename($filePath);
        
        // First update the basic info
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'filename' => $filename,
            'total_records' => $totalRecords,
            'processed_records' => $totalRecords,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7), // Files expire after 7 days
        ]);
        
        // Then generate download URL if file exists
        if (file_exists($filePath)) {
            $downloadUrl = $this->generateDownloadUrl();
            $this->update(['download_url' => $downloadUrl]);
        }
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

    /**
     * Update progress.
     */
    public function updateProgress(int $processedRecords): void
    {
        $this->update(['processed_records' => $processedRecords]);
    }
}