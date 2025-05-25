<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUsersWithRoles(Request $request)
    {
        // Define the number of users per page (or get it from the request)
        $perPage = $request->query('pageSize', 10); // Default to 10 users per page

        // Fetch users with their roles, and paginate the result
        $users = Admin::with('roles')->paginate($perPage);


        // Format the paginated response
        $response = $users->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'role' => $user->roles->pluck('id'),
            ];
        });

        return response()->json($response);
    }

    public function addUsers(Request $request)
    {
        //validate the request data
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
        ]);

        // Create a new admin user with the provided data
        $user = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);
        //attach role
        $user->roles()->attach($request->role);

    }

    public function getAllUsers()
    {
        $data = User::all();
        return $this->sendResponse($data, 'Farm details retrieved successfully');
    }
}
