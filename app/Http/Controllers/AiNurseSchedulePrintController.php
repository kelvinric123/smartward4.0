<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Ward;
use App\Services\NurseScheduling\CredentialReport;
use App\Services\NurseScheduling\LeaveReport;
use App\Services\NurseScheduling\ProductivityReport;
use App\Services\NurseScheduling\ScheduleSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Printouts of the AI Nurse Schedule: the weekly roster, a duty list per
 * nurse, a shift's bed assignment (a handover sheet), patient acuity and
 * staffing, workload and fairness, or all of them as one pack. And three
 * reports: monthly productivity, leave and holidays, and credentialing and
 * privileging.
 *
 * Every choice is in the query string and checked here, so a printout's URL
 * always gives the same printout. Nurses may print what they may view; the
 * data comes from ScheduleSnapshot, the same read-only copy the view-only
 * link uses, with patient names as initials unless full names are asked for.
 * The productivity and credentialing reports are about the staff, so only
 * those who may change the schedule print them, and not the shared ward
 * screen login; the same goes for the leave notes on the leave report.
 */
class AiNurseSchedulePrintController extends Controller
{
    /**
     * Each format: what it is, its paper orientation when left on auto, whether
     * it covers several weeks or one shift, whether it lists patients, and the
     * sections that can be switched on or off for it. A report also says
     * whether only editors may print it, and whether it takes a month, a
     * period (month, quarter or year) or a window ("expiring within N days").
     */
    public const FORMATS = [
        'roster' => [
            'label' => 'Weekly roster',
            'description' => 'The roster grid: every nurse by day, with staffing per shift.',
            'orientation' => 'landscape',
            'weeks' => true,
            'shift' => false,
            'patients' => false,
            'sections' => ['staffing', 'legend', 'leave_detail', 'signature'],
        ],
        'nurse' => [
            'label' => 'Duty list per nurse',
            'description' => 'Each nurse\'s shifts as a block of their own, to hand out or pin up.',
            'orientation' => 'portrait',
            'weeks' => true,
            'shift' => false,
            'patients' => false,
            'sections' => ['legend', 'leave_detail', 'signature'],
        ],
        'assign' => [
            'label' => 'Bed assignment',
            'description' => 'One shift\'s beds and nurses with each nurse\'s load: a handover sheet.',
            'orientation' => 'portrait',
            'weeks' => false,
            'shift' => true,
            'patients' => true,
            'sections' => ['staffing', 'legend', 'reasons', 'signature'],
        ],
        'acuity' => [
            'label' => 'Patient acuity & staffing',
            'description' => 'Patients by care level and the nurses each shift needs for them.',
            'orientation' => 'portrait',
            'weeks' => false,
            'shift' => false,
            'patients' => true,
            'sections' => ['staffing', 'legend', 'reasons', 'references', 'signature'],
        ],
        'workload' => [
            'label' => 'Workload & fairness',
            'description' => 'How evenly shifts, nights, weekends and holidays are shared.',
            'orientation' => 'landscape',
            'weeks' => true,
            'shift' => false,
            'patients' => false,
            'sections' => ['signature'],
        ],
        'pack' => [
            'label' => 'Full pack',
            'description' => 'Roster, patient acuity, bed assignment and workload, each on a new page.',
            'orientation' => 'landscape',
            'weeks' => false,
            'shift' => true,
            'patients' => true,
            'sections' => ['staffing', 'legend', 'reasons', 'references', 'leave_detail', 'signature'],
        ],
        'productivity' => [
            'label' => 'Monthly productivity report',
            'description' => 'Patients, the care hours they needed and the nurse hours rostered, day by day and per nurse.',
            'orientation' => 'landscape',
            'weeks' => false,
            'shift' => false,
            'patients' => false,
            'report' => true,
            'editors' => true,
            'month' => true,
            'sections' => ['daily', 'nurses', 'definitions', 'signature'],
        ],
        'leave' => [
            'label' => 'Leave & holiday report',
            'description' => 'Leave by nurse and type, who worked each public holiday, and the nurse app\'s requests.',
            'orientation' => 'landscape',
            'weeks' => false,
            'shift' => false,
            'patients' => false,
            'report' => true,
            'month' => true,
            'period' => true,
            'sections' => ['calendar', 'entries', 'leave_notes', 'requests', 'signature'],
        ],
        'credentials' => [
            'label' => 'Credentialing & privileging report',
            'description' => 'APC, certificates and privileges per nurse, and what has expired, runs out or is due for review.',
            'orientation' => 'landscape',
            'weeks' => false,
            'shift' => false,
            'patients' => false,
            'report' => true,
            'editors' => true,
            'window' => true,
            'sections' => ['matrix', 'actions', 'privileges', 'signature'],
        ],
    ];

    /** Sections that can be left out: label, a hint, and whether they are on unless switched off. */
    public const SECTIONS = [
        'staffing' => ['label' => 'Staffing per shift', 'hint' => 'Nurses on duty against the minimum and the patients\' needs', 'default' => true],
        'legend' => ['label' => 'Legend', 'hint' => 'What the codes and colours mean', 'default' => true],
        'reasons' => ['label' => 'Care needs per patient', 'hint' => 'The indicators behind each care level', 'default' => true],
        'references' => ['label' => 'Method and references', 'hint' => 'How SmartWard suggests levels and staffing, and the ANA (2017) references', 'default' => true],
        'leave_detail' => ['label' => 'Leave types', 'hint' => 'AL, MC and the other codes; off shows just "Leave"', 'default' => true],
        'daily' => ['label' => 'Day by day', 'hint' => 'Patients, care hours needed and nurse hours rostered each day', 'default' => true],
        'nurses' => ['label' => 'Per nurse', 'hint' => 'Shifts, hours, nights, weekends and holidays against the month\'s target', 'default' => true],
        'definitions' => ['label' => 'How the figures are worked out', 'hint' => 'What each figure means, with the ANA (2017) sources', 'default' => true],
        'calendar' => ['label' => 'Availability calendar', 'hint' => 'Nurses on leave and on duty each day', 'default' => true],
        'entries' => ['label' => 'Every leave', 'hint' => 'Each leave with its dates and who booked it', 'default' => true],
        'leave_notes' => ['label' => 'Leave notes', 'hint' => 'Notes can hold health details: leave off unless needed', 'default' => false],
        'requests' => ['label' => 'Requests from the nurse app', 'hint' => 'Leave and swap requests in the period, and what was decided', 'default' => true],
        'matrix' => ['label' => 'Compliance per nurse', 'hint' => 'APC, registration, certificates and privileges for each nurse', 'default' => true],
        'actions' => ['label' => 'Action list', 'hint' => 'What has expired, runs out, is at risk or is due for review', 'default' => true],
        'privileges' => ['label' => 'Privileges per nurse', 'hint' => 'Every privilege with its status and review date (long)', 'default' => false],
        'signature' => ['label' => 'Signature lines', 'hint' => 'Prepared, checked and approved by', 'default' => false],
    ];

    /** How far from this month a report's month may be. */
    public const MONTHS_AROUND = 24;

    public const PAPERS = ['A4', 'A3', 'Letter', 'Legal'];

    public const ORIENTATIONS = ['auto', 'landscape', 'portrait'];

    public const SIZES = ['compact', 'normal', 'large'];

    /** The format each tab of the schedule page prints by default. */
    public const TAB_FORMATS = [
        'roster' => 'roster',
        'acuity' => 'acuity',
        'assign' => 'assign',
        'workload' => 'workload',
        'leave' => 'roster',
    ];

    public function show(Request $request)
    {
        $ward = $this->wardFrom($request);
        $weekStart = $this->weekStart($request->input('week'));

        $format = $this->pick($request->input('format'), array_keys(self::FORMATS), 'roster');
        $spec = self::FORMATS[$format] + ['report' => false, 'editors' => false, 'month' => false, 'period' => false, 'window' => false];
        // Asked for by name, so refused by name: a nurse never gets another printout instead
        abort_if($spec['editors'] && !self::canPrintStaffReports(), 403, 'This report is about the staff: nurse managers and admins print it.');

        $paper = $this->pick($request->input('paper'), self::PAPERS, 'A4');
        $orientationChoice = $this->pick($request->input('orientation'), self::ORIENTATIONS, 'auto');
        $orientation = $orientationChoice === 'auto' ? $spec['orientation'] : $orientationChoice;
        $weeks = $spec['weeks'] ? (int) $this->pick($request->integer('weeks'), ScheduleSnapshot::WEEKS, 1) : 1;
        $names = $this->pick($request->input('names'), ScheduleSnapshot::NAME_MODES, 'initials');
        $size = $this->pick($request->input('size'), self::SIZES, 'normal');

        // Ticked sections only once the form has sent any; the defaults otherwise.
        // Sections that do not apply to the format are dropped.
        $sections = $request->has('sections')
            ? collect((array) $request->input('sections'))->filter(fn ($key) => is_string($key))->all()
            : array_keys(array_filter(self::SECTIONS, fn (array $section) => $section['default']));
        $sections = array_values(array_intersect($spec['sections'], $sections));
        // Leave notes can hold health details: the page shows nurses only recent ones, the print would give years of them
        if (!self::canPrintStaffReports()) {
            $sections = array_values(array_diff($sections, ['leave_notes']));
        }
        $on = fn (string $section) => in_array($section, $sections, true);

        // A report's own choices: its month, the period around it, how far ahead "expiring" looks
        $month = self::month($request->input('month'), $weekStart);
        $period = $this->pick($request->input('period'), LeaveReport::PERIODS, 'month');
        [$periodFrom, $periodTo] = LeaveReport::period($month, $period);
        $window = (int) $this->pick($request->integer('window'), CredentialReport::WINDOWS, 90);

        // A report needs only the header's hospital and ward from the snapshot
        $snapshot = ScheduleSnapshot::build($ward, $weekStart, [
            'weeks' => $weeks,
            'day' => is_string($request->input('day')) ? $request->input('day') : null,
            'shift' => $spec['shift'] ? $request->input('shift') : null,
            'names' => $names,
            'public' => false,
            'leave_detail' => $on('leave_detail'),
            // The roster's staffing rows show the nurses the patients need, which is acuity too
            'acuity' => in_array($format, ['acuity', 'pack'], true) || ($format === 'roster' && $on('staffing')),
            'assign' => in_array($format, ['assign', 'pack'], true),
        ]);

        $report = match ($format) {
            'productivity' => ProductivityReport::build($ward, $month),
            'leave' => LeaveReport::build($ward, $periodFrom, $periodTo, ['notes' => $on('leave_notes')]),
            'credentials' => CredentialReport::build($ward, now(), $window),
            default => null,
        };

        // The options as checked, so the paper and orientation switches reproduce everything else
        $query = array_filter([
            'ward_id' => $ward->id,
            'week' => $weekStart->toDateString(),
            'format' => $format,
            'paper' => $paper,
            'orientation' => $orientationChoice,
            'weeks' => $spec['weeks'] ? $weeks : null,
            'day' => $spec['shift'] ? $snapshot['assign']['day'] : null,
            'shift' => $spec['shift'] ? $snapshot['assign']['shift'] : null,
            'names' => $spec['patients'] ? $names : null,
            'month' => $spec['month'] ? $month->format('Y-m') : null,
            'period' => $spec['period'] ? $period : null,
            'window' => $spec['window'] ? $window : null,
            'sections' => $sections ?: ['none'],
            'size' => $size,
            'mono' => $request->boolean('mono') ? 1 : null,
        ]);

        return view('wards.ai-schedule.print', [
            'snapshot' => $snapshot,
            'report' => $report,
            'period' => $spec['period'] ? $period : null,
            'citation' => self::citation($format, $snapshot),
            'format' => $format,
            'formatLabel' => $spec['label'],
            'paper' => $paper,
            'orientation' => $orientation,
            'orientationChoice' => $orientationChoice,
            'size' => $size,
            'mono' => $request->boolean('mono'),
            'autoprint' => $request->boolean('autoprint'),
            'sections' => $sections,
            'names' => $names,
            'showsPatients' => $spec['patients'],
            'query' => $query,
            'printedBy' => Auth::user()?->name,
            'options' => [
                'mono' => $request->boolean('mono'),
                'staffing' => $on('staffing'),
                'legend' => $on('legend'),
                'show_reasons' => $on('reasons'),
                'references' => $on('references'),
                'leave_detail' => $on('leave_detail'),
                // Room to write on the handover sheet; the pack is for reading
                'write_in' => $format === 'assign',
                'compact' => true,
                // The reports' sections
                'daily' => $on('daily'),
                'nurses' => $on('nurses'),
                'definitions' => $on('definitions'),
                'calendar' => $on('calendar'),
                'entries' => $on('entries'),
                'leave_notes' => $on('leave_notes'),
                'requests' => $on('requests'),
                'matrix' => $on('matrix'),
                'actions' => $on('actions'),
                'privileges' => $on('privileges'),
            ],
        ]);
    }

    /**
     * Whether the signed-in user may print the staff reports (and leave
     * notes): the schedule's editors, so not role nurse, and not the shared
     * ward screen login anyone at the ward PC or TV is signed in as.
     */
    public static function canPrintStaffReports(): bool
    {
        $user = Auth::user();

        return $user !== null && !$user->hasAnyRole([User::ROLE_NURSE, User::ROLE_WARD_DASHBOARD]);
    }

    /**
     * A report's month from "Y-m", or the month the week shown belongs to: the
     * one its Thursday falls in, as ISO weeks are counted. A month that is not
     * one, or further than MONTHS_AROUND from now, is the default; a default
     * that far off (from a week far off) is this month.
     */
    public static function month(mixed $value, Carbon $weekStart): Carbon
    {
        $now = now();
        $away = fn (Carbon $month) => abs(($month->year - $now->year) * 12 + ($month->month - $now->month));
        $default = $weekStart->copy()->startOfWeek(Carbon::MONDAY)->addDays(3)->startOfMonth()->startOfDay();
        if ($away($default) > self::MONTHS_AROUND) {
            $default = $now->copy()->startOfMonth()->startOfDay();
        }
        if (!is_string($value) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $parts)) {
            return $default;
        }

        $month = Carbon::create((int) $parts[1], (int) $parts[2], 1)->startOfDay();

        return $away($month) > self::MONTHS_AROUND ? $default : $month;
    }

    /**
     * Which source line the footer carries: the acuity one wherever care
     * levels or the nurses they call for are printed (the bed assignment shows
     * care levels too, and the roster with its staffing rows the nurses
     * needed), and the reports' own.
     */
    private static function citation(string $format, array $snapshot): ?string
    {
        return match (true) {
            in_array($format, ['acuity', 'assign', 'pack'], true) => 'acuity',
            $format === 'roster' && $snapshot['acuity'] !== null => 'acuity',
            in_array($format, ['productivity', 'credentials'], true) => $format,
            default => null,
        };
    }

    /** The value when it is one of the allowed ones, else the default. */
    private function pick(mixed $value, array $allowed, mixed $default): mixed
    {
        return in_array($value, $allowed, true) ? $value : $default;
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

    /** The ward asked for, if active, else the first active ward, as the schedule page picks. */
    private function wardFrom(Request $request): Ward
    {
        $wards = Ward::where('is_active', true)->orderBy('ward_name');

        return (clone $wards)->find((int) $request->input('ward_id')) ?? $wards->firstOrFail();
    }
}
