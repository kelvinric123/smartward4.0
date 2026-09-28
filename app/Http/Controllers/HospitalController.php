<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\User;
use App\Support\HospitalTheme;
use App\Support\NavigationMenu;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;

class HospitalController extends Controller implements HasMiddleware
{
    /**
     * The same roles the sidebar shows the Hospital page to: its theme and menu
     * settings change the app for everyone.
     */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                $user = $request->user();
                abort_unless(
                    $user->isSuperadmin() || $user->hasRole(User::ROLE_HOSPITAL_ADMIN) || $user->hasRole(User::ROLE_IT_ADMIN),
                    403
                );

                return $next($request);
            },
        ];
    }

    public function index()
    {
        $hospitals = Hospital::latest()->paginate(10);
        return view('admin.hospitals.index', compact('hospitals'));
    }

    public function create()
    {
        return view('admin.hospitals.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('hospitals', 'public');
        }

        $validated['is_active'] = true;
        Hospital::create($validated);

        return redirect()->route('hospitals.index')->with('success', 'Hospital created successfully.');
    }

    public function edit(Hospital $hospital)
    {
        return view('admin.hospitals.edit', [
            'hospital' => $hospital,
            'themeColours' => HospitalTheme::colours($hospital),
            'menu' => NavigationMenu::for($hospital),
        ]);
    }

    public function update(Request $request, Hospital $hospital)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'theme_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'menu' => 'sometimes|array',
            'menu.*' => 'boolean',
        ], [
            'theme_primary_color.regex' => 'Choose the primary colour as a hex code such as #2563eb.',
            'theme_secondary_color.regex' => 'Choose the secondary colour as a hex code such as #06b6d4.',
        ]);

        if ($request->hasFile('logo')) {
            if ($hospital->logo_path) {
                Storage::disk('public')->delete($hospital->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('hospitals', 'public');
        }

        if (isset($validated['theme_primary_color'], $validated['theme_secondary_color'])) {
            // The default colours are stored as none, so the app keeps Tailwind's exact blue/cyan palettes
            $primary = strtolower($validated['theme_primary_color']);
            $secondary = strtolower($validated['theme_secondary_color']);
            $isDefault = $primary === HospitalTheme::DEFAULT_PRIMARY && $secondary === HospitalTheme::DEFAULT_SECONDARY;
            $validated['theme_primary_color'] = $isDefault ? null : $primary;
            $validated['theme_secondary_color'] = $isDefault ? null : $secondary;
        } else {
            // The colours only change as a pair
            unset($validated['theme_primary_color'], $validated['theme_secondary_color']);
        }

        if (isset($validated['menu'])) {
            $validated['hidden_nav_items'] = NavigationMenu::hiddenFrom($validated['menu']);
            unset($validated['menu']);
        }

        $hospital->update($validated);

        return redirect()
            ->route('hospitals.edit', ['hospital' => $hospital, 'tab' => $request->input('tab') === 'theme' ? 'theme' : null])
            ->with('success', 'Hospital updated successfully.');
    }

    public function deactivate(Hospital $hospital)
    {
        $hospital->update(['is_active' => !$hospital->is_active]);
        $status = $hospital->is_active ? 'activated' : 'deactivated';
        return redirect()->route('hospitals.index')->with('success', "Hospital {$status} successfully.");
    }

    public function destroy(Hospital $hospital)
    {
        $hospital->delete();
        return redirect()->route('hospitals.index')->with('success', 'Hospital deleted successfully.');
    }
}
