<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Display the user management page.
     */
    public function index(): Response
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return Inertia::render('admin/users/index', [
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'approval_status' => $user->approval_status,
                    'approved_at' => $user->approved_at,
                    'rejected_at' => $user->rejected_at,
                    'rejection_reason' => $user->rejection_reason,
                    'created_at' => $user->created_at,
                ];
            }),
        ]);
    }



    /**
     * Approve a user.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorize('approve', $user);

        $user->approve();

        return redirect()->back()->with('success', 'User approved successfully');
    }

    /**
     * Reject a user.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->authorize('reject', $user);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user->reject($request->reason);

        return redirect()->back()->with('success', 'User rejected successfully');
    }

    /**
     * Toggle admin status.
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleAdmin', $user);

        $user->update(['is_admin' => !$user->is_admin]);

        return redirect()->back()->with('success', 'Admin status updated successfully');
    }
}