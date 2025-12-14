<?php

namespace App\Http\Controllers;

use App\Models\AdtConfiguration;
use App\Models\AdtMessageLog;
use App\Models\AdtDoctorMapping;
use App\Models\Patient;
use App\Models\Bed;
use App\Models\Ward;
use App\Models\Consultant;
use App\Models\Anaesthetist;
use App\Models\DietType;
use App\Models\IsolationType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdtApiController extends Controller
{
    /**
     * Receive and process ADT message from Python listener
     */
    public function receiveMessage(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Get active configuration
            $configuration = AdtConfiguration::getActive();
            
            // Extract data from request
            $msh = $request->input('msh', []);
            $evn = $request->input('evn', []);
            $pid = $request->input('pid', []);
            $pv1 = $request->input('pv1', []);
            $pv2 = $request->input('pv2', []);
            $allergies = $request->input('allergies', []);
            $custom = $request->input('custom', []);
            $rawMessage = $request->input('raw_message', '');
            $sourceIp = $request->input('source_ip', $request->ip());
            
            // Create message log entry
            $messageLog = AdtMessageLog::create([
                'adt_configuration_id' => $configuration?->id,
                'message_type' => $msh['message_type'] ?? 'UNKNOWN',
                'event_type' => $msh['event_type'] ?? 'UNKNOWN',
                'event_description' => AdtMessageLog::EVENT_TYPES[$msh['event_type'] ?? ''] ?? 'Unknown Event',
                'message_control_id' => $msh['message_control_id'] ?? null,
                'sending_application' => $msh['sending_application'] ?? null,
                'sending_facility' => $msh['sending_facility'] ?? null,
                'patient_id' => $pid['mrn'] ?? null,
                'patient_name' => $pid['name'] ?? null,
                'patient_mrn' => $pid['mrn'] ?? null,
                'visit_number' => $pv1['visit_number'] ?? null,
                'assigned_location' => $pv1['location_raw'] ?? null,
                'source_ip' => $sourceIp,
                'raw_message' => $rawMessage,
                'parsed_data' => [
                    'msh' => $msh,
                    'evn' => $evn,
                    'pid' => $pid,
                    'pv1' => $pv1,
                    'pv2' => $pv2,
                    'allergies' => $allergies,
                    'custom' => $custom,
                ],
                'status' => 'received',
                'message_datetime' => $this->parseDateTime($msh['message_datetime'] ?? null),
            ]);
            
            // Process based on event type
            $eventType = $msh['event_type'] ?? '';
            $result = match ($eventType) {
                'A01' => $this->handleA01Admit($messageLog, $configuration, $pid, $pv1, $pv2, $allergies, $custom),
                'A02' => $this->handleA02Transfer($messageLog, $configuration, $pid, $pv1),
                'A03' => $this->handleA03Discharge($messageLog, $configuration, $pid, $pv1),
                'A04' => $this->handleA04Register($messageLog, $configuration, $pid, $pv1, $pv2, $allergies, $custom),
                'A08' => $this->handleA08Update($messageLog, $configuration, $pid, $pv1, $pv2, $allergies, $custom),
                'A11' => $this->handleA11CancelAdmit($messageLog, $configuration, $pid, $pv1),
                'A13' => $this->handleA13CancelDischarge($messageLog, $configuration, $pid, $pv1),
                'A16' => $this->handleA16PendingDischarge($messageLog, $configuration, $pid, $pv1),
                'A25' => $this->handleA25CancelPendingDischarge($messageLog, $configuration, $pid, $pv1),
                default => $this->handleUnknownEvent($messageLog, $eventType),
            };
            
            // Update processing time
            $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);
            $messageLog->update(['processing_time_ms' => $processingTimeMs]);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'log_id' => $messageLog->id,
                'event_type' => $eventType,
                'patient_id' => $result['patient_id'] ?? null,
                'actions' => $result['actions'] ?? [],
            ]);
            
        } catch (\Exception $e) {
            Log::error('ADT API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing ADT message: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Handle A01 - Admit/Visit Notification
     */
    protected function handleA01Admit(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1,
        array $pv2,
        array $allergies,
        array $custom
    ): array {
        $actions = [];
        
        // Extract ward and bed codes from the ADT message for logging
        $adtWardCode = $pv1['ward'] ?? null;
        $adtBedCode = $pv1['bed'] ?? null;
        $bedStatus = $pv1['bed_status'] ?? null;
        
        // Extract bed code from bed_status if bed is empty (e.g., "^^C706" -> "C706")
        if (!$adtBedCode && $bedStatus) {
            if (preg_match('/\^*([A-Za-z0-9]+)$/', $bedStatus, $matches)) {
                $adtBedCode = $matches[1];
            }
        }
        
        Log::info("ADT A01 Admit - Processing", [
            'mrn' => $pid['mrn'] ?? 'N/A',
            'name' => $pid['name'] ?? 'N/A',
            'adt_ward_code' => $adtWardCode,
            'adt_bed_code' => $adtBedCode,
            'bed_status' => $bedStatus,
            'auto_admit' => $configuration?->auto_admit ?? 'no config',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Check if auto-admit is enabled
            if ($configuration && !$configuration->auto_admit) {
                $messageLog->update([
                    'status' => 'ignored',
                    'action_taken' => [
                        'reason' => 'Auto-admit disabled',
                        'adt_ward_code' => $adtWardCode,
                        'adt_bed_code' => $adtBedCode,
                    ],
                ]);
                DB::commit();
                Log::info("ADT A01 Admit - Ignored (auto-admit disabled)");
                return [
                    'success' => true,
                    'message' => 'Message logged but auto-admit is disabled',
                    'actions' => ['logged'],
                ];
            }
            
            // Try to find ward - first from mapping, then direct lookup by ward_code
            $mappedWard = null;
            $mappedBed = null;
            
            if ($adtWardCode) {
                // First try mapping table (for cases where HIS codes differ from SmartWard)
                if ($configuration) {
                    $mappedWard = $configuration->findWardByAdtCode($adtWardCode);
                }
                // If not found in mapping, try direct lookup by ward_code
                if (!$mappedWard) {
                    $mappedWard = Ward::where('ward_code', $adtWardCode)
                        ->where('is_active', true)
                        ->first();
                }
            }
            
            if ($adtBedCode) {
                // First try mapping table
                if ($configuration) {
                    $mappedBed = $configuration->findBedByAdtCode($adtBedCode);
                }
                // If not found in mapping, try direct lookup by bed_number or bed_id
                if (!$mappedBed) {
                    $mappedBed = Bed::where(function($q) use ($adtBedCode) {
                            $q->where('bed_number', $adtBedCode)
                              ->orWhere('bed_id', $adtBedCode);
                        })
                        ->where('is_active', true)
                        ->first();
                }
            }
            
            $wardFound = $mappedWard !== null;
            $bedFound = $mappedBed !== null;
            
            // If ward or bed is not found anywhere, mark as unmapped
            if (!$wardFound || !$bedFound) {
                $unmappedDetails = [];
                if (!$wardFound && $adtWardCode) {
                    $unmappedDetails[] = "Ward '{$adtWardCode}' not found";
                }
                if (!$bedFound && $adtBedCode) {
                    $unmappedDetails[] = "Bed '{$adtBedCode}' not found";
                }
                
                $messageLog->update([
                    'status' => 'unmapped',
                    'error_message' => implode('; ', $unmappedDetails),
                    'action_taken' => [
                        'reason' => 'Ward or bed not found in system',
                        'adt_ward_code' => $adtWardCode,
                        'adt_bed_code' => $adtBedCode,
                        'ward_found' => $wardFound,
                        'bed_found' => $bedFound,
                        'mapped_ward_name' => $mappedWard?->ward_name,
                        'mapped_bed_number' => $mappedBed?->bed_number,
                    ],
                ]);
                DB::commit();
                
                Log::warning("ADT A01 Admit - NOT FOUND", [
                    'adt_ward_code' => $adtWardCode,
                    'adt_bed_code' => $adtBedCode,
                    'ward_found' => $wardFound,
                    'bed_found' => $bedFound,
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Ward or bed not found: ' . implode('; ', $unmappedDetails),
                    'actions' => ['unmapped'],
                ];
            }
            
            Log::info("ADT A01 Admit - Ward and Bed found", [
                'ward' => $mappedWard->ward_name,
                'bed' => $mappedBed->bed_number,
            ]);
            
            // Find or create patient by MRN
            $mrn = $pid['mrn'] ?? null;
            if (!$mrn) {
                throw new \Exception('MRN is required for admission');
            }
            
            $patient = Patient::where('mrn', $mrn)->first();
            $isNewPatient = !$patient;
            
            if ($isNewPatient) {
                $patient = new Patient();
                $patient->mrn = $mrn;
                $actions[] = 'patient_created';
                Log::info("ADT A01 Admit - Creating new patient with MRN: {$mrn}");
            } else {
                $actions[] = 'patient_updated';
                Log::info("ADT A01 Admit - Updating existing patient ID: {$patient->id}");
            }
            
            // Update patient information from PID
            $this->updatePatientFromPid($patient, $pid);
            
            // Update clinical indicators from allergies and custom segments
            $this->updatePatientClinicalIndicators($patient, $allergies, $custom, $pv1);
            
            // Update visit information from PV1/PV2
            $this->updatePatientVisitInfo($patient, $pv1, $pv2);
            
            // Set patient status to admitted
            $patient->status = Patient::STATUS_ADMITTED;
            $patient->admitted_at = $this->parseDateTime($pv1['admit_datetime_raw'] ?? null) ?? now();
            $patient->pending_discharge_at = null; // Clear any pending discharge flag
            $patient->discharged_at = null; // Clear discharged timestamp
            
            // Find and assign bed (we know it exists from the mapping check above)
            $bed = $this->findAndAssignBed($patient, $pv1, $configuration);
            if ($bed) {
                $patient->ward_id = $bed->ward_id;
                $patient->bed_number = $bed->bed_number;
                $wardName = $bed->ward->ward_name ?? 'unknown';
                $actions[] = 'bed_assigned';
                $actions[] = "assigned_to_ward_{$wardName}";
                $actions[] = "assigned_to_bed_{$bed->bed_number}";
                Log::info("ADT A01 Admit - Bed assigned: {$bed->bed_number} (ID: {$bed->id})");
            } else {
                Log::warning("ADT A01 Admit - No bed assigned despite mapping check");
            }
            
            // Find and assign consultant
            $consultant = $this->findAndAssignConsultant($patient, $pv1, $configuration);
            if ($consultant) {
                $patient->consultant_id = $consultant->id;
                $actions[] = 'consultant_assigned';
                Log::info("ADT A01 Admit - Consultant assigned: {$consultant->name}");
            }
            
            $patient->save();
            Log::info("ADT A01 Admit - Patient saved", [
                'patient_id' => $patient->id,
                'mrn' => $patient->mrn,
                'name' => $patient->name,
                'status' => $patient->status,
                'ward_id' => $patient->ward_id,
                'bed_number' => $patient->bed_number,
            ]);
            
            // Update message log with detailed information
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => array_merge($actions, [
                    'adt_ward_code' => $adtWardCode,
                    'adt_bed_code' => $adtBedCode,
                    'assigned_ward' => $bed?->ward?->ward_name,
                    'assigned_bed' => $bed?->bed_number,
                ]),
                'patient_id_ref' => $patient->id,
                'bed_id_ref' => $bed?->id,
            ]);
            
            // Update bed status if found
            if ($bed) {
                $bed->update([
                    'patient_id' => $patient->id,
                    'status' => 'occupied',
                ]);
                Log::info("ADT A01 Admit - Bed status updated to occupied");
            }
            
            DB::commit();
            
            Log::info("ADT A01 Admit - SUCCESS", ['actions' => $actions]);
            
            return [
                'success' => true,
                'message' => $isNewPatient ? 'Patient created and admitted' : 'Patient updated and admitted',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("ADT A01 Admit - FAILED: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'action_taken' => [
                    'adt_ward_code' => $adtWardCode,
                    'adt_bed_code' => $adtBedCode,
                ],
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Handle A02 - Transfer a Patient
     */
    protected function handleA02Transfer(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1
    ): array {
        $actions = [];
        
        try {
            DB::beginTransaction();
            
            if ($configuration && !$configuration->auto_transfer) {
                $messageLog->update([
                    'status' => 'ignored',
                    'action_taken' => ['reason' => 'Auto-transfer disabled'],
                ]);
                DB::commit();
                return [
                    'success' => true,
                    'message' => 'Message logged but auto-transfer is disabled',
                    'actions' => ['logged'],
                ];
            }
            
            // Extract ward and bed codes from PV1-3
            $adtWardCode = $pv1['ward'] ?? null;
            $adtBedCode = $pv1['bed'] ?? null;
            // Fallback: if bed code is missing, try to extract from bed_status (PV1-40) e.g. "^^B10" -> "B10"
            if (!$adtBedCode && !empty($pv1['bed_status'])) {
                if (preg_match('/\\^*([A-Za-z0-9]+)$/', $pv1['bed_status'], $matches)) {
                    $adtBedCode = $matches[1];
                }
            }
            
            Log::info("ADT A02 Transfer - Checking destination", [
                'adt_ward_code' => $adtWardCode,
                'adt_bed_code' => $adtBedCode,
            ]);
            
            // Try to find ward - first from mapping, then direct lookup by ward_code
            $mappedWard = null;
            $mappedBed = null;
            
            if ($adtWardCode) {
                // First try mapping table
                if ($configuration) {
                    $mappedWard = $configuration->findWardByAdtCode($adtWardCode);
                }
                // If not found in mapping, try direct lookup by ward_code
                if (!$mappedWard) {
                    $mappedWard = Ward::where('ward_code', $adtWardCode)
                        ->where('is_active', true)
                        ->first();
                }
            }
            
            if ($adtBedCode) {
                // First try mapping table
                if ($configuration) {
                    $mappedBed = $configuration->findBedByAdtCode($adtBedCode);
                }
                // If not found in mapping, try direct lookup by bed_number or bed_id
                if (!$mappedBed) {
                    $mappedBed = Bed::where(function($q) use ($adtBedCode) {
                            $q->where('bed_number', $adtBedCode)
                              ->orWhere('bed_id', $adtBedCode);
                        })
                        ->where('is_active', true)
                        ->first();
                }
            }
            
            $wardFound = $mappedWard !== null;
            $bedFound = $mappedBed !== null;
            
            // If destination ward or bed is not found, mark as unmapped
            if (!$wardFound || !$bedFound) {
                $unmappedDetails = [];
                if (!$wardFound && $adtWardCode) {
                    $unmappedDetails[] = "Ward '{$adtWardCode}' not found";
                }
                if (!$bedFound && $adtBedCode) {
                    $unmappedDetails[] = "Bed '{$adtBedCode}' not found";
                }
                
                $messageLog->update([
                    'status' => 'unmapped',
                    'error_message' => implode('; ', $unmappedDetails),
                    'action_taken' => [
                        'reason' => 'Destination ward or bed not found in system',
                        'adt_ward_code' => $adtWardCode,
                        'adt_bed_code' => $adtBedCode,
                        'ward_found' => $wardFound,
                        'bed_found' => $bedFound,
                        'mapped_ward_name' => $mappedWard?->ward_name,
                        'mapped_bed_number' => $mappedBed?->bed_number,
                    ],
                    'unmapped_ward_code' => !$wardFound ? $adtWardCode : null,
                    'unmapped_bed_code' => !$bedFound ? $adtBedCode : null,
                ]);
                DB::commit();
                
                Log::warning("ADT A02 Transfer - DESTINATION NOT FOUND", [
                    'adt_ward_code' => $adtWardCode,
                    'adt_bed_code' => $adtBedCode,
                    'ward_found' => $wardFound,
                    'bed_found' => $bedFound,
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Destination ward or bed not found: ' . implode('; ', $unmappedDetails),
                    'actions' => ['unmapped'],
                ];
            }
            
            Log::info("ADT A02 Transfer - Destination found", [
                'ward' => $mappedWard->ward_name,
                'bed' => $mappedBed->bed_number,
            ]);
            
            $mrn = $pid['mrn'] ?? null;
            if (!$mrn) {
                throw new \Exception('MRN is required for transfer');
            }
            
            $patient = Patient::where('mrn', $mrn)->first();
            
            // If patient doesn't exist (e.g., was in unmapped location), create them now
            if (!$patient) {
                Log::info("ADT A02 Transfer - Patient not found, creating new patient", ['mrn' => $mrn]);
                $patient = new Patient();
                $patient->mrn = $mrn;
                $this->updatePatientFromPid($patient, $pid);
                $patient->status = Patient::STATUS_ADMITTED;
                $patient->admitted_at = now();
                $actions[] = 'patient_created_on_transfer';
            } else {
                // Update existing patient info
                $this->updatePatientFromPid($patient, $pid);
                $actions[] = 'patient_updated';
            }
            
            // Release old bed if patient has one
            $oldBed = Bed::where('patient_id', $patient->id)->first();
            $oldBedNumber = $oldBed?->bed_number;
            $oldWardName = $oldBed?->ward?->ward_name;
            
            if ($oldBed) {
                $oldBed->update([
                    'patient_id' => null,
                    'status' => 'available',
                ]);
                $actions[] = 'old_bed_released';
                Log::info("ADT A02 Transfer - Released old bed: {$oldBedNumber}");
            }
            
            // Find and assign new bed
            [$newBed, $unmappedWardCode, $unmappedBedCode] = $this->findAndAssignBed($patient, $pv1, $configuration);
            if ($newBed) {
                $patient->ward_id = $newBed->ward_id;
                $patient->bed_number = $newBed->bed_number;
                $patient->status = Patient::STATUS_ADMITTED;
                $patient->is_active = true;
                
                $newBed->update([
                    'patient_id' => $patient->id,
                    'status' => 'occupied',
                ]);
                $actions[] = 'new_bed_assigned';
                $actions[] = "transferred_to_ward_{$newBed->ward->ward_name}";
                $actions[] = "transferred_to_bed_{$newBed->bed_number}";
                Log::info("ADT A02 Transfer - Assigned new bed: {$newBed->bed_number} in {$newBed->ward->ward_name}");
            } else {
                Log::warning("ADT A02 Transfer - No bed found", [
                    'unmapped_ward' => $unmappedWardCode,
                    'unmapped_bed' => $unmappedBedCode,
                ]);
            }
            
            $patient->save();
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => array_merge($actions, [
                    'adt_ward_code' => $adtWardCode,
                    'adt_bed_code' => $adtBedCode,
                    'old_ward' => $oldWardName,
                    'old_bed' => $oldBedNumber,
                    'assigned_ward' => $newBed?->ward?->ward_name,
                    'assigned_bed' => $newBed?->bed_number,
                ]),
                'patient_id_ref' => $patient->id,
                'bed_id_ref' => $newBed?->id,
            ]);
            
            DB::commit();
            
            Log::info("ADT A02 Transfer - SUCCESS", [
                'patient_id' => $patient->id,
                'from' => "{$oldWardName}/{$oldBedNumber}",
                'to' => "{$newBed?->ward?->ward_name}/{$newBed?->bed_number}",
                'actions' => $actions,
            ]);
            
            return [
                'success' => true,
                'message' => 'Patient transferred successfully',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("ADT A02 Transfer - FAILED: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Handle A03 - Discharge/End Visit
     */
    protected function handleA03Discharge(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1
    ): array {
        $actions = [];
        
        try {
            DB::beginTransaction();
            
            if ($configuration && !$configuration->auto_discharge) {
                $messageLog->update([
                    'status' => 'ignored',
                    'action_taken' => ['reason' => 'Auto-discharge disabled'],
                ]);
                DB::commit();
                return [
                    'success' => true,
                    'message' => 'Message logged but auto-discharge is disabled',
                    'actions' => ['logged'],
                ];
            }
            
            $mrn = $pid['mrn'] ?? null;
            $patient = $mrn ? Patient::where('mrn', $mrn)->first() : null;
            
            if (!$patient) {
                $messageLog->update([
                    'status' => 'failed',
                    'error_message' => 'Patient not found for discharge',
                ]);
                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'actions' => [],
                ];
            }
            
            // Release bed
            $bed = Bed::where('patient_id', $patient->id)->first();
            if ($bed) {
                $bed->update([
                    'patient_id' => null,
                    'status' => 'available',
                ]);
                $actions[] = 'bed_released';
            }
            
            // Update patient status
            $patient->status = Patient::STATUS_DISCHARGED;
            $patient->ward_id = null;
            $patient->bed_number = null;
            $patient->is_active = false;
            $patient->discharged_at = now();
            $patient->pending_discharge_at = null; // Clear pending discharge if was set
            $patient->save();
            $actions[] = 'patient_discharged';
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => $actions,
                'patient_id_ref' => $patient->id,
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Patient discharged successfully',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle A04 - Register a Patient (similar to A01 but typically for outpatient)
     */
    protected function handleA04Register(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1,
        array $pv2,
        array $allergies,
        array $custom
    ): array {
        // A04 is similar to A01 for registration purposes
        return $this->handleA01Admit($messageLog, $configuration, $pid, $pv1, $pv2, $allergies, $custom);
    }
    
    /**
     * Handle A08 - Update Patient Information
     */
    protected function handleA08Update(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1,
        array $pv2,
        array $allergies,
        array $custom
    ): array {
        $actions = [];
        
        try {
            DB::beginTransaction();
            
            $mrn = $pid['mrn'] ?? null;
            if (!$mrn) {
                throw new \Exception('MRN is required for patient update');
            }
            
            $patient = Patient::where('mrn', $mrn)->first();
            
            if (!$patient) {
                $messageLog->update([
                    'status' => 'failed',
                    'error_message' => 'Patient not found for update',
                ]);
                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'actions' => [],
                ];
            }
            
            $actions[] = 'patient_updated';
            
            // Update patient information (demographics/clinical/visit) but do NOT change admission/bed
            $this->updatePatientFromPid($patient, $pid);
            $this->updatePatientClinicalIndicators($patient, $allergies, $custom, $pv1);
            $this->updatePatientVisitInfo($patient, $pv1, $pv2);
            
            $patient->save();
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => $actions,
                'patient_id_ref' => $patient->id,
            ]);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Patient information updated',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle A11 - Cancel Admit/Cancel Visit
     * Reverses a previously sent A01 message
     */
    protected function handleA11CancelAdmit(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1
    ): array {
        $actions = [];
        
        Log::info("ADT A11 Cancel Admit - Processing", [
            'mrn' => $pid['mrn'] ?? 'N/A',
            'visit_number' => $pv1['visit_number'] ?? 'N/A',
        ]);
        
        try {
            DB::beginTransaction();
            
            $mrn = $pid['mrn'] ?? null;
            $visitNumber = $pv1['visit_number'] ?? null;
            
            // Find patient by MRN and optionally visit number
            $query = Patient::where('mrn', $mrn);
            if ($visitNumber) {
                $query->where('visit_number', $visitNumber);
            }
            $patient = $query->first();
            
            if (!$patient) {
                $messageLog->update([
                    'status' => 'failed',
                    'error_message' => 'Patient not found for cancel admit',
                ]);
                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'actions' => [],
                ];
            }
            
            // Release bed
            $bed = Bed::where('patient_id', $patient->id)->first();
            if ($bed) {
                $bed->update([
                    'patient_id' => null,
                    'status' => 'available',
                ]);
                $actions[] = 'bed_released';
            }
            
            // Update patient status to cancelled
            $patient->status = Patient::STATUS_CANCELLED;
            $patient->ward_id = null;
            $patient->bed_number = null;
            $patient->is_active = false;
            $patient->admitted_at = null;
            $patient->save();
            $actions[] = 'admission_cancelled';
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => $actions,
                'patient_id_ref' => $patient->id,
            ]);
            
            DB::commit();
            
            Log::info("ADT A11 Cancel Admit - SUCCESS", ['actions' => $actions]);
            
            return [
                'success' => true,
                'message' => 'Admission cancelled successfully',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADT A11 Cancel Admit - FAILED: " . $e->getMessage());
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle A13 - Cancel Discharge
     * Reopens a visit by cancelling a prior discharge event
     */
    protected function handleA13CancelDischarge(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1
    ): array {
        $actions = [];
        
        Log::info("ADT A13 Cancel Discharge - Processing", [
            'mrn' => $pid['mrn'] ?? 'N/A',
            'visit_number' => $pv1['visit_number'] ?? 'N/A',
        ]);
        
        try {
            DB::beginTransaction();
            
            $mrn = $pid['mrn'] ?? null;
            $patient = $mrn ? Patient::where('mrn', $mrn)->first() : null;
            
            if (!$patient) {
                $messageLog->update([
                    'status' => 'failed',
                    'error_message' => 'Patient not found for cancel discharge',
                ]);
                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'actions' => [],
                ];
            }
            
            // Re-admit patient - restore status
            $patient->status = Patient::STATUS_ADMITTED;
            $patient->is_active = true;
            $patient->discharged_at = null;
            $patient->pending_discharge_at = null;
            
            // Try to reassign bed from PV1
            $bed = $this->findAndAssignBed($patient, $pv1, $configuration);
            if ($bed) {
                $patient->ward_id = $bed->ward_id;
                $patient->bed_number = $bed->bed_number;
                $bed->update([
                    'patient_id' => $patient->id,
                    'status' => 'occupied',
                ]);
                $actions[] = 'bed_reassigned';
            }
            
            $patient->save();
            $actions[] = 'discharge_cancelled';
            $actions[] = 'patient_readmitted';
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => $actions,
                'patient_id_ref' => $patient->id,
                'bed_id_ref' => $bed?->id,
            ]);
            
            DB::commit();
            
            Log::info("ADT A13 Cancel Discharge - SUCCESS", ['actions' => $actions]);
            
            return [
                'success' => true,
                'message' => 'Discharge cancelled, patient readmitted',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADT A13 Cancel Discharge - FAILED: " . $e->getMessage());
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle A16 - Pending Discharge
     * Indicates the patient is awaiting discharge
     */
    protected function handleA16PendingDischarge(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1
    ): array {
        $actions = [];
        
        Log::info("ADT A16 Pending Discharge - Processing", [
            'mrn' => $pid['mrn'] ?? 'N/A',
            'visit_number' => $pv1['visit_number'] ?? 'N/A',
        ]);
        
        try {
            DB::beginTransaction();
            
            $mrn = $pid['mrn'] ?? null;
            $patient = $mrn ? Patient::where('mrn', $mrn)->first() : null;
            
            if (!$patient) {
                $messageLog->update([
                    'status' => 'failed',
                    'error_message' => 'Patient not found for pending discharge',
                ]);
                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'actions' => [],
                ];
            }
            
            // Update patient status to pending discharge
            $patient->status = Patient::STATUS_PENDING_DISCHARGE;
            $patient->pending_discharge_at = now();
            $patient->save();
            $actions[] = 'pending_discharge_flagged';
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => $actions,
                'patient_id_ref' => $patient->id,
            ]);
            
            DB::commit();
            
            Log::info("ADT A16 Pending Discharge - SUCCESS", ['actions' => $actions]);
            
            return [
                'success' => true,
                'message' => 'Patient flagged as pending discharge',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADT A16 Pending Discharge - FAILED: " . $e->getMessage());
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle A25 - Cancel Pending Discharge
     * Reverses a pending discharge previously flagged by an A16
     */
    protected function handleA25CancelPendingDischarge(
        AdtMessageLog $messageLog,
        ?AdtConfiguration $configuration,
        array $pid,
        array $pv1
    ): array {
        $actions = [];
        
        Log::info("ADT A25 Cancel Pending Discharge - Processing", [
            'mrn' => $pid['mrn'] ?? 'N/A',
            'visit_number' => $pv1['visit_number'] ?? 'N/A',
        ]);
        
        try {
            DB::beginTransaction();
            
            $mrn = $pid['mrn'] ?? null;
            $patient = $mrn ? Patient::where('mrn', $mrn)->first() : null;
            
            if (!$patient) {
                $messageLog->update([
                    'status' => 'failed',
                    'error_message' => 'Patient not found for cancel pending discharge',
                ]);
                DB::commit();
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'actions' => [],
                ];
            }
            
            // Restore patient status to admitted
            $patient->status = Patient::STATUS_ADMITTED;
            $patient->pending_discharge_at = null;
            $patient->save();
            $actions[] = 'pending_discharge_cancelled';
            
            $messageLog->update([
                'status' => 'processed',
                'action_taken' => $actions,
                'patient_id_ref' => $patient->id,
            ]);
            
            DB::commit();
            
            Log::info("ADT A25 Cancel Pending Discharge - SUCCESS", ['actions' => $actions]);
            
            return [
                'success' => true,
                'message' => 'Pending discharge cancelled',
                'patient_id' => $patient->id,
                'actions' => $actions,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADT A25 Cancel Pending Discharge - FAILED: " . $e->getMessage());
            $messageLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle unknown event type
     */
    protected function handleUnknownEvent(AdtMessageLog $messageLog, string $eventType): array
    {
        $messageLog->update([
            'status' => 'ignored',
            'action_taken' => ['reason' => "Unhandled event type: {$eventType}"],
        ]);
        
        return [
            'success' => true,
            'message' => "Event type {$eventType} is not currently handled, message logged only",
            'actions' => ['logged'],
        ];
    }
    
    /**
     * Update patient information from PID segment
     */
    protected function updatePatientFromPid(Patient $patient, array $pid): void
    {
        // Name
        if (!empty($pid['name'])) {
            $patient->name = $pid['name'];
        }
        
        // IC/Passport - use alternate_id field
        if (!empty($pid['alternate_id'])) {
            $patient->ic_passport = $pid['alternate_id'];
        } elseif (empty($patient->ic_passport)) {
            // Provide a default IC/Passport if not available
            // Use MRN as fallback to ensure unique constraint is satisfied
            $patient->ic_passport = 'IC-' . ($pid['mrn'] ?? $patient->mrn ?? uniqid());
            Log::info("ADT - Generated default ic_passport for patient: {$patient->ic_passport}");
        }
        
        // Generate RN if not exists
        if (empty($patient->rn)) {
            $patient->rn = 'RN-' . strtoupper(substr(md5($patient->mrn . time()), 0, 8));
        }
        
        // Date of Birth and Age
        if (!empty($pid['dob'])) {
            $patient->date_of_birth = $pid['dob'];
        }
        if (!empty($pid['age'])) {
            $patient->age = $pid['age'];
        } elseif ($patient->date_of_birth) {
            // Calculate age from DOB
            $patient->age = Carbon::parse($patient->date_of_birth)->age;
        }
        
        // Gender
        if (!empty($pid['gender'])) {
            $patient->gender = $pid['gender'];
        }
        
        // Phone
        if (!empty($pid['phone'])) {
            $patient->phone = $pid['phone'];
        }
        
        // Race
        if (!empty($pid['race'])) {
            $patient->race = $pid['race'];
        }
        
        // Religion
        if (!empty($pid['religion'])) {
            $patient->religion = $pid['religion'];
        }
        
        // Address
        if (!empty($pid['address']) && is_array($pid['address'])) {
            $patient->address = $pid['address'];
        }
        
        // Default to active
        $patient->is_active = true;
    }
    
    /**
     * Update patient clinical indicators
     */
    protected function updatePatientClinicalIndicators(Patient $patient, array $allergies, array $custom, array $pv1): void
    {
        // Allergies
        if (!empty($allergies)) {
            $patient->allergies = $allergies;
        }
        
        // Fall Risk
        if (!empty($custom['fall_risk'])) {
            $patient->fall_risk = 'yes';
        }
        
        // Isolation Type from RMI segment or custom
        if (!empty($custom['isolation_type'])) {
            $rawIsolation = trim($custom['isolation_type']);
            
            // Handle caret-delimited values like "CI^Contact Isolation"
            $parts = strpos($rawIsolation, '^') !== false ? explode('^', $rawIsolation, 2) : [$rawIsolation];
            $codePart = strtoupper(trim($parts[0] ?? ''));
            $descPart = trim($parts[1] ?? '');
            
            // First, try to look up in IsolationType table (primary source)
            $isolationType = IsolationType::findByCode($codePart);
            
            if ($isolationType) {
                // Store the isolation code directly - it's a valid code in our system
                $patient->isolation_type = $isolationType->code;
                Log::info("ADT - Isolation type set from IsolationType table", [
                    'raw' => $custom['isolation_type'],
                    'code' => $isolationType->code,
                    'name' => $isolationType->name,
                ]);
            } else {
                // Fallback to hardcoded map for backwards compatibility
                $isolationMap = [
                    // Standard codes
                    'DAC' => 'droplet_airborne_contact',
                    'D' => 'droplet',
                    'DI' => 'droplet',
                    'A' => 'airborne',
                    'AI' => 'airborne',
                    'C' => 'contact',
                    'CI' => 'contact',
                    // Combined codes
                    'DC' => 'droplet_contact',
                    'AC' => 'airborne_contact',
                    // Full names
                    'CONTACT' => 'contact',
                    'DROPLET' => 'droplet',
                    'AIRBORNE' => 'airborne',
                    'CONTACT ISOLATION' => 'contact',
                    'DROPLET ISOLATION' => 'droplet',
                    'AIRBORNE ISOLATION' => 'airborne',
                ];
                
                // Try mapping using code, then description, then full raw value
                $isolationTypeCandidates = array_filter([
                    $codePart,
                    strtoupper($descPart),
                    strtoupper($rawIsolation),
                ]);
                
                $mappedIsolation = null;
                foreach ($isolationTypeCandidates as $candidate) {
                    if (isset($isolationMap[$candidate])) {
                        $mappedIsolation = $isolationMap[$candidate];
                        break;
                    }
                }
                
                // Fallback to slugified raw value
                $patient->isolation_type = $mappedIsolation ?? strtolower(str_replace([' ', '^'], ['_', '_'], $rawIsolation));
                
                Log::info("ADT - Isolation type set (fallback)", [
                    'raw' => $custom['isolation_type'],
                    'parsed' => $codePart,
                    'mapped' => $patient->isolation_type,
                ]);
            }
        }
        
        // Diet Type from PV1-38 (diet_type field)
        // Example from document: DMD, REGD^DIABETIC DIET, BF (Breastfeeding)
        if (!empty($pv1['diet_type'])) {
            // Parse diet type - may contain multiple diets separated by comma
            $dietRaw = $pv1['diet_type'];
            
            // If it contains ^, take the code part (before ^)
            if (strpos($dietRaw, '^') !== false) {
                $parts = explode('^', $dietRaw);
                $dietRaw = trim($parts[0]);
            }
            
            // Take the first diet if multiple
            if (strpos($dietRaw, ',') !== false) {
                $dietRaw = trim(explode(',', $dietRaw)[0]);
            }
            
            // First, try to look up in DietType table (primary source)
            $dietCode = strtoupper(trim($dietRaw));
            $dietType = DietType::findByCode($dietCode);
            
            if ($dietType) {
                // Store the diet code directly - it's a valid code in our system
                $patient->diet_type = $dietType->code;
                Log::info("ADT - Diet type set from DietType table", [
                    'raw' => $pv1['diet_type'],
                    'code' => $dietType->code,
                    'name' => $dietType->name,
                ]);
            } else {
                // Fallback to hardcoded map for backwards compatibility
                $dietMap = [
                    'NPO' => 'NPO',
                    'NBM' => 'NBM',
                    'DMD' => 'DMD',
                    'REGD' => 'RD',
                    'RD' => 'RD',
                    'BF' => 'BF',
                    'VEG' => 'VEGD',
                    'SD' => 'SD',
                ];
                
                $patient->diet_type = $dietMap[$dietCode] ?? $dietCode;
                
                Log::info("ADT - Diet type set (fallback)", [
                    'raw' => $pv1['diet_type'],
                    'parsed' => $dietRaw,
                    'mapped' => $patient->diet_type,
                ]);
            }
        }
    }
    
    /**
     * Update patient visit information from PV1/PV2
     */
    protected function updatePatientVisitInfo(Patient $patient, array $pv1, array $pv2): void
    {
        // Visit Number
        if (!empty($pv1['visit_number'])) {
            $patient->visit_number = $pv1['visit_number'];
        }
        
        // Patient Class
        if (!empty($pv1['patient_class'])) {
            $patient->patient_class = $pv1['patient_class'];
        }
        
        // Expected Discharge DateTime
        if (!empty($pv2['expected_discharge_datetime'])) {
            $patient->expected_discharge_at = $this->parseDateTime($pv2['expected_discharge_datetime_raw'] ?? null)
                ?? $pv2['expected_discharge_datetime'];
        }
        
        // Estimated Length of Stay
        if (!empty($pv2['estimated_length_of_stay'])) {
            $patient->estimated_length_of_stay = $pv2['estimated_length_of_stay'];
        }
    }
    
    /**
     * Find and assign bed from PV1 location information
     * Priority: 1) Direct lookup by bed_number/bed_id, 2) Mapping table, 3) Ward lookup
     */
    protected function findAndAssignBed(Patient $patient, array $pv1, ?AdtConfiguration $configuration): ?Bed
    {
        $bedCode = isset($pv1['bed']) ? strtoupper(trim($pv1['bed'])) : null;
        $wardCode = isset($pv1['ward']) ? strtoupper(trim($pv1['ward'])) : null;
        $bedStatus = isset($pv1['bed_status']) ? trim($pv1['bed_status']) : null;
        
        Log::info("ADT Bed Assignment - Looking for bed", [
            'bed_code' => $bedCode,
            'ward_code' => $wardCode,
            'bed_status' => $bedStatus,
        ]);
        
        // Extract bed code from bed_status if bed is empty (e.g., "^^D606" -> "D606")
        if (!$bedCode && $bedStatus) {
            if (preg_match('/\^*([A-Za-z0-9]+)$/', $bedStatus, $matches)) {
                $bedCode = strtoupper($matches[1]);
                Log::info("ADT Bed Assignment - Extracted bed code from bed_status: {$bedCode}");
            }
        }
        
        // 1) Direct lookup by bed_number or bed_id (PRIMARY method - auto-match)
        if ($bedCode) {
            $bed = Bed::where(function($q) use ($bedCode) {
                    $q->where('bed_number', $bedCode)
                      ->orWhere('bed_id', $bedCode);
                })
                ->where('is_active', true)
                ->first();
            if ($bed) {
                Log::info("ADT Bed Assignment - Found by direct lookup: {$bed->bed_number}");
                return $bed;
            }
        }
        
        // 2) Try mapping table (for cases where HIS codes differ from SmartWard)
        if ($configuration && $bedCode) {
            $bed = $configuration->findBedByAdtCode($bedCode);
            if ($bed) {
                Log::info("ADT Bed Assignment - Found by mapping: {$bed->bed_number}");
                return $bed;
            }
        }
        
        // 3) Find ward and get any available bed in that ward
        $ward = null;
        if ($wardCode) {
            // Direct ward lookup first
            $ward = Ward::where('ward_code', $wardCode)
                ->where('is_active', true)
                ->first();
            // Fallback to mapping
            if (!$ward && $configuration) {
                $ward = $configuration->findWardByAdtCode($wardCode);
            }
        }
        
        if ($ward) {
            $bed = Bed::where('ward_id', $ward->id)
                ->where('status', 'available')
                ->whereNull('patient_id')
                ->where('is_active', true)
                ->first();
            if ($bed) {
                Log::info("ADT Bed Assignment - Found available bed in ward {$ward->ward_name}: {$bed->bed_number}");
                return $bed;
            }
        }
        
        Log::warning("ADT Bed Assignment - No bed found", [
            'bed_code' => $bedCode,
            'ward_code' => $wardCode,
        ]);
        
        return null;
    }
    
    /**
     * Find and assign consultant from PV1 attending doctor
     * Also checks if the consultant is an anaesthetist based on specialty
     */
    protected function findAndAssignConsultant(Patient $patient, array $pv1, ?AdtConfiguration $configuration): ?Consultant
    {
        $doctorCode = $pv1['attending_doctor_id'] ?? null;
        
        if (!$doctorCode) {
            return null;
        }
        
        Log::info("ADT Consultant Assignment - Looking for doctor", ['code' => $doctorCode]);
        
        // 1) Direct lookup by personnel_code (PRIMARY method - auto-match)
        $consultant = Consultant::findByPersonnelCode($doctorCode);
        if ($consultant) {
            Log::info("ADT Consultant Assignment - Found by personnel_code: {$consultant->name}");
            
            // Check if this consultant is actually an anaesthetist
            if ($consultant->isAnaesthetist()) {
                Log::info("ADT Consultant Assignment - Consultant is an anaesthetist, assigning to anaesthetist_id");
                $patient->anaesthetist_id = $consultant->id;
            }
            
            return $consultant;
        }
        
        // 2) Try Anaesthetist table by personnel_code
        $anaesthetist = Anaesthetist::findByPersonnelCode($doctorCode);
        if ($anaesthetist) {
            Log::info("ADT Consultant Assignment - Found anaesthetist by personnel_code: {$anaesthetist->name}");
            $patient->anaesthetist_id = $anaesthetist->id;
            // Don't return consultant, anaesthetist is separate
            return null;
        }
        
        // 3) Try mapping table (for cases where HIS codes differ)
        if ($configuration) {
            $consultant = $configuration->findConsultantByAdtCode($doctorCode, 'attending');
            if ($consultant) {
                Log::info("ADT Consultant Assignment - Found by mapping: {$consultant->name}");
                if ($consultant->isAnaesthetist()) {
                    $patient->anaesthetist_id = $consultant->id;
                }
                return $consultant;
            }
        }
        
        // 4) Try direct lookup by registration_number as last resort
        $consultant = Consultant::where('registration_number', $doctorCode)
            ->where('is_active', true)
            ->first();
        if ($consultant) {
            Log::info("ADT Consultant Assignment - Found by registration_number: {$consultant->name}");
            if ($consultant->isAnaesthetist()) {
                $patient->anaesthetist_id = $consultant->id;
            }
            return $consultant;
        }
        
        Log::warning("ADT Consultant Assignment - Doctor not found", ['code' => $doctorCode]);
        
        return null;
    }
    
    /**
     * Parse HL7 datetime format (YYYYMMDDHHMMSS) to Carbon
     */
    protected function parseDateTime(?string $datetime): ?Carbon
    {
        if (!$datetime || strlen($datetime) < 8) {
            return null;
        }
        
        try {
            $format = match (strlen($datetime)) {
                8 => 'Ymd',
                12 => 'YmdHi',
                14 => 'YmdHis',
                default => 'YmdHis',
            };
            
            return Carbon::createFromFormat($format, substr($datetime, 0, strlen($format) === 'Ymd' ? 8 : 14));
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Debug endpoint to check ADT setup
     */
    public function debug(): JsonResponse
    {
        $configuration = AdtConfiguration::getActive();
        
        // Get bed stats
        $totalBeds = Bed::count();
        $availableBeds = Bed::where('status', 'available')->where('is_active', true)->whereNull('patient_id')->count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        
        // Get mapping stats
        $bedMappings = $configuration ? $configuration->bedMappings()->count() : 0;
        $wardMappings = $configuration ? $configuration->wardMappings()->count() : 0;
        $doctorMappings = $configuration ? $configuration->doctorMappings()->count() : 0;
        
        // Get sample bed mappings
        $sampleBedMappings = $configuration 
            ? $configuration->bedMappings()->take(10)->get(['adt_bed_code', 'bed_id'])
            : [];
        
        // Get sample beds
        $sampleBeds = Bed::take(10)->get(['id', 'bed_number', 'status', 'patient_id', 'is_active']);
        
        // Get recent ADT logs
        $recentLogs = AdtMessageLog::latest()->take(5)->get(['id', 'event_type', 'status', 'patient_mrn', 'action_taken', 'error_message', 'created_at']);
        
        // Get recently admitted patients via ADT
        $recentPatients = Patient::where('status', 'admitted')
            ->latest('admitted_at')
            ->take(5)
            ->get(['id', 'mrn', 'name', 'status', 'ward_id', 'bed_number', 'admitted_at']);
        
        return response()->json([
            'success' => true,
            'configuration' => $configuration ? [
                'id' => $configuration->id,
                'name' => $configuration->name,
                'is_active' => $configuration->is_active,
                'auto_admit' => $configuration->auto_admit,
                'auto_discharge' => $configuration->auto_discharge,
                'auto_transfer' => $configuration->auto_transfer,
            ] : null,
            'stats' => [
                'total_beds' => $totalBeds,
                'available_beds' => $availableBeds,
                'occupied_beds' => $occupiedBeds,
                'bed_mappings' => $bedMappings,
                'ward_mappings' => $wardMappings,
                'doctor_mappings' => $doctorMappings,
            ],
            'sample_bed_mappings' => $sampleBedMappings,
            'sample_beds' => $sampleBeds,
            'recent_logs' => $recentLogs,
            'recent_patients' => $recentPatients,
        ]);
    }
}






