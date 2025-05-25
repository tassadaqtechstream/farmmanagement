<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function getAllRoles(Request $request)
    {
         // all roles with pagination
        $perPage = $request->query('per_page', 10); // Default to 10 users per page

        // Fetch users with their roles, and paginate the result
        $users =Role::paginate($perPage);


        // Format the paginated response
        $response = $users->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,

            ];
        });

        return response()->json($response);
    }
}
