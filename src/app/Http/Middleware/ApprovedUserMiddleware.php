<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedUserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->isApproved()) {
            auth()->logout();
            return redirect()->route('login')
                ->with('status', 'Your account is pending approval. Please wait for an administrator to approve your registration.')
                ->with('status_type', 'warning');
        }

        if ($user->isRejected()) {
            auth()->logout();
            return redirect()->route('login')
                ->with('status', 'Your registration has been rejected. ' . ($user->rejection_reason ? 'Reason: ' . $user->rejection_reason : ''))
                ->with('status_type', 'error');
        }

        return $next($request);
    }
}