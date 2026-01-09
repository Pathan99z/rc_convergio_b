<?php

namespace App\Services;

use App\Models\Collateral;
use App\Models\Activity;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Exception;

class CollateralService
{
    /**
     * Allowed file types for collaterals.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /**
     * Maximum file size in bytes (100MB).
     */
    private const MAX_FILE_SIZE = 104857600;

    /**
     * Upload a collateral file and create a database record.
     */
    public function uploadCollateral(UploadedFile $file, array $data): Collateral
    {
        $user = Auth::user();
        
        if (!$user) {
            throw new Exception('User not authenticated');
        }
        
        $tenantId = $user->tenant_id ?? $user->id;
        
        // Validate file
        $this->validateFile($file);
        
        // Verify product belongs to tenant
        $product = Product::where('id', $data['product_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file);
        $filePath = "tenant_{$tenantId}/collaterals/{$filename}";
        
        // Store the file
        $storedPath = $file->storeAs("tenant_{$tenantId}/collaterals", $filename, 'local');
        
        // Create collateral record
        $collateral = Collateral::create([
            'product_id' => $data['product_id'],
            'name' => $data['name'],
            'collateral_type' => $data['collateral_type'],
            'file_path' => $storedPath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'is_active' => $data['is_active'] ?? true,
            'tenant_id' => $tenantId,
            'team_id' => $user->team_id,
            'created_by' => $user->id,
        ]);

        // Log the upload activity
        $this->logActivity($collateral, 'collateral_uploaded', 'Collateral uploaded', [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);

        Log::info('Collateral uploaded', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'team_id' => $user->team_id,
            'collateral_id' => $collateral->id,
            'product_id' => $product->id,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        return $collateral;
    }

    /**
     * Validate uploaded file.
     */
    public function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new Exception('File size exceeds maximum allowed size of 100MB');
        }
        
        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new Exception('Invalid file type. Only PDF, Images (JPG/PNG/GIF), and PowerPoint files are allowed');
        }
        
        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'ppt', 'pptx'];
        
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new Exception('Invalid file extension. Only PDF, Images (JPG/PNG/GIF), and PowerPoint files are allowed');
        }
    }

    /**
     * Generate a unique filename for the uploaded file.
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename);
        
        return $basename . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Delete a collateral and its file.
     */
    public function deleteCollateral(Collateral $collateral): bool
    {
        $collateralData = $collateral->toArray();
        
        // Delete the physical file
        if (Storage::exists($collateral->file_path)) {
            Storage::delete($collateral->file_path);
        }

        // Log the deletion activity
        $this->logActivity($collateral, 'collateral_deleted', 'Collateral deleted', [
            'file_name' => $collateral->name,
            'file_size' => $collateral->file_size,
            'product_id' => $collateral->product_id,
        ]);

        Log::info('Collateral deleted', [
            'user_id' => Auth::id(),
            'tenant_id' => $collateral->tenant_id,
            'team_id' => $collateral->team_id,
            'collateral_id' => $collateral->id,
            'file_name' => $collateral->name,
        ]);

        // Soft delete the collateral
        return $collateral->delete();
    }

    /**
     * Update collateral metadata.
     */
    public function updateCollateral(Collateral $collateral, array $data): Collateral
    {
        $oldData = $collateral->toArray();
        
        $collateral->update($data);

        // Log the update activity
        $this->logActivity($collateral, 'collateral_updated', 'Collateral updated', [
            'changes' => array_diff_assoc($data, $oldData),
        ]);

        Log::info('Collateral updated', [
            'user_id' => Auth::id(),
            'tenant_id' => $collateral->tenant_id,
            'team_id' => $collateral->team_id,
            'collateral_id' => $collateral->id,
            'changes' => array_diff_assoc($data, $oldData),
        ]);

        return $collateral->fresh();
    }

    /**
     * Log activity for collateral actions.
     */
    private function logActivity(Collateral $collateral, string $type, string $description, array $metadata = []): void
    {
        $userId = Auth::id() ?? $collateral->created_by;
        
        Activity::create([
            'type' => $type,
            'subject' => "Collateral: {$collateral->name}",
            'description' => $description,
            'tenant_id' => $collateral->tenant_id,
            'owner_id' => $userId,
            'related_type' => 'App\\Models\\Product',
            'related_id' => $collateral->product_id,
            'metadata' => $metadata,
        ]);
    }
}

