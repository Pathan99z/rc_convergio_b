<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * Display a listing of teams for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Team::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        $query = Team::where('tenant_id', $tenantId);

        // Search functionality
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Pagination
        $perPage = $request->query('per_page', 15);
        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "teams_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache teams list for 5 minutes (300 seconds)
        $teams = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['createdBy', 'members.user'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        $team = Team::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        $team->load(['createdBy', 'members.user']);

        // Clear cache after creating team
        $this->clearTeamsCache($tenantId, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Team created successfully',
            'data' => $team,
        ], 201);
    }

    /**
     * Display the specified team.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $userId = $request->user()->id;
        
        // Create cache key with tenant, user, and team ID isolation
        $cacheKey = "team_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache team detail for 15 minutes (900 seconds)
        $team = Cache::remember($cacheKey, 900, function () use ($id) {
            return Team::with(['createdBy', 'members.user'])->findOrFail($id);
        });
        
        $this->authorize('view', $team);

        return response()->json([
            'success' => true,
            'data' => $team,
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        
        $this->authorize('update', $team);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $team->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $team->load(['createdBy', 'members.user']);

        // Clear cache after updating team
        $tenantId = $team->tenant_id;
        $this->clearTeamsCache($tenantId, $request->user()->id);
        Cache::forget("team_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Team updated successfully',
            'data' => $team,
        ]);
    }

    /**
     * Remove the specified team.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        
        $this->authorize('delete', $team);

        DB::transaction(function () use ($team) {
            // Remove all team members first
            $team->members()->delete();
            
            // Update all records that reference this team to set team_id to null
            User::where('team_id', $team->id)->update(['team_id' => null]);
            \App\Models\Contact::where('team_id', $team->id)->update(['team_id' => null]);
            \App\Models\Deal::where('team_id', $team->id)->update(['team_id' => null]);
            \App\Models\Company::where('team_id', $team->id)->update(['team_id' => null]);
            \App\Models\Task::where('team_id', $team->id)->update(['team_id' => null]);
            \App\Models\Activity::where('team_id', $team->id)->update(['team_id' => null]);
            
            // Delete the team
            $team->delete();
        });

        // Clear cache after deleting team
        $tenantId = $team->tenant_id;
        $userId = $request->user()->id;
        $teamId = $team->id;
        $this->clearTeamsCache($tenantId, $userId);
        Cache::forget("team_show_{$tenantId}_{$userId}_{$teamId}");

        return response()->json([
            'success' => true,
            'message' => 'Team deleted successfully',
        ]);
    }

    /**
     * Get team members.
     */
    public function members(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        
        $this->authorize('view', $team);

        $tenantId = $team->tenant_id;
        $userId = $request->user()->id;
        
        // Create cache key for team members
        $cacheKey = "team_members_{$tenantId}_{$userId}_{$id}";
        
        // Cache team members for 5 minutes (300 seconds)
        $members = Cache::remember($cacheKey, 300, function () use ($team) {
            return $team->members()
                ->with('user')
                ->orderBy('role', 'desc') // managers first
                ->orderBy('created_at', 'asc')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Add a member to the team.
     */
    public function addMember(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        
        $this->authorize('addMember', $team);

        $validator = Validator::make($request->all(), [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('team_members')->where(function ($query) use ($id) {
                    return $query->where('team_id', $id);
                }),
            ],
            'role' => 'required|in:manager,member',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify the user belongs to the same tenant
        $user = User::findOrFail($request->user_id);
        if ($user->tenant_id !== $team->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to the same tenant',
            ], 403);
        }

        $member = TeamMember::create([
            'team_id' => $id,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        // ✅ FIX: Update user's team_id when adding to team
        $user->update(['team_id' => $id]);

        $member->load('user');

        // Clear cache after adding member
        $tenantId = $team->tenant_id;
        Cache::forget("team_show_{$tenantId}_{$request->user()->id}_{$id}");
        Cache::forget("team_members_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully',
            'data' => $member,
        ], 201);
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(Request $request, int $id, int $userId): JsonResponse
    {
        $team = Team::findOrFail($id);
        
        $this->authorize('removeMember', $team);

        $member = TeamMember::where('team_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // If removing the user from their primary team, update their team_id
        $user = User::find($userId);
        if ($user && $user->team_id === $team->id) {
            $user->update(['team_id' => null]);
        }

        $member->delete();

        // Clear cache after removing member
        $tenantId = $team->tenant_id;
        Cache::forget("team_show_{$tenantId}_{$request->user()->id}_{$id}");
        Cache::forget("team_members_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);
    }

    /**
     * Update a member's role in the team.
     */
    public function updateMemberRole(Request $request, int $id, int $userId): JsonResponse
    {
        $team = Team::findOrFail($id);
        
        $this->authorize('manageMembers', $team);

        $validator = Validator::make($request->all(), [
            'role' => 'required|in:manager,member',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = TeamMember::where('team_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $member->update(['role' => $request->role]);
        $member->load('user');

        // Clear cache after updating member role
        $tenantId = $team->tenant_id;
        Cache::forget("team_show_{$tenantId}_{$request->user()->id}_{$id}");
        Cache::forget("team_members_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Member role updated successfully',
            'data' => $member,
        ]);
    }

    /**
     * Clear teams cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearTeamsCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for teams list
            $commonParams = [
                '',
                md5(serialize(['per_page' => 15])),
                md5(serialize(['search' => '', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("teams_list_{$tenantId}_{$userId}_{$params}");
            }

            Log::info('Teams cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear teams cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
