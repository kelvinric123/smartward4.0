<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivity;
use App\Models\Ward;
use App\Models\WardRosterShareLink;
use App\Services\NurseScheduling\ScheduleSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * View-only links to a ward's AI Nurse Schedule for staff without a SmartWard
 * sign-in (many nurses use only the nurse app, and their web accounts are
 * switched off): the roster, a duty list, the bed assignment, patient acuity
 * or workload, as the manager chose when making the link.
 *
 * The token in the link is the access. Only its hash is looked up, and a link
 * stops working when it expires, is revoked or its ward is deactivated. An
 * unknown link and one that stopped are the same 404, so trying tokens tells
 * nobody which ones exist.
 *
 * The page is a public ScheduleSnapshot: patients as initials or the bed
 * only, never an MRN or an indicator, leave without its type, no notes or
 * requests, and no links into the app.
 */
class AiNurseScheduleShareController extends Controller
{
    /** The schedule page's tabs, to go back to the one the dialog was opened on. */
    private const TABS = ['roster', 'acuity', 'assign', 'workload', 'leave'];

    /** Sent with the shared page and its 404: never cached or indexed, and the link never passed on as a referrer. */
    private const HEADERS = [
        'Cache-Control' => 'no-store, private',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
    ];

    /** The page asks for this header when it refreshes itself, which is not a new view. */
    public const REFRESH_HEADER = 'X-Shared-Refresh';

    public function store(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            // Only a ward the schedule page lists: an inactive ward's links could not be seen or revoked there
            'ward_id' => ['required', Rule::exists('wards', 'id')->where('is_active', true)],
            'week' => 'required|date',
            'label' => 'nullable|string|max:100',
            'sections' => 'required|array|min:1',
            'sections.*' => ['string', Rule::in(array_keys(WardRosterShareLink::SECTIONS))],
            'week_mode' => ['required', Rule::in(WardRosterShareLink::WEEK_MODES)],
            'expires_in' => ['required', 'integer', Rule::in(WardRosterShareLink::EXPIRY_DAYS)],
            'names' => ['required', Rule::in(array_keys(WardRosterShareLink::NAME_MODES))],
        ], [
            'ward_id.exists' => 'Links can only be made for an active ward.',
            'sections.required' => 'Tick at least one part of the schedule for the link to show.',
            'names.in' => 'A view-only link shows patients by bed only or by initials, never by full name.',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $weekStart = $this->weekStart($validated['week']);
        $fixed = $validated['week_mode'] === WardRosterShareLink::WEEK_FIXED;
        $label = trim((string) ($validated['label'] ?? ''));

        [$link] = WardRosterShareLink::issue($ward, [
            'label' => $label !== '' ? $label : null,
            'sections' => array_values(array_intersect(array_keys(WardRosterShareLink::SECTIONS), $validated['sections'])),
            'week_mode' => $validated['week_mode'],
            'week_start' => $fixed ? $weekStart->toDateString() : null,
            'names' => $validated['names'],
            'expires_at' => now()->addDays((int) $validated['expires_in']),
            'created_by' => Auth::id(),
            'created_by_name' => Auth::user()?->name,
        ]);

        $this->log($link, 'Created a view-only link to the ' . $ward->ward_name . ' AI Nurse Schedule: ' . $link->title());

        return $this->backTo($ward, $weekStart, $request->input('tab'))
            ->with('share_created', $link->id)
            ->with('success', 'View-only link created for ' . $ward->ward_name . ', open until '
                . $link->expires_at->format('D j M Y, H:i') . '. Copy it or show its QR code from the Share dialog.');
    }

    /** Turn a link off now. Whoever opens it afterwards is told it has expired. */
    public function revoke(Request $request, WardRosterShareLink $share)
    {
        $this->authorizeEdit();

        if (!$share->revoked_at) {
            $share->forceFill(['revoked_at' => now(), 'revoked_by' => Auth::id()])->save();
            $this->log($share, 'Revoked a view-only link to the ' . ($share->ward?->ward_name ?? 'ward') . ' AI Nurse Schedule: ' . $share->title());
        }

        return $this->backTo($share->ward ?? $this->wardFrom($request), $this->weekStart($request->input('week')), $request->input('tab'))
            ->with('share_open', true)
            ->with('success', 'Link revoked: ' . $share->title() . '. Anyone who opens it now is told it has expired.');
    }

    /**
     * The shared page itself: no sign-in, the token is the access. A link to a
     * ward that has been deactivated stops too: the schedule page no longer
     * lists that ward, so nobody could see or revoke its links there.
     */
    public function show(Request $request, string $token)
    {
        $link = WardRosterShareLink::findActive($token);

        if (!$link || !$link->ward || !$link->ward->is_active) {
            return response()->view('wards.ai-schedule.shared-unavailable', [], 404)->withHeaders(self::HEADERS);
        }

        // Next week only on a link that follows the current week; a link for one week shows just that week
        $next = $link->isRolling() && $request->query('week') === 'next';
        $sections = $link->sectionKeys();

        if (!$request->headers->has(self::REFRESH_HEADER)) {
            $link->recordView();
        }

        $snapshot = ScheduleSnapshot::build($link->ward, $link->weekStart($next), [
            'public' => true,
            'names' => $link->names,
            'weeks' => 1,
            'day' => $request->query('day'),
            'shift' => $request->query('shift'),
            // The roster's staffing rows show the registered nurses the patients need, which is acuity too
            'acuity' => in_array('acuity', $sections, true) || in_array('roster', $sections, true),
            'assign' => in_array('assign', $sections, true),
        ]);

        return response()->view('wards.ai-schedule.shared', [
            'link' => $link,
            'snapshot' => $snapshot,
            'sections' => $sections,
            'next' => $next,
            'pageUrl' => route('ward.ai-schedule.shared', $token),
        ])->withHeaders(self::HEADERS);
    }

    private function log(WardRosterShareLink $link, string $description): void
    {
        UserActivity::create([
            'user_id' => Auth::id(),
            'activity_type' => 'roster_share',
            'description' => $description,
            'properties' => [
                'share_link_id' => $link->id,
                'ward_id' => $link->ward_id,
                'sections' => $link->sections,
                'week_mode' => $link->week_mode,
                'week_start' => $link->week_start?->toDateString(),
                'names' => $link->names,
                'expires_at' => $link->expires_at?->toDateTimeString(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /** The same rule as the schedule page: nurses can view it but not change it. */
    private function authorizeEdit(): void
    {
        abort_if(Auth::user()?->hasRole(User::ROLE_NURSE), 403, 'Nurses can view the AI Nurse Schedule but not change it.');
    }

    private function weekStart(mixed $week): Carbon
    {
        try {
            $date = is_string($week) && $week !== '' ? Carbon::parse($week) : now();
        } catch (\Throwable) {
            $date = now();
        }

        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    private function wardFrom(Request $request): Ward
    {
        return Ward::find((int) $request->input('ward_id')) ?? Ward::where('is_active', true)->orderBy('ward_name')->firstOrFail();
    }

    private function backTo(Ward $ward, Carbon $weekStart, mixed $tab): RedirectResponse
    {
        return redirect()->route('ward.ai-schedule', [
            'ward_id' => $ward->id,
            'week' => $weekStart->toDateString(),
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'roster',
        ]);
    }
}
