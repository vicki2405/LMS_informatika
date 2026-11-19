<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    /**
     * Authenticate user and issue API Token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->email;
        $user = User::with('roles')
            ->where(function ($query) use ($loginInput) {
                $query->where('email', $loginInput)
                    ->orWhere('username', $loginInput);
            })
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial email atau password tidak valid',
            ], 401);
        }

        $role = $user->roles->first()?->name;
        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum memiliki peran aktif dalam sistem. Hubungi administrator.',
            ], 403);
        }

        // Enforce single-device active session for students
        if ($user->isStudent() && $user->isDeviceSessionActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang aktif di perangkat lain. Harap logout dari perangkat sebelumnya atau hubungi pengawas/guru untuk me-reset login perangkat Anda.',
            ], 422);
        }

        if ($user->isStudent()) {
            $user->update([
                'active_device_ip' => $request->ip(),
                'active_device_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_activity_at' => now(),
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login API Berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $role,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles');
        $role = $user->roles->first()?->name;

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak memiliki peran aktif.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $role,
            ],
        ]);
    }

    /**
     * Revoke token / logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isStudent()) {
            $user->resetDeviceSession();
        }

        $user?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout API Berhasil',
        ]);
    }
}
