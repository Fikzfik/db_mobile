<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exclude_id' => 'required|integer|exists:users,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $excludeId = $request->query('exclude_id');

        try {
            $users = User::select('id_user', 'name', 'email')
                ->where('id_user', '!=', $excludeId)
                ->get();


            return response()->json($users, 200);
        } catch (\Exception $e) {

            return response()->json(['message' => 'Server error'], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email or password is incorrect',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id_user,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }

    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }
    public function updateProfile(Request $request)
    {
        // Log incoming request
        Log::info('UpdateProfile Request', [
            'input' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // Validate input
        $validator = Validator::make($request->all(), [
            'id_user' => 'required|integer|exists:users,id_user',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('UpdateProfile Validation Failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $idUser = $request->input('id_user');
        $name = $request->input('name');
        $email = $request->input('email');

        try {
            // Check if email is unique (excluding current user)
            Log::info('Checking email uniqueness', ['email' => $email, 'id_user' => $idUser]);
            $emailExists = DB::selectOne(
                'SELECT COUNT(*) as count FROM users WHERE email = ? AND id_user != ?',
                [$email, $idUser]
            );

            if ($emailExists->count > 0) {
                Log::warning('UpdateProfile: Email already taken', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'errors' => ['email' => ['The email has already been taken.']],
                ], 422);
            }

            // Update user profile
            Log::info('Updating user profile', ['id_user' => $idUser, 'name' => $name, 'email' => $email]);
            $affected = DB::update(
                'UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id_user = ?',
                [$name, $email, $idUser]
            );

            if ($affected === 0) {
                Log::warning('UpdateProfile: No rows affected', ['id_user' => $idUser]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or no changes made',
                ], 404);
            }

            // Fetch updated user data
            Log::info('Fetching updated user data', ['id_user' => $idUser]);
            $updatedUser = DB::selectOne(
                'SELECT id_user, name, email, money, created_at, updated_at FROM users WHERE id_user = ?',
                [$idUser]
            );

            Log::info('UpdateProfile: Success', ['updatedUser' => (array)$updatedUser]);
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $updatedUser,
            ]);
        } catch (\Exception $e) {
            Log::error('UpdateProfile: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error updating profile: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:1',
            'exclude_id' => 'sometimes|exists:users,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = User::where('name', 'like', '%' . $request->name . '%')
            ->select('id_user', 'name', 'email');

        if ($request->has('exclude_id')) {
            $query->where('id_user', '!=', $request->exclude_id);
        }

        $users = $query->get();

        return response()->json($users, 200);
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
