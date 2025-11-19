<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'statusType' => $request->session()->get('status_type'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = auth()->user();
        
        // Check if user is approved
        if (!$user->isApproved()) {
            auth()->logout();
            return redirect()->route('login')
                ->with('status', 'Your account is pending approval. Please wait for an administrator to approve your registration.')
                ->with('status_type', 'warning');
        }

        // Check if user is rejected
        if ($user->isRejected()) {
            auth()->logout();
            return redirect()->route('login')
                ->with('status', 'Your registration has been rejected. ' . ($user->rejection_reason ? 'Reason: ' . $user->rejection_reason : ''))
                ->with('status_type', 'error');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
