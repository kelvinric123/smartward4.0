<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Update the application settings and logos (Super Admin only).
     */
    public function updateLogos(Request $request): RedirectResponse
    {
        if (!$request->user()->isSuperadmin()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'login_logo' => ['nullable', 'image', 'max:2048'],
            'navbar_logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $hospital = \App\Models\Hospital::first();
        if (!$hospital) {
            $hospital = \App\Models\Hospital::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'is_active' => true,
            ]);
        } else {
            $hospital->name = $request->input('name');
            $hospital->description = $request->input('description');
        }

        if ($request->hasFile('login_logo')) {
            if ($hospital->login_logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($hospital->login_logo_path);
            }
            $hospital->login_logo_path = $request->file('login_logo')->store('logos', 'public');
        }

        if ($request->hasFile('navbar_logo')) {
            if ($hospital->navbar_logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($hospital->navbar_logo_path);
            }
            $hospital->navbar_logo_path = $request->file('navbar_logo')->store('logos', 'public');
        }

        $hospital->save();

        return Redirect::route('profile.edit')->with('status', 'logos-updated');
    }
}
