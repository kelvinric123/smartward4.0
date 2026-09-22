<?php

namespace App\Http\Controllers;

use App\Models\AdmissionLog;
use App\Models\Ward;
use App\Services\DischargeSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Discharge summaries: the list of admissions, and the printable summary for
 * one of them.
 *
 * A summary can be opened for a patient who has not been discharged yet - the
 * ward often needs to review the stay before writing the discharge - but both
 * the screen and the printed copy say so in as many words, because a summary
 * printed mid-stay is not the final record of the admission.
 */
class DischargeSummaryController extends Controller
{
    public function __construct(private DischargeSummaryService $summaries)
    {
    }

    /**
     * List of admissions to pick a summary from.
     */
    public function index(Request $request): View
    {
        $filters = [
            'ward_id' => $request->input('ward_id'),
            'status' => $request->input('status'),
            'search' => $request->input('search', ''),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
        ];

        return view('wards.discharge-summary.index', [
            'admissions' => $this->summaries->admissions($filters),
            'wards' => Ward::where('is_active', true)->orderBy('ward_name')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * The summary on screen.
     */
    public function show(AdmissionLog $admission): View
    {
        $this->assertIsAdmission($admission);

        return view('wards.discharge-summary.show', $this->summaries->summary($admission));
    }

    /**
     * The same summary as a standalone printable page.
     */
    public function print(AdmissionLog $admission): View
    {
        $this->assertIsAdmission($admission);

        return view('wards.discharge-summary.print', $this->summaries->summary($admission));
    }

    /**
     * Only an admit / check-in log opens an admission; a discharge or transfer
     * log has no stay of its own to summarise.
     */
    private function assertIsAdmission(AdmissionLog $admission): void
    {
        abort_unless(
            in_array($admission->action, DischargeSummaryService::ADMISSION_ACTIONS, true),
            404,
            'That log entry is not an admission.'
        );
    }
}
