<?php

namespace App\Http\Controllers;

use App\Models\AdmissionLog;
use App\Models\Hospital;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Admission Logs, opened from the ward dashboard: the log with filters and a
 * choice of columns, a live count for the print dialog, and a printable report
 * of every matching record (not just the page on screen).
 */
class AdmissionLogController extends Controller
{
    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    /** A report stops here, so a very wide date range cannot hang the browser. */
    public const MAX_PRINT_ROWS = 5000;

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $perPage = in_array($request->integer('per_page'), self::PER_PAGE_OPTIONS, true)
            ? $request->integer('per_page')
            : 50;

        $logs = $this->query($filters)
            ->with(['ward', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('wards.admission-logs', [
            'logs' => $logs,
            'filters' => $filters,
            'perPage' => $perPage,
            'wards' => Ward::where('is_active', true)->orderBy('ward_name')->get(),
            'statusCounts' => $this->statusCounts($filters),
            'columns' => AdmissionLog::COLUMNS,
        ]);
    }

    /**
     * How many records a print would contain, in total and per status (the
     * per-status counts ignore the status choice so every box shows its count).
     */
    public function summary(Request $request)
    {
        $filters = $this->filters($request);

        return response()->json([
            'total' => $this->query($filters)->count(),
            'by_status' => $this->statusCounts($filters),
            'max' => self::MAX_PRINT_ROWS,
        ]);
    }

    /**
     * The printable report: every record matching the filters, with the
     * chosen columns, optionally grouped by status or by day.
     */
    public function print(Request $request)
    {
        $filters = $this->filters($request);

        $requested = collect((array) $request->input('columns', []));
        $columns = collect(array_keys(AdmissionLog::COLUMNS))
            ->filter(fn (string $key) => $requested->contains($key))
            ->values();
        if ($columns->isEmpty()) {
            $columns = collect(AdmissionLog::defaultColumns());
        }

        $sort = $request->input('sort') === 'desc' ? 'desc' : 'asc';
        $group = in_array($request->input('group'), ['status', 'date'], true) ? $request->input('group') : 'none';
        $orientation = $request->input('orientation') === 'portrait' ? 'portrait' : 'landscape';

        $total = $this->query($filters)->count();
        $logs = $this->query($filters)
            ->with(['ward', 'user'])
            ->orderBy('created_at', $sort)
            ->orderBy('id', $sort)
            ->limit(self::MAX_PRINT_ROWS)
            ->get();

        $statusOrder = array_flip(array_keys(AdmissionLog::ACTION_LABELS));
        $groups = match ($group) {
            'status' => $logs->groupBy('action')
                ->sortBy(fn ($rows, $action) => $statusOrder[$action] ?? PHP_INT_MAX)
                ->mapWithKeys(fn ($rows, $action) => [AdmissionLog::actionLabel($action) => $rows]),
            'date' => $logs->groupBy(fn (AdmissionLog $log) => $log->created_at->format('D, d M Y')),
            default => collect(['' => $logs]),
        };

        $ward = $filters['ward_id'] ? Ward::with('hospital')->find($filters['ward_id']) : null;

        return view('wards.admission-logs-print', [
            'groups' => $groups,
            'total' => $total,
            'printed' => $logs->count(),
            'columns' => $columns->mapWithKeys(fn (string $key) => [$key => AdmissionLog::COLUMNS[$key][0]]),
            'statusTotals' => collect($this->statusCounts($filters))
                ->filter(fn (int $count, string $action) => $count > 0
                    && (!$filters['statuses'] || in_array($action, $filters['statuses'], true))),
            'filterSummary' => $this->describe($filters, $ward),
            'hospital' => $ward?->hospital ?? Hospital::first(),
            'orientation' => $orientation,
            'sort' => $sort,
            'group' => $group,
            'showSummary' => $request->input('summary', '1') !== '0',
            'autoprint' => $request->boolean('autoprint'),
            'generatedBy' => Auth::user()?->name,
        ]);
    }

    /**
     * The filters from the query string, cleaned. The old single ?action=
     * still works and joins the ?statuses[] list.
     */
    private function filters(Request $request): array
    {
        $statuses = collect((array) $request->input('statuses', []))
            ->push($request->input('action'))
            ->map(fn ($status) => trim((string) $status))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $date = function ($value): ?string {
            if (!$value) {
                return null;
            }
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        };

        return [
            'ward_id' => $request->integer('ward_id') ?: null,
            'statuses' => $statuses,
            'source' => in_array($request->input('source'), ['manual', 'adt'], true) ? $request->input('source') : null,
            'bed_number' => trim((string) $request->input('bed_number', '')),
            'search' => trim((string) $request->input('search', '')),
            'from_date' => $date($request->input('from_date')),
            'to_date' => $date($request->input('to_date')),
        ];
    }

    private function query(array $filters, bool $withStatuses = true): Builder
    {
        return AdmissionLog::query()
            ->when($filters['ward_id'], fn (Builder $query, int $wardId) => $query->where('ward_id', $wardId))
            ->when($withStatuses && $filters['statuses'], fn (Builder $query) => $query->whereIn('action', $filters['statuses']))
            ->when($filters['source'] === 'adt', fn (Builder $query) => $query->where('source', 'adt'))
            // Rows from before the source column existed count as manual
            ->when($filters['source'] === 'manual', fn (Builder $query) => $query->where(
                fn (Builder $where) => $where->where('source', 'manual')->orWhereNull('source')
            ))
            ->when($filters['bed_number'] !== '', fn (Builder $query) => $query->where('bed_number', 'like', '%' . $filters['bed_number'] . '%'))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $where) => $where->where('mrn', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('patient_name', 'like', '%' . $filters['search'] . '%')
            ))
            ->when($filters['from_date'], fn (Builder $query, string $from) => $query->where('created_at', '>=', $from . ' 00:00:00'))
            ->when($filters['to_date'], fn (Builder $query, string $to) => $query->where(
                'created_at', '<', Carbon::parse($to)->addDay()->toDateString() . ' 00:00:00'
            ));
    }

    /**
     * Records per status under the other filters: the known statuses in their
     * usual order, then anything else found in the log.
     *
     * @return array<string, int>
     */
    private function statusCounts(array $filters): array
    {
        $counts = $this->query($filters, false)
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        return collect(array_keys(AdmissionLog::ACTION_LABELS))
            ->merge($counts->keys())
            ->filter(fn ($action) => $action !== null && $action !== '')
            ->unique()
            ->mapWithKeys(fn (string $action) => [$action => (int) ($counts[$action] ?? 0)])
            ->all();
    }

    /** Readable lines describing what a report covers. */
    private function describe(array $filters, ?Ward $ward): array
    {
        $from = $filters['from_date'] ? Carbon::parse($filters['from_date'])->format('d M Y') : null;
        $to = $filters['to_date'] ? Carbon::parse($filters['to_date'])->format('d M Y') : null;

        return array_filter([
            'Ward' => $ward?->ward_name ?? 'All wards',
            'Dates' => match (true) {
                $from && $to => $from === $to ? $from : $from . ' to ' . $to,
                (bool) $from => 'From ' . $from,
                (bool) $to => 'Up to ' . $to,
                default => 'All dates',
            },
            'Status' => $filters['statuses']
                ? collect($filters['statuses'])->map(fn ($action) => AdmissionLog::actionLabel($action))->implode(', ')
                : 'All statuses',
            'Source' => match ($filters['source']) {
                'adt' => 'ADT/HIS',
                'manual' => 'Manual',
                default => 'All sources',
            },
            'Bed' => $filters['bed_number'] !== '' ? $filters['bed_number'] : null,
            'Search' => $filters['search'] !== '' ? $filters['search'] : null,
        ]);
    }
}
