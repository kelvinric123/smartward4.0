<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use App\Models\NursePrivilege;
use App\Models\Ward;
use App\Support\NurseCredentialing;
use App\Support\NursePrivilegeCatalogue;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Credentialing and privileging across a ward's nurses, for the
 * credentialing and privileging printout: each nurse's APC, registration,
 * certificates and privileges, the ward's figures, and a list of what to act
 * on, most urgent first. Staffing matches nurses to patients on skill mix,
 * education and competencies (ANA, 2017, pp. 3-4); this is the ward's picture
 * of them.
 *
 * Each nurse's file is read with NurseCredentialing::forNurse(), as the nurse
 * edit page reads it. Only "expiring" differs: the printout asks how far
 * ahead to look ($window days) where the nurse's page always looks 90 days.
 *
 * This is staff data: only those who may change the schedule print it (not
 * nurses, nor the shared ward screen login), and nothing
 * here identifies a nurse beyond their name and designation. No IC, phone,
 * email, credential reference numbers or notes; credential titles, types,
 * dates and statuses only. Plain arrays out, no models.
 */
final class CredentialReport
{
    public const WINDOWS = [30, 60, 90, 180];

    /**
     * What the action list can hold, most urgent first: what is wrong now,
     * then what is coming, then the paperwork. "todo" is what to do about it.
     */
    public const ACTIONS = [
        'expired' => ['label' => 'Expired', 'todo' => 'Renew it and record the new one; check what the nurse may do meanwhile'],
        'apc_missing' => ['label' => 'No APC on file', 'todo' => 'Sight this year\'s APC and record it'],
        'missing' => ['label' => 'Not on file', 'todo' => 'Book the course, or sight the certificate and record it'],
        'at_risk' => ['label' => 'Privilege at risk', 'todo' => 'Supervise or suspend the privilege until the certificate is on file and in date'],
        'review_overdue' => ['label' => 'Review overdue', 'todo' => 'Reassess competence, then renew or change the privilege'],
        'expiring' => ['label' => 'Expiring', 'todo' => 'Book the renewal before it runs out'],
        'review_due' => ['label' => 'Review due', 'todo' => 'Plan the competency review'],
        'unverified' => ['label' => 'Not verified', 'todo' => 'Sight the original and mark it verified'],
    ];

    /**
     * Certificates every nurse on the list needs, so every column set starts
     * with them and a nurse without one is on the action list; the others show
     * when a nurse has one or a privilege they hold needs it.
     */
    public const ALWAYS_SHOWN = ['bls'];

    public static function build(Ward $ward, CarbonInterface $today, int $window): array
    {
        $today = Carbon::instance($today)->copy()->startOfDay();
        $window = in_array($window, self::WINDOWS, true) ? $window : 90;

        $nurses = self::team($ward, $today)->map(fn (Nurse $nurse) => self::nurse($nurse, $today, $window))->values();

        // Certificate columns: the ones always shown, and any a nurse here has or needs
        $certificates = collect(array_keys(NursePrivilegeCatalogue::CERTIFICATES))
            ->filter(fn (string $key) => in_array($key, self::ALWAYS_SHOWN, true)
                || $nurses->contains(fn (array $nurse) => $nurse['certificates'][$key]['status'] !== 'missing' || $nurse['certificates'][$key]['needed']))
            ->mapWithKeys(fn (string $key) => [$key => NursePrivilegeCatalogue::CERTIFICATES[$key]['label']])
            ->all();

        $actionOrder = array_flip(array_keys(self::ACTIONS));
        $actions = $nurses->flatMap(fn (array $nurse) => $nurse['actions'])
            ->sort(fn (array $a, array $b) => [$actionOrder[$a['type']], $a['days'] ?? PHP_INT_MAX, mb_strtolower($a['nurse'])]
                <=> [$actionOrder[$b['type']], $b['days'] ?? PHP_INT_MAX, mb_strtolower($b['nurse'])])
            ->values();

        $registered = $nurses->where('registered', true);
        $inDate = fn (?array $status) => in_array($status['status'] ?? null, ['valid', 'expiring'], true);
        $percent = fn (int $part, int $whole) => $whole ? (int) round($part / $whole * 100) : null;
        $designations = array_flip(array_map('mb_strtolower', Nurse::DESIGNATIONS));

        return [
            'today' => $today,
            'window' => $window,
            'certificates' => $certificates,
            'actions_meta' => self::ACTIONS,
            'nurses' => $nurses->map(fn (array $nurse) => collect($nurse)->except('actions')->all())->all(),
            'actions' => $actions->all(),
            'summary' => [
                'nurses' => $nurses->count(),
                'registered' => $registered->count(),
                'tagging' => $nurses->where('tagging', true)->count(),
                'apc_valid' => $registered->filter(fn (array $nurse) => $inDate($nurse['apc']))->count(),
                'apc_valid_percent' => $percent($registered->filter(fn (array $nurse) => $inDate($nurse['apc']))->count(), $registered->count()),
                'bls_valid' => $nurses->filter(fn (array $nurse) => $inDate($nurse['certificates']['bls']))->count(),
                'bls_valid_percent' => $percent($nurses->filter(fn (array $nurse) => $inDate($nurse['certificates']['bls']))->count(), $nurses->count()),
                'critical_care' => $nurses->filter(fn (array $nurse) => $inDate($nurse['certificates']['critical_care']))->count(),
                'actions' => collect(self::ACTIONS)->map(fn (array $meta, string $type) => $actions->where('type', $type)->count())->all(),
                'nurses_to_act_on' => $actions->pluck('nurse_id')->unique()->count(),
                // Skill mix: the hospital's designations in their order, then any others
                'skill_mix' => $nurses->countBy(fn (array $nurse) => $nurse['designation'] ?: 'No designation')
                    ->sortBy(fn (int $count, string $designation) => [$designations[mb_strtolower($designation)] ?? PHP_INT_MAX, $designation])
                    ->all(),
            ],
        ];
    }

    /**
     * The ward's team (WardTeam) with its tagging nurses, who are in
     * orientation and whose credentials matter as much, and anyone rostered on
     * the ward this month.
     */
    public static function team(Ward $ward, CarbonInterface $today): Collection
    {
        $month = Carbon::instance($today)->copy();
        $team = LeaveReport::team($ward, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());
        $tagging = Nurse::where('ward_id', $ward->id)->where('is_active', true)->where('is_tagging', true)->get();

        return $team->concat($tagging)->unique('id')->sortBy(fn (Nurse $nurse) => mb_strtolower($nurse->name))->values();
    }

    /** One nurse's row, with their items for the action list. */
    private static function nurse(Nurse $nurse, Carbon $today, int $window): array
    {
        $file = NurseCredentialing::forNurse($nurse, $today);
        $current = $file['credentials']->reject(fn (array $row) => $row['superseded'])->values();
        $registered = AcuityStaffing::isRegisteredNurse($nurse);

        $needed = $file['privileges']
            ->filter(fn (array $row) => $row['privilege']->isActive())
            ->map(fn (array $row) => NursePrivilegeCatalogue::item($row['code'])['requires'] ?? null)
            ->filter()
            ->unique()
            ->all();

        // Needed by everyone (BLS), or by a privilege the nurse holds; a missing one prints as "Missing" either way
        $certificates = [];
        foreach (NursePrivilegeCatalogue::CERTIFICATES as $key => $meta) {
            $forPrivilege = in_array($key, $needed, true);
            $certificates[$key] = self::certificate($key, $file['certificates'][$key], $current, $window) + [
                'needed' => $forPrivilege || in_array($key, self::ALWAYS_SHOWN, true),
                'for_privilege' => $forPrivilege,
            ];
        }

        $apc = $file['apc'] ? self::standing($file['apc'], $window) : ['status' => 'missing', 'expires' => null, 'days' => null, 'title' => null];

        $privileges = $file['privileges']->map(function (array $row) use ($window) {
            /** @var NursePrivilege $privilege */
            $privilege = $row['privilege'];
            $days = $row['review']['days'] ?? null;

            return [
                'name' => $privilege->name,
                'category' => NursePrivilege::CATEGORIES[$privilege->category]['label'] ?? ucfirst((string) $privilege->category),
                'status' => $privilege->status,
                'status_label' => $privilege->statusLabel(),
                'active' => $privilege->isActive(),
                'review_on' => $privilege->review_on?->copy(),
                'review_days' => $days,
                'review' => match (true) {
                    $days === null => null,
                    $days < 0 => 'overdue',
                    $days <= $window => 'due',
                    default => 'ok',
                },
                'at_risk' => $row['at_risk'],
                'certificate' => $row['certificate']['chip'] ?? null,
            ];
        })->values();

        $row = [
            'id' => $nurse->id,
            'name' => $nurse->name,
            'designation' => $nurse->designation,
            'registered' => $registered,
            'tagging' => (bool) $nurse->is_tagging,
            'apc' => $apc,
            'registration' => $current->contains(fn (array $credential) => $credential['credential']->type === 'registration'),
            'certificates' => $certificates,
            'unverified' => $file['counts']['unverified'],
            'privilege_counts' => [
                'granted' => $file['counts']['granted'],
                'supervised' => $file['counts']['supervised'],
                'suspended' => $file['counts']['suspended'],
                'withdrawn' => $file['counts']['withdrawn'],
            ],
            'review_overdue' => $privileges->where('review', 'overdue')->count(),
            'review_due' => $privileges->where('review', 'due')->count(),
            'at_risk' => $file['counts']['at_risk'],
            'privileges' => $privileges->all(),
        ];

        $row['actions'] = self::actions($row, $current, $window);
        $row['attention'] = collect($row['actions'])->contains(fn (array $action) => in_array($action['type'], ['expired', 'apc_missing', 'missing', 'at_risk', 'review_overdue'], true))
            ? 'now'
            : (collect($row['actions'])->contains(fn (array $action) => in_array($action['type'], ['expiring', 'review_due'], true)) ? 'soon' : null);

        return $row;
    }

    /**
     * Where a certificate stands, from NurseCredentialing's pick of the best
     * credential for it, with "expiring" looking $window days ahead.
     */
    private static function certificate(string $key, array $status, Collection $current, int $window): array
    {
        if ($status['key'] === 'missing') {
            return ['status' => 'missing', 'expires' => null, 'days' => null, 'title' => null];
        }

        // The credential NurseCredentialing picked: the one it names, latest expiry first
        $row = $current
            ->filter(fn (array $row) => $row['credential']->title === ($status['title'] ?? null) && NursePrivilegeCatalogue::satisfies($key, $row['credential']))
            ->sortByDesc(fn (array $row) => $row['credential']->expires_on?->timestamp ?? PHP_INT_MAX)
            ->first();

        return $row
            ? self::standing($row, $window)
            : ['status' => $status['key'] === 'expired' ? 'expired' : 'valid', 'expires' => null, 'days' => null, 'title' => $status['title'] ?? null];
    }

    /** A credential row's standing: expired, expiring within $window days, or valid (no expiry is valid). */
    private static function standing(array $row, int $window): array
    {
        $days = $row['expiry']['days'];

        return [
            'status' => match (true) {
                $days === null => 'valid',
                $days < 0 => 'expired',
                $days <= $window => 'expiring',
                default => 'valid',
            },
            'expires' => $row['credential']->expires_on?->copy(),
            'days' => $days,
            'title' => $row['credential']->title,
        ];
    }

    /** What to act on for one nurse: each item with its date and the days to it (negative: since). */
    private static function actions(array $nurse, Collection $current, int $window): array
    {
        $item = fn (string $type, string $what, ?CarbonInterface $date, ?int $days, ?string $detail = null) => [
            'type' => $type,
            'nurse_id' => $nurse['id'],
            'nurse' => $nurse['name'],
            'designation' => $nurse['designation'],
            'item' => $what,
            'detail' => $detail,
            'date' => $date?->copy(),
            'days' => $days,
            'todo' => self::ACTIONS[$type]['todo'],
        ];
        $actions = [];

        foreach ($current as $row) {
            $credential = $row['credential'];
            $what = $credential->typeShort() . ': ' . $credential->title;
            $days = $row['expiry']['days'];
            if ($days !== null && $days < 0) {
                $actions[] = $item('expired', $what, $credential->expires_on, $days);
            } elseif ($days !== null && $days <= $window) {
                $actions[] = $item('expiring', $what, $credential->expires_on, $days);
            }
            if (!$credential->isVerified()) {
                $actions[] = $item('unverified', $what, $credential->expires_on, null);
            }
        }

        if ($nurse['registered'] && $nurse['apc']['status'] === 'missing') {
            $actions[] = $item('apc_missing', 'Annual Practising Certificate', null, null);
        }

        // A certificate everyone needs and this nurse lacks; one only a privilege needs is listed as the privilege at risk
        foreach (self::ALWAYS_SHOWN as $key) {
            if ($nurse['certificates'][$key]['status'] === 'missing') {
                $actions[] = $item('missing', NursePrivilegeCatalogue::CERTIFICATES[$key]['label'], null, null);
            }
        }

        foreach ($nurse['privileges'] as $privilege) {
            if ($privilege['at_risk']) {
                $actions[] = $item('at_risk', $privilege['name'], null, null, $privilege['certificate']);
            }
            if (in_array($privilege['review'], ['overdue', 'due'], true)) {
                $actions[] = $item($privilege['review'] === 'overdue' ? 'review_overdue' : 'review_due', $privilege['name'], $privilege['review_on'], $privilege['review_days']);
            }
        }

        return $actions;
    }
}
