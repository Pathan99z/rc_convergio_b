<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadScoringRule;
use App\Services\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadScoringTemplateController extends Controller
{
    protected LeadScoringService $leadScoringService;

    public function __construct(LeadScoringService $leadScoringService)
    {
        $this->leadScoringService = $leadScoringService;
    }

    /**
     * Get all available lead scoring templates
     */
    public function getTemplates(): JsonResponse
    {
        try {
            $templates = include config_path('LeadScoringTemplates.php');
            
            return response()->json([
                'success' => true,
                'data' => $templates,
                'message' => 'Lead scoring templates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific template by key
     */
    public function getTemplate(string $templateKey): JsonResponse
    {
        try {
            $templates = include config_path('LeadScoringTemplates.php');
            
            if (!isset($templates[$templateKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $templates[$templateKey],
                'message' => 'Template retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a template (create rules from template)
     */
    public function activateTemplate(Request $request, string $templateKey): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $tenantId = $user->tenant_id;

        try {
            $templates = include config_path('LeadScoringTemplates.php');
            
            if (!isset($templates[$templateKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            $template = $templates[$templateKey];
            $createdRules = [];
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($template['rules'] as $ruleData) {
                    // Check if rule already exists
                    $existingRule = LeadScoringRule::where('tenant_id', $tenantId)
                        ->where('name', $ruleData['name'])
                        ->first();

                    if ($existingRule) {
                        $errors[] = "Rule '{$ruleData['name']}' already exists";
                        continue;
                    }

                    // Create new rule
                    $rule = LeadScoringRule::create([
                        'name' => $ruleData['name'],
                        'description' => $ruleData['description'],
                        'condition' => [
                            'event' => $ruleData['event'],
                            'criteria' => $ruleData['conditions'] ?? []
                        ],
                        'points' => $ruleData['points'],
                        'priority' => $ruleData['priority'],
                        'is_active' => $ruleData['is_active'],
                        'tenant_id' => $tenantId,
                        'created_by' => $user->id,
                        'metadata' => [
                            'template' => $templateKey,
                            'auto_created' => true
                        ]
                    ]);

                    $createdRules[] = $rule;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'template' => $templateKey,
                        'created_rules' => count($createdRules),
                        'rules' => $createdRules,
                        'errors' => $errors
                    ],
                    'message' => "Template '{$template['name']}' activated successfully"
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get smart suggestions based on tenant data
     */
    public function getSuggestions(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            // Log the authentication failure for debugging
            Log::warning('Lead scoring suggestions API called without authentication', [
                'headers' => request()->headers->all(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $tenantId = $user->tenant_id;

        try {
            $eventDetector = new \App\Services\LeadScoringEventDetector($this->leadScoringService);
            $suggestions = $eventDetector->autoDetectEvents($tenantId);

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'message' => 'Smart suggestions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create rules from suggestions
     */
    public function createFromSuggestions(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'suggestions' => 'required|array',
            'suggestions.*.type' => 'required|string',
            'suggestions.*.points' => 'required|integer',
            'suggestions.*.name' => 'required|string'
        ]);

        try {
            $createdRules = [];
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($validated['suggestions'] as $suggestion) {
                    // Check if rule already exists
                    $existingRule = LeadScoringRule::where('tenant_id', $tenantId)
                        ->where('name', $suggestion['name'])
                        ->first();

                    if ($existingRule) {
                        $errors[] = "Rule '{$suggestion['name']}' already exists";
                        continue;
                    }

                    // Create new rule
                    $rule = LeadScoringRule::create([
                        'name' => $suggestion['name'],
                        'description' => "Auto-suggested rule for {$suggestion['type']}",
                        'condition' => [
                            'event' => $suggestion['type']
                        ],
                        'points' => $suggestion['points'],
                        'priority' => 1,
                        'is_active' => true,
                        'tenant_id' => $tenantId,
                        'created_by' => $user->id,
                        'metadata' => [
                            'auto_suggested' => true,
                            'confidence' => $suggestion['confidence'] ?? 0.8
                        ]
                    ]);

                    $createdRules[] = $rule;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'created_rules' => count($createdRules),
                        'rules' => $createdRules,
                        'errors' => $errors
                    ],
                    'message' => 'Rules created from suggestions successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rules from suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template categories
     */
    public function getCategories(): JsonResponse
    {
        try {
            $templates = include config_path('LeadScoringTemplates.php');
            $categories = [];

            foreach ($templates as $template) {
                $category = $template['category'] ?? 'general';
                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'name' => ucfirst($category),
                        'count' => 0,
                        'templates' => []
                    ];
                }
                $categories[$category]['count']++;
                $categories[$category]['templates'][] = [
                    'key' => array_search($template, $templates),
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'icon' => $template['icon'] ?? 'star',
                    'color' => $template['color'] ?? '#6B7280'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $categories,
                'message' => 'Template categories retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
