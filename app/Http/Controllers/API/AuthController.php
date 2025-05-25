<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * B2B login endpoint
     */
    public function businessLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check credentials
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Create token
        $token = $user->createToken('Personal Access Token')->accessToken;

        // Load the user with profile and roles relationships
        $userData = $user->load(['profile', 'roles']);

        // Determine user_type based on roles
        $userType = $this->determineUserType($userData->roles);

        // Add user_type to the response data
        $responseData = $userData->toArray();
        $responseData['user_type'] = $userType;

        return response()->json([
            'user' => $responseData,
            'token' => $token,
            'user_type' => $userType, // Also include at root level for easier access
            'message' => 'Login successful'
        ]);
    }

    /**
     * Determine user type based on roles
     */
    private function determineUserType($roles)
    {
        if (!$roles || $roles->isEmpty()) {
            return 'user'; // Default user type
        }

        $roleNames = $roles->pluck('name')->toArray();

        // Check if user has both seller and buyer roles
        if (in_array('seller', $roleNames) && in_array('buyer', $roleNames)) {
            return 'both';
        }

        // Check for seller role
        if (in_array('seller', $roleNames)) {
            return 'seller';
        }

        // Check for buyer role
        if (in_array('buyer', $roleNames)) {
            return 'buyer';
        }

        // If user has other roles but not seller/buyer, return the first role
        if (!empty($roleNames)) {
            return $roleNames[0];
        }

        return 'user'; // Default fallback
    }
    /**
     * Reset password request
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate password reset token and send email
        // (Implementation depends on your notification system)

        return response()->json([
            'message' => 'Password reset instructions have been sent to your email'
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
