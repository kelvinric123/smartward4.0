<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EcgController extends Controller
{
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
            
            if ($patient && $patient->mrn) {
                // Search for ECG files matching this patient's MRN
                $ecgFiles = $this->findEcgFilesForPatient($patient->mrn);
                
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
     * Find ECG files for a patient by MRN only
     * Matches ECG XML files where <PatientID> equals the patient's MRN
     */
    private function findEcgFilesForPatient(string $mrn): array
    {
        $ecgStorePath = base_path('ecg/store');
        $ecgFiles = [];
        
        // Clean the MRN for comparison (trim whitespace)
        $mrn = trim($mrn);
        
        if (empty($mrn)) {
            Log::warning('ECG search: Empty MRN provided');
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
            
            // Parse XML to get PatientID (which is the MRN)
            $ecgPatientId = $this->getEcgPatientId($xmlPath);
            
            // Match ONLY by MRN (PatientID in ECG XML = MRN in our system)
            if ($ecgPatientId !== null && trim($ecgPatientId) === $mrn) {
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
                ]);
            }
        }
        
        // Sort by timestamp descending (newest first)
        usort($ecgFiles, function($a, $b) {
            return strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01');
        });
        
        Log::info('ECG search completed', [
            'mrn' => $mrn,
            'files_found' => count($ecgFiles),
        ]);
        
        return $ecgFiles;
    }
    
    /**
     * Get PatientID (MRN) from ECG XML file
     * This is the ONLY field used for matching
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
            
            // Return PatientID (this is the MRN in the ECG)
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

