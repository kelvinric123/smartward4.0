<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role_filter')) {
            $query->where('role', $request->role_filter);
        }

        // Sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // Validate sort field
        $allowedSortFields = ['name', 'email', 'role'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }

        // Validate sort direction
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate(10)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $integration = $request->input('role') === User::ROLE_INTEGRATION;

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            // An Integration User signs in with an API token, never a password
            'password' => $integration ? ['nullable'] : ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::in(array_keys(User::getRoles()))],
        ]);

        if ($request->role === User::ROLE_SUPERADMIN) {
            // Redundant check if UI filters it out, but good for security
            // Allow superadmin to create other superadmins? Requirement said "Only seeded by seeder".
            // Let's prevent creating superadmin via UI for now as per requirement.
            return back()->withErrors(['role' => 'Superadmin cannot be created manually.']);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($integration ? Str::random(64) : $request->password),
            'role' => $request->role,
        ]);

        if ($integration) {
            return redirect()->route('users.edit', $user)
                ->with('success', 'Integration user created. Generate its API token below.');
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::getRoles()))],
        ]);

        // Password update is optional
        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $user->password = Hash::make($request->password);
        }

        if ($request->role === User::ROLE_SUPERADMIN && !$user->isSuperadmin()) {
            return back()->withErrors(['role' => 'Cannot promote to Superadmin manually.']);
        }

        if ($user->isSuperadmin() && $request->role !== User::ROLE_SUPERADMIN) {
            // Prevent demoting superadmin? Or maybe allow? 
            // Requirement says "Superadmin-only seeded by seeder". 
            // Let's assume seeded superadmins should stay superadmins, but maybe we can edit basic info.
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->save();
        $this->dropTokenUnlessIntegration($user);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', Rule::in(array_keys(User::getRoles()))],
        ]);

        // Prevent superadmin role manipulation
        if ($request->role === User::ROLE_SUPERADMIN && !$user->isSuperadmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot promote to Superadmin manually.'
            ], 403);
        }

        if ($user->isSuperadmin() && $request->role !== User::ROLE_SUPERADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot demote Superadmin.'
            ], 403);
        }

        // Prevent changing your own role
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change your own role.'
            ], 403);
        }

        $oldRole = User::getRoles()[$user->role] ?? $user->role;
        $user->role = $request->role;
        $user->save();
        $this->dropTokenUnlessIntegration($user);

        $newRole = User::getRoles()[$user->role] ?? $user->role;

        return response()->json([
            'success' => true,
            'message' => "Role changed from {$oldRole} to {$newRole}",
            'role' => $user->role,
            'role_display' => $newRole
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->isSuperadmin()) {
            return back()->with('error', 'Cannot delete Superadmin.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        if ($user->isSuperadmin()) {
            return back()->with('error', 'Cannot deactivate Superadmin.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Cannot deactivate yourself.');
        }

        if ($user->isActive()) {
            $user->update(['deactivated_at' => now()]);
            $message = 'User deactivated successfully.';
        } else {
            $user->update(['deactivated_at' => null]);
            $message = 'User activated successfully.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * A new API token for an Integration User, replacing the old one. It is
     * shown once on the edit page; only its hash is kept.
     */
    public function generateApiToken(User $user)
    {
        if (! $user->isIntegration()) {
            return back()->with('error', 'Only Integration Users have an API token.');
        }

        $token = $user->generateApiToken();

        return redirect()->route('users.edit', $user)
            ->with('api_token', $token)
            ->with('success', 'New API token generated. Copy it now: it will not be shown again. Any old token no longer works.');
    }

    public function revokeApiToken(User $user)
    {
        $user->revokeApiToken();

        return redirect()->route('users.edit', $user)->with('success', 'API token revoked. Calls with it are now refused.');
    }

    private function dropTokenUnlessIntegration(User $user): void
    {
        if (! $user->isIntegration() && $user->hasApiToken()) {
            $user->revokeApiToken();
        }
    }
}
