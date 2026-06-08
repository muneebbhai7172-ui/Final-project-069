<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use OTPHP\TOTP;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // Rate limiting
        $key = 'login:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'message' => 'Too many login attempts. Try again later.']);
        }

        // Find user by username OR email
        $user = DB::table('users')
            ->where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$user) {
            RateLimiter::hit($key);
            DB::table('login_attempts')->insert([
                'ip_address' => $ip,
                'email' => $username,
                'success' => false,
                'user_agent' => $userAgent,
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid username or password.']);
        }

        if ($user->account_locked) {
            return response()->json(['success' => false, 'message' => 'Account is locked.']);
        }

        if (!Hash::check($password, $user->password)) {
            $failedAttempts = $user->failed_attempts + 1;
            DB::table('users')->where('id', $user->id)->update(['failed_attempts' => $failedAttempts]);

            if ($failedAttempts >= 5) {
                DB::table('users')->where('id', $user->id)->update(['account_locked' => true]);
                return response()->json(['success' => false, 'message' => 'Account locked due to too many failed attempts.']);
            }

            RateLimiter::hit($key);
            DB::table('login_attempts')->insert([
                'ip_address' => $ip,
                'email' => $user->email,
                'success' => false,
                'user_agent' => $userAgent,
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid username or password.']);
        }

        // Successful login - but check for 2FA first
        $requires2FA = $user->two_factor_enabled && $user->two_factor_confirmed_at;

        DB::table('users')->where('id', $user->id)->update([
            'failed_attempts' => 0,
            'last_login' => now(),
        ]);

        DB::table('login_attempts')->insert([
            'ip_address' => $ip,
            'email' => $user->email,
            'success' => true,
            'user_agent' => $userAgent,
        ]);

        RateLimiter::clear($key);

        // If 2FA is enabled, require verification before completing login
        if ($requires2FA) {
            // Store pending 2FA session
            Session::put('pending_2fa_user_id', $user->id);
            Session::put('pending_2fa_timestamp', now()->timestamp);
            $request->session()->save();

            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'message' => 'Please enter your 2FA verification code.',
                'user_id' => $user->id
            ]);
        }

        // Regenerate session to prevent fixation attacks
        $request->session()->regenerate();

        // Start session
        Session::put('user_id', $user->id);
        Session::put('username', $user->username);
        Session::put('role', $user->role);

        // Save session immediately
        $request->session()->save();

        // Debug logging
        Log::info('Login successful', [
            'user_id' => $user->id,
            'username' => $user->username,
            'session_id' => $request->session()->getId(),
            'session_data' => Session::all(),
            'cookie_name' => config('session.cookie')
        ]);

        $response = response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            ],
            'redirect' => '/modules/dashboard/index.html',
            'debug' => [
                'session_id' => $request->session()->getId(),
                'cookie_name' => config('session.cookie'),
                'has_session' => $request->hasSession()
            ]
        ]);

        // Explicitly ensure session cookie is set
        $response->header('X-Session-ID', $request->session()->getId());

        return $response;
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'password' => 'required|string|min:6',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string',
            'role' => 'nullable|in:admin,customer',
            'license_key' => 'nullable|string',
        ]);

        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // Rate limiting for registration
        $key = 'register:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['success' => false, 'message' => 'Too many registration attempts. Try again later.']);
        }

        // Determine role - default is customer
        $role = $request->role ?? 'customer';

        // If admin role requested, validate license key
        if ($role === 'admin') {
            $licenseKey = $request->license_key;

            if (!$licenseKey) {
                return response()->json(['success' => false, 'message' => 'License key is required for admin registration.']);
            }

            // Check license key in database
            $license = DB::table('license_keys')
                ->where('email', $request->email)
                ->where('license_key', $licenseKey)
                ->where('used', false)
                ->first();

            if (!$license) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired license key for this email.']);
            }

            // Check if license is expired
            if ($license->expires_at && now()->gt($license->expires_at)) {
                return response()->json(['success' => false, 'message' => 'License key has expired.']);
            }

            // Mark license as used
            DB::table('license_keys')
                ->where('id', $license->id)
                ->update([
                    'used' => true,
                    'used_at' => now()
                ]);
        }

        // Create username from email (before @)
        $username = explode('@', $request->email)[0];

        // Check if username exists, append number if needed
        $baseUsername = $username;
        $counter = 1;
        while (DB::table('users')->where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        try {
            DB::table('users')->insert([
                'username' => $username,
                'password' => Hash::make($request->password),
                'email' => $request->email,
                'role' => $role,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }

        try {
            DB::table('registration_attempts')->insert([
                'ip_address' => $ip,
                'email' => $request->email,
                'success' => true,
                'user_agent' => $userAgent,
            ]);
        } catch (\Exception $e) {
            // Table might not exist, ignore
            Log::warning('Registration attempts table error: ' . $e->getMessage());
        }

        RateLimiter::clear($key);

        return response()->json(['success' => true, 'message' => 'Registration successful. Please login with your email.']);
    }

    /**
     * Generate a new license key for admin registration
     */
    public function generateLicenseKey(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'expires_days' => 'nullable|integer|min:1|max:365',
        ]);

        // Generate a unique license key
        $licenseKey = strtoupper(bin2hex(random_bytes(16)));
        $formattedKey = implode('-', str_split($licenseKey, 8));

        // Check if license already exists for this email
        $existing = DB::table('license_keys')->where('email', $request->email)->first();

        if ($existing && !$existing->used) {
            return response()->json([
                'success' => false,
                'message' => 'An active license key already exists for this email.'
            ]);
        }

        // If used, delete the old one
        if ($existing) {
            DB::table('license_keys')->where('id', $existing->id)->delete();
        }

        $expiresAt = $request->expires_days ? now()->addDays($request->expires_days) : null;

        DB::table('license_keys')->insert([
            'email' => $request->email,
            'license_key' => $formattedKey,
            'used' => false,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'License key generated successfully.',
            'license_key' => $formattedKey,
            'email' => $request->email,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s')
        ]);
    }

    public function logout(Request $request)
    {
        Session::flush();
        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function unlockAccount(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
        ]);

        $user = DB::table('users')->where('username', $request->username)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.']);
        }

        DB::table('users')->where('id', $user->id)->update([
            'account_locked' => false,
            'failed_attempts' => 0,
        ]);

        return response()->json(['success' => true, 'message' => 'Account unlocked.']);
    }

    public function check(Request $request)
    {
        $userId = Session::get('user_id');

        // Debug logging
        Log::info('Auth check called', [
            'user_id' => $userId,
            'session_id' => $request->session()->getId(),
            'all_session' => Session::all()
        ]);        if (!$userId) {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Not authenticated',
                'debug' => [
                    'session_id' => $request->session()->getId(),
                    'has_session' => $request->hasSession()
                ]
            ]);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            Session::forget('user_id');
            Session::forget('user_role');
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'User not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    public function user(Request $request)
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated'
            ]);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'full_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'phone' => $user->phone ?? '',
            ]
        ]);
    }

    /**
     * Verify 2FA code during login
     */
    public function verify2FALogin(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $pendingUserId = Session::get('pending_2fa_user_id');
        $pendingTimestamp = Session::get('pending_2fa_timestamp');

        if (!$pendingUserId || !$pendingTimestamp) {
            return response()->json(['success' => false, 'message' => '2FA session expired. Please login again.']);
        }

        // Check if 2FA session expired (5 minutes)
        if (now()->timestamp - $pendingTimestamp > 300) {
            Session::forget('pending_2fa_user_id');
            Session::forget('pending_2fa_timestamp');
            return response()->json(['success' => false, 'message' => '2FA session expired. Please login again.']);
        }

        $user = DB::table('users')->where('id', $pendingUserId)->first();

        if (!$user || !$user->two_factor_secret) {
            return response()->json(['success' => false, 'message' => 'Invalid 2FA configuration.']);
        }

        // Verify the TOTP code
        try {
            $totp = TOTP::create($user->two_factor_secret);
            $totp->setLabel($user->email);
            $totp->setIssuer('Muneeb Drug House');

            if (!$totp->verify($request->code, null, 1)) {
                return response()->json(['success' => false, 'message' => 'Invalid verification code.']);
            }
        } catch (\Exception $e) {
            Log::error('2FA verification error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Verification failed.']);
        }

        // Clear pending 2FA session
        Session::forget('pending_2fa_user_id');
        Session::forget('pending_2fa_timestamp');

        // Complete login
        $request->session()->regenerate();
        Session::put('user_id', $user->id);
        Session::put('username', $user->username);
        Session::put('role', $user->role);
        $request->session()->save();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
            'redirect' => $user->role === 'admin' ? '/modules/dashboard/index.html' : '/modules/frontend/index.html'
        ]);
    }

    /**
     * Get 2FA status for current user
     */
    public function get2FAStatus(Request $request)
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated']);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        return response()->json([
            'success' => true,
            'enabled' => (bool) ($user->two_factor_enabled && $user->two_factor_confirmed_at)
        ]);
    }

    /**
     * Setup 2FA - generate secret and QR code
     */
    public function setup2FA(Request $request)
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated']);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        try {
            // Generate new secret
            $totp = TOTP::create();
            $totp->setLabel($user->email);
            $totp->setIssuer('Muneeb Drug House');

            $secret = $totp->getSecret();

            // Store secret temporarily (not confirmed yet)
            DB::table('users')->where('id', $userId)->update([
                'two_factor_secret' => $secret,
                'two_factor_enabled' => false,
                'two_factor_confirmed_at' => null,
            ]);

            // Generate QR code as data URL
            $qrUri = $totp->getProvisioningUri();

            // Create QR code
            $renderer = new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrSvg = $writer->writeString($qrUri);
            $qrDataUrl = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

            return response()->json([
                'success' => true,
                'secret' => $secret,
                'qr_code' => $qrDataUrl
            ]);
        } catch (\Exception $e) {
            Log::error('2FA setup error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to setup 2FA: ' . $e->getMessage()]);
        }
    }

    /**
     * Verify and enable 2FA
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated']);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user->two_factor_secret) {
            return response()->json(['success' => false, 'message' => 'Please setup 2FA first.']);
        }

        try {
            $totp = TOTP::create($user->two_factor_secret);
            $totp->setLabel($user->email);
            $totp->setIssuer('Muneeb Drug House');

            if (!$totp->verify($request->code, null, 1)) {
                return response()->json(['success' => false, 'message' => 'Invalid verification code.']);
            }

            // Enable 2FA
            DB::table('users')->where('id', $userId)->update([
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '2FA enabled successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('2FA verify error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Verification failed.']);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated']);
        }

        DB::table('users')->where('id', $userId)->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => '2FA disabled successfully.'
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated']);
        }

        DB::table('users')->where('id', $userId)->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.'
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        $userId = Session::get('user_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated']);
        }

        $user = DB::table('users')->where('id', $userId)->first();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.']);
        }

        DB::table('users')->where('id', $userId)->update([
            'password' => Hash::make($request->new_password),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.'
        ]);
    }
}
