<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    /**
     * Display a listing of all available roles.
     * Accessible by authenticated users (typically admins for role management).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Check if user is authenticated
        if (!$request->user()) {
            abort(401, 'Authentication required');
        }

        // Get all roles ordered by name
        $roles = Role::orderBy('name')->get();

        return RoleResource::collection($roles);
    }
}
