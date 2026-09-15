<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Reads the ECG store on disk.
 *
 * ECG recordings arrive as XML (from the gateways / the dockerized listener)
 * plus an extracted PDF, or as a manually uploaded PDF. Nothing links them to
 * a patient row - the XML carries the identifier the ECG machine was given,
 * so a lookup means scanning the store and matching that identifier against
 * the patient's MRN or RN. The ECG viewer and the discharge summary both need
 * that, so the matching rules live here rather than in a controller.
 */
class EcgArchive
{
    public function storePath(): string
    {
        return (string) config('services.ecg.store_path');
    }

    /**
     * Every ECG in the store belonging to this patient, newest first.
     *
     * Each entry: xml_file, pdf_file, has_pdf, mrn, timestamp, recorded_at,
     * source ('gateway' | 'manual').
     */
    public function filesForPatient(?string $mrn, ?string $rn = null, ?int $patientId = null): array
    {
        $storePath = $this->storePath();
        $ecgFiles = [];

        $mrn = $mrn ? trim($mrn) : null;
        $rn = $rn ? trim($rn) : null;

        if (empty($mrn) && empty($rn) && empty($patientId)) {
            Log::warning('ECG search: Empty MRN, RN and patient ID provided');
            return [];
        }

        if (!is_dir($storePath)) {
            Log::warning('ECG store directory not found', ['path' => $storePath]);
            return [];
        }

        foreach (scandir($storePath) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Manually uploaded PDFs are matched by patient ID in the filename
            if ($patientId && preg_match('/^manual_pid(\d+)_.*\.pdf$/i', $file, $matches)) {
                if ((int) $matches[1] === $patientId) {
                    $pdfPath = $storePath . '/' . $file;
                    $timestamp = $this->timestampFor($file, $pdfPath);

                    $ecgFiles[] = [
                        'xml_file' => null,
                        'pdf_file' => $file,
                        'has_pdf' => true,
                        'mrn' => $mrn,
                        'timestamp' => $timestamp,
                        'recorded_at' => $timestamp,
                        'source' => 'manual',
                    ];
                }
                continue;
            }

            if (!preg_match('/\.xml$/i', $file)) {
                continue;
            }

            if (empty($mrn) && empty($rn)) {
                continue;
            }

            $xmlPath = $storePath . '/' . $file;

            // The ECG's PatientID can hold either the MRN or the RN
            $ecgPatientId = $this->patientIdFromXml($xmlPath);
            if ($ecgPatientId === null) {
                continue;
            }

            $trimmedEcgId = trim($ecgPatientId);
            $isMatch = ($mrn && $trimmedEcgId === $mrn) || ($rn && $trimmedEcgId === $rn);

            if (!$isMatch) {
                continue;
            }

            $baseName = pathinfo($file, PATHINFO_FILENAME);
            $pdfFile = $baseName . '_extracted.pdf';
            $hasPdf = file_exists($storePath . '/' . $pdfFile);
            $timestamp = $this->timestampFor($file, $xmlPath);

            $ecgFiles[] = [
                'xml_file' => $file,
                'pdf_file' => $hasPdf ? $pdfFile : null,
                'has_pdf' => $hasPdf,
                'mrn' => $ecgPatientId,
                'timestamp' => $timestamp,
                'recorded_at' => $timestamp,
                'source' => 'gateway',
            ];

            Log::debug('ECG file matched', [
                'file' => $file,
                'ecg_patient_id' => $ecgPatientId,
                'patient_mrn' => $mrn,
                'patient_rn' => $rn,
            ]);
        }

        usort($ecgFiles, fn($a, $b) => strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01'));

        Log::info('ECG search completed', [
            'mrn' => $mrn,
            'rn' => $rn,
            'files_found' => count($ecgFiles),
        ]);

        return $ecgFiles;
    }

    /**
     * The <PatientID> the ECG machine recorded - the MRN or the RN.
     */
    public function patientIdFromXml(string $xmlPath): ?string
    {
        try {
            $data = file_get_contents($xmlPath);

            if (empty($data)) {
                return null;
            }

            // ECG exports are frequently UTF-16
            if (substr($data, 0, 2) === "\xff\xfe") {
                $xmlContent = mb_convert_encoding($data, 'UTF-8', 'UTF-16LE');
            } elseif (substr($data, 0, 2) === "\xfe\xff") {
                $xmlContent = mb_convert_encoding($data, 'UTF-8', 'UTF-16BE');
            } else {
                $xmlContent = $data;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                return null;
            }

            $patientId = (string) ($xml->PatientID ?? '');

            return !empty($patientId) ? trim($patientId) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Recording time from the filename (ecg_upload_YYYYMMDD_HHMMSS), falling
     * back to the file's modification time.
     */
    public function timestampFor(string $filename, string $path): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/(\d{8}_\d{6})/', $baseName, $matches)) {
            $timestamp = \DateTime::createFromFormat('Ymd_His', $matches[1]);
            if ($timestamp) {
                return $timestamp->format('Y-m-d H:i:s');
            }
        }

        return date('Y-m-d H:i:s', filemtime($path));
    }
}
