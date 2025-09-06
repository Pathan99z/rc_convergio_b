<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    /**
     * Get industries metadata.
     */
    public function industries(Request $request): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $industries = $this->companyService->getIndustries($tenantId);

        return response()->json([
            'success' => true,
            'data' => $industries
        ]);
    }

    /**
     * Get company types metadata.
     */
    public function companyTypes(Request $request): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $types = $this->companyService->getCompanyTypes($tenantId);

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get owners metadata.
     */
    public function owners(Request $request): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        $owners = $this->companyService->getOwners($tenantId);

        return response()->json([
            'success' => true,
            'data' => $owners
        ]);
    }
}
