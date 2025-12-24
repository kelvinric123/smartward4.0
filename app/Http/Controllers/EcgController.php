<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EcgController extends Controller
{
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
        $ecgStorePath = base_path('ecg/store');
        $allFiles = [];
        $processedPdfs = []; // Track PDFs that are linked to XML files

        if (!is_dir($ecgStorePath)) {
            return [];
        }

        $files = scandir($ecgStorePath);

        // Get all patients and create dual lookup by MRN and RN
        $patientsByMrn = [];
        $patientsByRn = [];

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

            $allFiles[] = [
                'xml_file' => null,
                'pdf_file' => $file,
                'has_pdf' => true,
                'mrn' => null,
                'timestamp' => date('Y-m-d H:i:s', filemtime($pdfPath)),
                'patient' => null,
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
                // Search for ECG files matching this patient's MRN or RN
                $ecgFiles = $this->findEcgFilesForPatient($patient->mrn, $patient->rn);

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
        $ecgStorePath = base_path('ecg/store');
        $filePath = $ecgStorePath . '/' . $filename;

        if (!file_exists($filePath)) {
            abort(404, 'ECG file not found');
        }

        // Return the PDF file
        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Find ECG files for a patient by MRN or RN
     * Matches ECG XML files where <PatientID> equals the patient's MRN or RN
     */
    private function findEcgFilesForPatient(?string $mrn, ?string $rn = null): array
    {
        $ecgStorePath = base_path('ecg/store');
        $ecgFiles = [];

        // Clean the identifiers for comparison (trim whitespace)
        $mrn = $mrn ? trim($mrn) : null;
        $rn = $rn ? trim($rn) : null;

        if (empty($mrn) && empty($rn)) {
            Log::warning('ECG search: Empty MRN and RN provided');
            return [];
        }

        if (!is_dir($ecgStorePath)) {
            Log::warning('ECG store directory not found', ['path' => $ecgStorePath]);
            return [];
        }

        // Scan the ECG store directory for XML files
        $files = scandir($ecgStorePath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Only process XML files
            if (!preg_match('/\.xml$/i', $file)) {
                continue;
            }

            $xmlPath = $ecgStorePath . '/' . $file;

            // Parse XML to get PatientID (which could be MRN or RN)
            $ecgPatientId = $this->getEcgPatientId($xmlPath);

            // Match by MRN or RN (PatientID in ECG XML can be either)
            $isMatch = false;
            if ($ecgPatientId !== null) {
                $trimmedEcgId = trim($ecgPatientId);
                // Check if ECG PatientID matches MRN or RN
                if (($mrn && $trimmedEcgId === $mrn) || ($rn && $trimmedEcgId === $rn)) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                // Check if there's an extracted PDF for this XML
                $baseName = pathinfo($file, PATHINFO_FILENAME);
                $pdfFile = $baseName . '_extracted.pdf';
                $pdfPath = $ecgStorePath . '/' . $pdfFile;

                $hasPdf = file_exists($pdfPath);

                // Get timestamp from filename
                $timestamp = $this->getTimestampFromFilename($file, $xmlPath);

                $ecgFiles[] = [
                    'xml_file' => $file,
                    'pdf_file' => $hasPdf ? $pdfFile : null,
                    'has_pdf' => $hasPdf,
                    'mrn' => $ecgPatientId,  // The MRN from ECG (PatientID)
                    'timestamp' => $timestamp,
                    'recorded_at' => $timestamp,
                ];

                Log::debug('ECG file matched', [
                    'file' => $file,
                    'ecg_patient_id' => $ecgPatientId,
                    'patient_mrn' => $mrn,
                    'patient_rn' => $rn,
                ]);
            }
        }

        // Sort by timestamp descending (newest first)
        usort($ecgFiles, function ($a, $b) {
            return strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01');
        });

        Log::info('ECG search completed', [
            'mrn' => $mrn,
            'rn' => $rn,
            'files_found' => count($ecgFiles),
        ]);

        return $ecgFiles;
    }

    /**
     * Get PatientID (MRN or RN) from ECG XML file
     * This field is used for matching against patient MRN or RN
     */
    private function getEcgPatientId(string $xmlPath): ?string
    {
        try {
            $data = file_get_contents($xmlPath);

            if (empty($data)) {
                return null;
            }

            // Decode XML content (handle UTF-16 encoding)
            if (substr($data, 0, 2) === "\xff\xfe") {
                $xmlContent = mb_convert_encoding($data, 'UTF-8', 'UTF-16LE');
            } elseif (substr($data, 0, 2) === "\xfe\xff") {
                $xmlContent = mb_convert_encoding($data, 'UTF-8', 'UTF-16BE');
            } else {
                $xmlContent = $data;
            }

            // Parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                return null;
            }

            // Return PatientID (this can be MRN or RN in the ECG)
            $patientId = (string) ($xml->PatientID ?? '');

            return !empty($patientId) ? trim($patientId) : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get timestamp from filename or file modification time
     */
    private function getTimestampFromFilename(string $filename, string $xmlPath): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        // Try to extract timestamp from filename (format: ecg_upload_YYYYMMDD_HHMMSS)
        if (preg_match('/(\d{8}_\d{6})/', $baseName, $matches)) {
            $dateStr = $matches[1];
            $timestamp = \DateTime::createFromFormat('Ymd_His', $dateStr);
            if ($timestamp) {
                return $timestamp->format('Y-m-d H:i:s');
            }
        }

        // Fallback to file modification time
        return date('Y-m-d H:i:s', filemtime($xmlPath));
    }

    /**
     * List all ECG files (for admin/debug purposes)
     * Shows all ECG files and their PatientID (MRN) for debugging
     */
    public function listFiles(Request $request)
    {
        $ecgStorePath = base_path('ecg/store');
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

