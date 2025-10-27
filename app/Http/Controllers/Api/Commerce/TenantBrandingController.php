<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TenantBrandingController extends Controller
{
    /**
     * Get tenant branding.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $branding = TenantBranding::getDefaultBranding($tenantId);

        return response()->json([
            'success' => true,
            'data' => $branding,
        ]);
    }

    /**
     * Update tenant branding.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $tenantId = $request->user()->tenant_id;

            // Log the incoming request for debugging
            Log::info('Branding update request', [
                'tenant_id' => $tenantId,
                'user_id' => $request->user()->id,
                'all_request_data' => $request->all(),
                'request_data_except_logo' => $request->except(['logo']),
                'has_logo' => $request->hasFile('logo'),
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method(),
                'raw_input' => $request->getContent(),
                'files' => $request->allFiles(),
            ]);

            $validator = Validator::make($request->all(), [
                'company_name' => 'nullable|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'company_address' => 'nullable|string|max:1000',
                'company_phone' => 'nullable|string|max:50',
                'company_email' => 'nullable|email|max:255',
                'company_website' => 'nullable|url|max:255',
                'invoice_footer' => 'nullable|string|max:1000',
                'email_signature' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                Log::error('Branding validation failed', [
                    'tenant_id' => $tenantId,
                    'errors' => $validator->errors()->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $branding = TenantBranding::getDefaultBranding($tenantId);
            
            // Process all form data - ensure we capture everything
            $data = [];
            $allowedFields = [
                'company_name',
                'primary_color', 
                'secondary_color',
                'company_address',
                'company_phone',
                'company_email',
                'company_website',
                'invoice_footer',
                'email_signature',
            ];
            
            foreach ($allowedFields as $field) {
                if ($request->has($field) && $request->input($field) !== null) {
                    $data[$field] = $request->input($field);
                }
            }
            
            Log::info('Processed data for update', [
                'tenant_id' => $tenantId,
                'processed_data' => $data,
                'original_request' => $request->only($allowedFields),
            ]);

            // Handle logo upload
            if ($request->hasFile('logo')) {
                try {
                    $logo = $request->file('logo');
                    $logoPath = $logo->store("tenants/{$tenantId}/branding", 'public');
                    $data['logo_url'] = Storage::url($logoPath);
                    
                    Log::info('Logo uploaded successfully', [
                        'tenant_id' => $tenantId,
                        'logo_path' => $logoPath,
                        'logo_url' => $data['logo_url'],
                        'file_size' => $logo->getSize(),
                        'file_mime' => $logo->getMimeType(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Logo upload failed', [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without logo if upload fails
                }
            }

            $branding->update($data);

            Log::info('Branding updated successfully', [
                'tenant_id' => $tenantId,
                'updated_data' => $data,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branding updated successfully',
                'data' => $branding->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Branding update failed', [
                'tenant_id' => $request->user()->tenant_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update branding: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset branding to default.
     */
    public function reset(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        try {
            $branding = TenantBranding::getDefaultBranding($tenantId);
            
            // Delete existing logo if it exists
            if ($branding->logo_url && str_contains($branding->logo_url, 'storage/tenants/')) {
                $logoPath = str_replace('/storage/', '', $branding->logo_url);
                Storage::disk('public')->delete($logoPath);
            }

            // Reset to default values
            $branding->update([
                'company_name' => 'RC Convergio',
                'logo_url' => null,
                'primary_color' => '#3b82f6',
                'secondary_color' => '#1f2937',
                'company_address' => null,
                'company_phone' => null,
                'company_email' => 'billing@rcconvergio.com',
                'company_website' => null,
                'invoice_footer' => null,
                'email_signature' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branding reset to default successfully',
                'data' => $branding,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset branding: ' . $e->getMessage(),
            ], 500);
        }
    }
}
