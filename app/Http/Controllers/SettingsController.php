<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = DB::table('settings')->get()->keyBy('key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string',
            'value' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::table('settings')->updateOrInsert(
            ['key' => $request->key],
            ['value' => $request->value, 'updated_at' => now()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully'
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }

    /**
     * Get all users for admin management
     */
    public function getUsers()
    {
        $users = DB::table('users')
            ->select('id', 'username', 'email', 'role', 'full_name', 'created_at')
            ->get();

        return response()->json($users);
    }

    /**
     * Get single user
     */
    public function getUser($id)
    {
        $user = DB::table('users')
            ->select('id', 'username', 'email', 'role', 'full_name')
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    /**
     * Create a new user
     */
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,manager,staff,customer',
            'full_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $id = DB::table('users')->insertGetId([
            'username' => $request->username,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => $request->role,
            'full_name' => $request->full_name,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'id' => $id
        ]);
    }

    /**
     * Update an existing user
     */
    public function updateUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|unique:users,username,' . $id,
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|in:admin,manager,staff,customer',
            'full_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'updated_at' => now()
        ];

        if ($request->has('username')) $data['username'] = $request->username;
        if ($request->has('email')) $data['email'] = $request->email;
        if ($request->has('role')) $data['role'] = $request->role;
        if ($request->has('full_name')) $data['full_name'] = $request->full_name;
        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        DB::table('users')->where('id', $id)->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    }

    /**
     * Delete a user
     */
    public function deleteUser($id)
    {
        $user = DB::table('users')->where('id', $id)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        DB::table('users')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Update current user's profile
     */
    public function updateProfile(Request $request)
    {
        $userId = session('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|string',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $userId,
            'phone' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = ['updated_at' => now()];

        // Handle full_name by splitting into first_name and last_name
        // Note: The users table has first_name and last_name columns, NOT full_name
        if ($request->filled('full_name')) {
            $nameParts = explode(' ', trim($request->full_name), 2);
            $data['first_name'] = $nameParts[0];
            $data['last_name'] = $nameParts[1] ?? '';
        }

        // Only set these if full_name wasn't provided (to avoid overwriting)
        if (!$request->filled('full_name')) {
            if ($request->filled('first_name')) $data['first_name'] = $request->first_name;
            if ($request->filled('last_name')) $data['last_name'] = $request->last_name;
        }

        if ($request->filled('email')) $data['email'] = $request->email;
        if ($request->filled('phone')) $data['phone'] = $request->phone;

        DB::table('users')->where('id', $userId)->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    }

    /**
     * Change current user's password
     */
    public function changePassword(Request $request)
    {
        $userId = session('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        DB::table('users')->where('id', $userId)->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->new_password),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Update store information
     */
    public function updateStoreInfo(Request $request)
    {
        $settings = [
            'store_name' => $request->store_name,
            'store_address' => $request->store_address,
            'store_phone' => $request->store_phone
        ];

        foreach ($settings as $key => $value) {
            if ($value !== null) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Store information updated successfully'
        ]);
    }

    /**
     * Update system settings
     */
    public function updateSystemSettings(Request $request)
    {
        $settings = [
            'low_stock_threshold' => $request->low_stock_threshold,
            'currency' => $request->currency,
            'email_notifications' => $request->email_notifications ? '1' : '0'
        ];

        foreach ($settings as $key => $value) {
            if ($value !== null) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully'
        ]);
    }
}
