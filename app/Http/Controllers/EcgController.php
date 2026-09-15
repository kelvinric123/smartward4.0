<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Services\EcgArchive;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EcgController extends Controller
{
    public function __construct(private EcgArchive $ecgArchive)
    {
    }

    /**
     * Display ECG Admin page with all ECG files
     */
    public function index(): View
    {
        $ecgFiles = $this->getAllEcgFilesWithPatients();

        // Calculate stats
        $ecgXmlFiles = array_filter($ecgFiles, fn($f) => ($f['type'] ?? '') === 'ecg_xml');
        $standalonePdfs = array_filter($ecgFiles, fn($f) => ($f['type'] ?? '') === 'standalone_pdf');

        $stats = [
            'total_files' => count($ecgFiles),
            'ecg_xml_files' => count($ecgXmlFiles),
            'standalone_pdfs' => count($standalonePdfs),
            'matched_patients' => count(array_filter($ecgFiles, fn($f) => $f['patient'] !== null)),
            'unmatched_patients' => count(array_filter($ecgXmlFiles, fn($f) => $f['patient'] === null)),
            'with_pdf' => count(array_filter($ecgFiles, fn($f) => $f['has_pdf'])),
        ];

        return view('integration.ecg.index', [
            'ecgFiles' => $ecgFiles,
            'stats' => $stats,
        ]);
    }

    /**
     * Get all ECG files with patient matching information
     * Matches ECG PatientID against both MRN and RN fields
     */
    private function getAllEcgFilesWithPatients(): array
    {
        $ecgStorePath = config('services.ecg.store_path');
        $allFiles = [];
        $processedPdfs = []; // Track PDFs that are linked to XML files

        if (!is_dir($ecgStorePath)) {
            return [];
        }

        $files = scandir($ecgStorePath);

        // Get all patients and create dual lookup by MRN and RN
        $patientsByMrn = [];
        $patientsByRn = [];
        $patientsById = [];

        $activePatients = Patient::where('is_active', true)->get();

        foreach ($activePatients as $patient) {
            // Index by MRN if available
            if (!empty($patient->mrn)) {
                $patientsByMrn[trim($patient->mrn)] = $patient;
            }
            // Index by RN if available
            if (!empty($patient->rn)) {
                $patientsByRn[trim($patient->rn)] = $patient;
            }
            $patientsById[$patient->id] = $patient;
        }

        // First pass: Process XML files (ECG data with patient info)
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (!preg_match('/\.xml$/i', $file)) {
                continue;
            }

            $xmlPath = $ecgStorePath . '/' . $file;
            $ecgPatientId = $this->getEcgPatientId($xmlPath);

            if ($ecgPatientId !== null) {
                $baseName = pathinfo($file, PATHINFO_FILENAME);
                $pdfFile = $baseName . '_extracted.pdf';
                $pdfPath = $ecgStorePath . '/' . $pdfFile;

                // Track this extracted PDF
                if (file_exists($pdfPath)) {
                    $processedPdfs[$pdfFile] = true;
                }

                // Try to find matching patient by MRN or RN
                $patient = null;
                $trimmedId = trim($ecgPatientId);
                $matchedPatient = null;

                // First try to match by MRN
                if (isset($patientsByMrn[$trimmedId])) {
                    $matchedPatient = $patientsByMrn[$trimmedId];
                }
                // If not found by MRN, try to match by RN
                elseif (isset($patientsByRn[$trimmedId])) {
                    $matchedPatient = $patientsByRn[$trimmedId];
                }

                // If patient found, prepare patient info
                if ($matchedPatient) {
                    $patient = [
                        'id' => $matchedPatient->id,
                        'name' => $matchedPatient->patient_name,
                        'mrn' => $matchedPatient->mrn,
                    ];
                }

                $allFiles[] = [
                    'xml_file' => $file,
                    'pdf_file' => file_exists($pdfPath) ? $pdfFile : null,
                    'has_pdf' => file_exists($pdfPath),
                    'mrn' => $ecgPatientId,
                    'timestamp' => $this->getTimestampFromFilename($file, $xmlPath),
                    'patient' => $patient,
                    'type' => 'ecg_xml',
                ];
            }
        }

        // Second pass: Process standalone PDF files (not extracted from XML)
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Only process PDF files
            if (!preg_match('/\.pdf$/i', $file)) {
                continue;
            }

            // Skip PDFs that are already linked to XML files
            if (isset($processedPdfs[$file])) {
                continue;
            }

            $pdfPath = $ecgStorePath . '/' . $file;

            // Manually uploaded PDFs are named manual_pid{patientId}_{timestamp}.pdf
            // so we can match them back to a patient by ID
            $patient = null;
            if (preg_match('/^manual_pid(\d+)_/i', $file, $matches)) {
                $matchedPatient = $patientsById[(int) $matches[1]] ?? null;
                if ($matchedPatient) {
                    $patient = [
                        'id' => $matchedPatient->id,
                        'name' => $matchedPatient->patient_name,
                        'mrn' => $matchedPatient->mrn,
                    ];
                }
            }

            $allFiles[] = [
                'xml_file' => null,
                'pdf_file' => $file,
                'has_pdf' => true,
                'mrn' => $patient['mrn'] ?? null,
                'timestamp' => $this->getTimestampFromFilename($file, $pdfPath),
                'patient' => $patient,
                'type' => 'standalone_pdf',
            ];
        }

        // Sort by timestamp descending (newest first)
        usort($allFiles, function ($a, $b) {
            return strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01');
        });

        return $allFiles;
    }

    /**
     * Display ECG viewer for a patient
     */
    public function patientEcg(Request $request): View
    {
        $patientId = $request->input('patient_id');
        $patient = null;
        $ecgFiles = [];
        $latestEcg = null;

        if ($patientId) {
            $patient = Patient::where('is_active', true)->find($patientId);

            if ($patient) {
                // Search for ECG files matching this patient's MRN or RN,
                // plus manually uploaded PDFs matched by patient ID
                $ecgFiles = $this->findEcgFilesForPatient($patient->mrn, $patient->rn, $patient->id);

                // Get the latest ECG file
                if (!empty($ecgFiles)) {
                    $latestEcg = $ecgFiles[0]; // Files are already sorted by date desc
                }
            }
        }

        return view('ecg.patient-ecg', [
            'patient' => $patient,
            'ecgFiles' => $ecgFiles,
            'latestEcg' => $latestEcg,
        ]);
    }

    /**
     * Serve an ECG PDF file
     */
    public function servePdf(Request $request)
    {
        $filename = $request->input('file');

        if (!$filename) {
            abort(404, 'No file specified');
        }

        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);

        // Look for the file in the ECG store directory
        $ecgStorePath = config('services.ecg.store_path');
        $filePath = $ecgStorePath . '/' . $filename;

        if (!file_exists($filePath)) {
            abort(404, 'ECG file not found');
        }

        // Export mode: force download, optionally with a friendly filename
        if ($request->boolean('download')) {
            $downloadName = $request->input('dl_name');
            $downloadName = $downloadName ? preg_replace('/[^\w\-. ]+/', '_', $downloadName) : $filename;
            if (!preg_match('/\.pdf$/i', $downloadName)) {
                $downloadName .= '.pdf';
            }

            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // Return the PDF file
        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Find ECG files for a patient by MRN or RN
     * Matches ECG XML files where <PatientID> equals the patient's MRN or RN.
     * Also includes manually uploaded PDFs (manual_pid{patientId}_*.pdf) matched by patient ID.
     */
    private function findEcgFilesForPatient(?string $mrn, ?string $rn = null, ?int $patientId = null): array
    {
        return $this->ecgArchive->filesForPatient($mrn, $rn, $patientId);
    }

    /**
     * Manually upload an ECG PDF for a patient
     * Saved as manual_pid{patientId}_{Ymd_His}.pdf in the ECG store so it can
     * be matched back to the patient by ID (no XML involved)
     */
    public function uploadPdf(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $patient = Patient::where('is_active', true)->find($validated['patient_id']);

        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found or inactive.'], 404);
        }

        $ecgStorePath = config('services.ecg.store_path');
        $uploadUrl = config('services.ecg.upload_url');

        $timestamp = now()->format('Ymd_His');
        $filename = "manual_pid{$patient->id}_{$timestamp}.pdf";
        $counter = 1;

        while (is_dir($ecgStorePath) && file_exists($ecgStorePath . '/' . $filename)) {
            $filename = "manual_pid{$patient->id}_{$timestamp}_{$counter}.pdf";
            $counter++;
        }

        if ($uploadUrl) {
            // Forward to the ECG upload server (same path the ECG integration
            // uses) - in docker this container's ECG store mount is read-only,
            // only the ECG container can write to the shared volume.
            try {
                $response = Http::withBasicAuth(
                    config('services.ecg.upload_username'),
                    config('services.ecg.upload_password')
                )
                    ->timeout(30)
                    ->withBody(file_get_contents($request->file('pdf')->getRealPath()), 'application/pdf')
                    ->post(rtrim($uploadUrl, '/') . '/' . rawurlencode($filename));
            } catch (\Throwable $e) {
                Log::error('Manual ECG upload: ECG upload server unreachable', [
                    'url' => $uploadUrl,
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['success' => false, 'message' => 'ECG upload server is unreachable.'], 502);
            }

            if (!$response->successful()) {
                Log::error('Manual ECG upload rejected by ECG upload server', [
                    'url' => $uploadUrl,
                    'status' => $response->status(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'ECG upload server rejected the file (HTTP ' . $response->status() . ').',
                ], 502);
            }
        } else {
            if (!is_dir($ecgStorePath)) {
                @mkdir($ecgStorePath, 0775, true);
            }

            if (!is_dir($ecgStorePath) || !is_writable($ecgStorePath)) {
                Log::error('ECG store directory not writable for manual upload', ['path' => $ecgStorePath]);
                return response()->json([
                    'success' => false,
                    'message' => 'ECG storage directory is not writable and no ECG upload server is configured (ECG_UPLOAD_URL).',
                ], 500);
            }

            $request->file('pdf')->move($ecgStorePath, $filename);
        }

        Log::info('Manual ECG PDF uploaded', [
            'patient_id' => $patient->id,
            'mrn' => $patient->mrn,
            'file' => $filename,
            'via' => $uploadUrl ? 'ecg-upload-server' : 'direct-write',
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'file' => $filename]);
    }

    /**
     * Delete an ECG record (XML and/or PDF files) from the ECG store
     */
    public function deleteEcg(Request $request)
    {
        $request->validate([
            'xml_file' => 'nullable|string',
            'pdf_file' => 'nullable|string',
        ]);

        $ecgStorePath = config('services.ecg.store_path');
        $uploadUrl = config('services.ecg.upload_url');
        $deleted = [];

        // Build the list of files to remove (sanitized against traversal,
        // only pdf/xml allowed; deleting an XML also removes its extracted PDF)
        $targets = [];
        foreach (['xml_file', 'pdf_file'] as $key) {
            $name = $request->input($key);

            if (empty($name)) {
                continue;
            }

            $name = basename($name);
            if (!preg_match('/^[\w\-. ]+\.(pdf|xml)$/i', $name)) {
                continue;
            }

            $targets[] = $name;

            if (preg_match('/\.xml$/i', $name)) {
                $targets[] = pathinfo($name, PATHINFO_FILENAME) . '_extracted.pdf';
            }
        }
        $targets = array_values(array_unique($targets));

        foreach ($targets as $name) {
            if ($uploadUrl) {
                // Forward to the ECG upload server - in docker this container's
                // ECG store mount is read-only, only the ECG container can write.
                try {
                    $response = Http::withBasicAuth(
                        config('services.ecg.upload_username'),
                        config('services.ecg.upload_password')
                    )
                        ->timeout(15)
                        ->delete(rtrim($uploadUrl, '/') . '/' . rawurlencode($name));
                } catch (\Throwable $e) {
                    Log::error('ECG delete: ECG upload server unreachable', [
                        'url' => $uploadUrl,
                        'error' => $e->getMessage(),
                    ]);
                    return response()->json(['success' => false, 'message' => 'ECG upload server is unreachable.'], 502);
                }

                if ($response->successful()) {
                    $deleted[] = $name;
                }
            } else {
                $path = $ecgStorePath . '/' . $name;
                if (file_exists($path) && @unlink($path)) {
                    $deleted[] = $name;
                }
            }
        }

        if (empty($deleted)) {
            return response()->json(['success' => false, 'message' => 'No matching ECG files found to delete.'], 404);
        }

        Log::info('ECG files deleted', [
            'deleted' => $deleted,
            'deleted_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /**
     * Get PatientID (MRN or RN) from ECG XML file
     * This field is used for matching against patient MRN or RN
     */
    private function getEcgPatientId(string $xmlPath): ?string
    {
        return $this->ecgArchive->patientIdFromXml($xmlPath);
    }

    /**
     * Get timestamp from filename or file modification time
     */
    private function getTimestampFromFilename(string $filename, string $xmlPath): string
    {
        return $this->ecgArchive->timestampFor($filename, $xmlPath);
    }

    /**
     * List all ECG files (for admin/debug purposes)
     * Shows all ECG files and their PatientID (MRN) for debugging
     */
    public function listFiles(Request $request)
    {
        $ecgStorePath = config('services.ecg.store_path');
        $allFiles = [];

        if (!is_dir($ecgStorePath)) {
            return response()->json(['error' => 'ECG store directory not found'], 404);
        }

        $files = scandir($ecgStorePath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (!preg_match('/\.xml$/i', $file)) {
                continue;
            }

            $xmlPath = $ecgStorePath . '/' . $file;
            $ecgPatientId = $this->getEcgPatientId($xmlPath);

            if ($ecgPatientId !== null) {
                $baseName = pathinfo($file, PATHINFO_FILENAME);
                $pdfFile = $baseName . '_extracted.pdf';
                $pdfPath = $ecgStorePath . '/' . $pdfFile;

                $allFiles[] = [
                    'xml_file' => $file,
                    'pdf_file' => file_exists($pdfPath) ? $pdfFile : null,
                    'has_pdf' => file_exists($pdfPath),
                    'mrn' => $ecgPatientId,  // PatientID from ECG = MRN
                    'timestamp' => $this->getTimestampFromFilename($file, $xmlPath),
                ];
            }
        }

        return response()->json($allFiles);
    }
}

