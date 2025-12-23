# EKAD Integration Analysis & Roadmap

## 1. JSON Payload Schema
The system must generate the following JSON structure for the SEEKINK API. 
**Rule**: All fields are mandatory. If data is missing/null, use `"-"`.

```json
{
  "id": "2000477842757914624",
  "data": [
    {
      "MRN": "MRN000024",
      "nurse": "-",
      "bed no": "D622",
      "doctor": "DATO DR MAHENDRA RAJ A/L P SUNDRAMOORTHY",
      "diet_type": "BF",
      "anaesthetist": "-",
      "patient_name": "N** A***** b**** I******"
    }
  ],
  "macList": [
    "D43D393CC02C"
  ]
}
```

## 2. Trigger Strategy
We are cleaning up the architecture to be more reliable and explicit.

### A. Core Architecture Changes
1.  **Remove ADT Listener Triggers**: The `adt_listener.py` and `AdtApiController` will **STOP** calling `EkadService`. They will only update the Database.
2.  **Remove PatientObserver**: As per instruction, we will **NOT** use `PatientObserver` to detect changes. It is too broad and "magic".

### B. The "Bed Box" Trigger Strategy
Data displayed on the "Bed Box" (MRN, Name, Doctor, Nurse, Diet) is the source of truth. Updates should be triggered **explicitly** from the points where this "Bed Box Info" is modified.

#### 1. Bed Occupancy (Admit/Discharge) -> `BedObserver`
The `Bed` model is the container. Changes to its occupancy state are the primary trigger.
*   **Action**: Update `BedObserver.php`.
*   **Trigger**: Watch for `patient_id` changes on the `Bed` model.
    *   `patient_id` becomes `NULL` -> **Push "Vacant" Payload**.
    *   `patient_id` becomes `VALUE` -> **Push "Patient" Payload**.

#### 2. Patient Info Changes (Diet, Doctor, Name) -> Controller Triggers
Since we removed `PatientObserver`, we must identify the Controllers handling "Bed Box" edits and fire the trigger explicitly after a successful save.
*   **Target**: `WardDashboardController` (or wherever Patient Details are edited).
*   **Logic**:
    ```php
    // In Controller update method
    $patient->update($validatedData);
    
    // Explicit Trigger "From the Bed Box"
    if ($patient->bed) {
         EkadService::pushPatientInfo($patient, $patient->bed);
    }
    ```
*   **Benefit**: We only trigger when the user *intentionally* updates the Bed Box info, avoiding ghost triggers from background processes.

## 3. Implementation Plan
1.  **Refactor `BedObserver.php`**:
    *   Handle `patient_id` -> `null` (Discharge) by pushing strict "Vacant" payload.
    *   Handle `patient_id` -> `int` (Admit) by pushing normal payload.
2.  **Modify Controllers**:
    *   Identify `update` methods for Patient details.
    *   Insert `EkadService::pushPatientInfo` calls.
3.  **Cleanup**:
    *   Delete `PatientObserver` EKAD logic.
    *   Delete `AdtApiController` triggers.

## 4. Reliability Improvements
*   **Single Responsibility**: `BedObserver` handles existence. Controllers handle content.
*   **No Race Conditions**: By removing the ADT Listener trigger and relying on the DB/Observer, we let the data settle first.
