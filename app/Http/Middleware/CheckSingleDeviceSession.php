<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSingleDeviceSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Don't intercept when user is already attempting to log out
        if ($request->is('logout') || $request->routeIs('logout')) {
            return $next($request);
        }

        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Only enforce single-device active session policy for students (murid)
            if ($user->isStudent()) {
                $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

                // 1. If active_session_id was explicitly reset by teacher/admin ('RESET')
                // OR if active_session_id belongs to another session (concurrent login takeover):
                if ($user->active_session_id === 'RESET' || 
                    (! empty($user->active_session_id) && $user->active_session_id !== $currentSessionId)) {
                    
                    Auth::guard('web')->logout();

                    if ($request->hasSession()) {
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    }

                    $logoutMessage = 'Sesi Anda telah berakhir karena akun dibuka di perangkat lain atau telah di-reset oleh pengawas/guru.';

                    return redirect()->route('login')
                        ->with('status', $logoutMessage)
                        ->withErrors([
                            'email' => $logoutMessage,
                        ]);
                }

                // 2. If active_session_id is not set yet (e.g. fresh login or test actingAs), bind it now
                if (empty($user->active_session_id) && $currentSessionId) {
                    $user->updateQuietly([
                        'active_session_id' => $currentSessionId,
                        'active_device_ip' => $request->ip(),
                        'active_device_agent' => substr((string) $request->userAgent(), 0, 255),
                        'last_activity_at' => now(),
                    ]);
                } elseif (! $user->last_activity_at || $user->last_activity_at->diffInMinutes(now()) >= 1) {
                    // Throttled touch to avoid DB write spamming on high-frequency requests
                    $user->updateQuietly([
                        'last_activity_at' => now(),
                    ]);
                }
            }
        }

        return $next($request);
    }
}
