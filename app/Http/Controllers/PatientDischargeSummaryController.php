<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\DischargeSummaryService;
use App\Support\AdmissionEpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The discharge summary as Patient Details shows it: the patient's latest
 * admission by default (or an earlier one picked from the tab), readable
 * while the patient is still in the ward.
 *
 * The admission list (DischargeSummaryController) works from an admission log
 * row; this works from the patient, so the tab never needs to know which log
 * row opened the stay - and a patient admitted before admission logs existed
 * still gets a summary.
 */
class PatientDischargeSummaryController extends Controller
{
    public function __construct(private DischargeSummaryService $summaries)
    {
    }

    /**
     * The tab body, fetched by the Discharge Summary tab (HTML fragment).
     */
    public function panel(Request $request, Patient $patient): View
    {
        $admissions = $this->summaries->admissionsFor($patient);
        $episode = $this->episode($request, $patient, $admissions);

        if (!$episode) {
            return view('wards.discharge-summary.panel', [
                'patient' => $patient,
                'episode' => null,
                'admissions' => $admissions,
            ]);
        }

        return view('wards.discharge-summary.panel', $this->summaries->summaryForEpisode($episode) + [
            'admissions' => $admissions,
            'printUrl' => route('ward.discharge-summary.print', array_filter([
                'patient' => $patient->id,
                'admission' => $episode->admissionLog->exists ? $episode->admissionLog->id : null,
            ])),
        ]);
    }

    /**
     * The same summary on the standalone printable page.
     */
    public function print(Request $request, Patient $patient): View
    {
        $episode = $this->episode($request, $patient, $this->summaries->admissionsFor($patient));

        abort_unless($episode, 404, 'This patient has no admission to summarise.');

        return view('wards.discharge-summary.print', $this->summaries->summaryForEpisode($episode));
    }

    /**
     * The admission asked for (?admission=<admission log id>), or the latest.
     * An id that is not one of this patient's admissions is a 404 rather than
     * a silent fall back, so a stale link never prints the wrong stay.
     */
    private function episode(Request $request, Patient $patient, Collection $admissions): ?AdmissionEpisode
    {
        if ($request->filled('admission')) {
            $admission = $admissions->firstWhere('id', $request->integer('admission'));

            abort_unless($admission, 404, 'That admission does not belong to this patient.');

            return $admission->episode;
        }

        return $admissions->first()?->episode ?? $this->summaries->unloggedEpisodeFor($patient);
    }
}
