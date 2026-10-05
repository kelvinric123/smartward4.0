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
      "patient_name": "N** A***** b**** I******",
      "isolation_type": "CONTACT ISOLATION"
    }
  ],
  "macList": [
    "D43D393CC02C"
  ]
}
```

`id` is the SEEKINK template the screen is painted with: the screen's own **Template ID** (Bed Mapping on `/ekad`, for screens of another size or another ward's layout), else the Template ID under **Configuration & Login**.

`data` holds the template's fields. The ones above are the **default fields**, which every template gets until its fields are edited under **Templates** on `/ekad` (`ekad_templates`, `App\Models\EkadTemplate`). There, each field is the name it has in the SEEKINK template, plus what SmartWard puts in it:
- The patient's data: bed number, MRN, patient name (masked as configured), diet, doctor, nurse on shift, anaesthetist, isolation, allergies (still active), ward, RN, gender, age, admission date.
- A fixed text.

Values are sent in capitals. An empty bed sends its bed number and ward, `Vacant` as the MRN, the fixed texts, and `-` in every other field, so a screen never keeps an old value.

`isolation_type` (default fields) is the patient's isolation precaution by name (Patient Details → Patient Additional Info → Isolation Precautions, set by staff, ADT or the C+ sync); `-` when there is none or the bed is vacant.

## 2. Trigger Strategy (REFINED)
We use the **Observer Pattern** exclusively. ADT Controller triggers are DISABLED to prevent race conditions and duplicate pushes.

### A. Core Architecture Changes
1.  **Remove ADT Controller Triggers**: The `AdtApiController` will **NEVER** call `EkadService`. It only updates the Database. EKad updates are handled by Observers.
2.  **Enable Observer-Based Triggers**: We use multiple observers to watch specific model changes that affect the "Bed Box" display.

### B. The "Bed Box" Observer Strategy
Data displayed on the "Bed Box" (MRN, Name, Doctor, Nurse, Diet) is the source of truth. Updates are triggered by **Model Observers** watching relevant field changes.

#### 1. Bed Occupancy (Admit/Discharge) → `BedObserver` ✅
The `Bed` model is the container. Changes to its occupancy state are the primary trigger.
*   **File**: `app/Observers/BedObserver.php`
*   **Status**: **ACTIVE** - Already implemented
*   **Trigger**: Watch for `patient_id` changes on the `Bed` model.
    *   `patient_id` becomes `NULL` → **Push "Vacant" Payload**
    *   `patient_id` becomes `VALUE` → **Push "Patient" Payload**

#### 2. Patient Info Changes (Diet, Name, etc.) → `PatientObserver` 🔄
Watch for changes to patient fields that display on the Bed Box.
*   **File**: `app/Observers/PatientObserver.php`
*   **Status**: **NEEDS RE-ENABLE** - Currently disabled (line 55)
*   **Fields to Watch**:
    *   `diet_types` - Patient can have multiple diets (located in Patient Details modal → Patient Additional Info → Diet Types)
    *   `isolation_type` - Isolation Precautions (Patient Details modal → Patient Additional Info), set by staff or the ADT feed
    *   `name` - Patient name
    *   `mrn` - Medical Record Number
*   **Logic**: When any watched field changes, trigger EKAD push if patient has an assigned bed

#### 3. Care Provider Changes (Anaesthetist) → `PatientCareProviderObserver` 🆕
Watch for care provider assignments/updates to detect anaesthetist changes.
*   **File**: `app/Observers/PatientCareProviderObserver.php` **[TO BE CREATED]**
*   **Status**: **NEW** - Needs to be created
*   **Trigger**: When a care provider is created/updated/deleted:
    *   Check if the provider is a **Referring Doctor** or **Consulting Doctor**
    *   Check if the linked consultant/anaesthetist is marked as an anaesthetist (as defined in `/anaesthetists` page)
    *   If yes, trigger EKAD update for the patient
*   **Note**: The anaesthetist field in EKAD should be populated from care providers where the role is "referring" or "consulting" AND the linked doctor is an anaesthetist

## 3. Implementation Plan
1.  **BedObserver** ✅:
    *   Already implemented and active
    *   Handles admit/discharge (patient_id changes)
    
2.  **Re-enable PatientObserver** 🔄:
    *   Uncomment/re-enable the EKAD logic in `PatientObserver.php`
    *   Ensure it watches: `diet_types`, `name`, `mrn`
    *   Only trigger if patient has an assigned bed
    
3.  **Create PatientCareProviderObserver** 🆕:
    *   Create new file: `app/Observers/PatientCareProviderObserver.php`
    *   Watch for created/updated/deleted events
    *   Check if provider is referring/consulting doctor AND is an anaesthetist
    *   Trigger EKAD update for the patient
    
4.  **Remove ADT Triggers** 🗑️:
    *   Remove `triggerEkadUpdate()` calls from `AdtApiController.php` (lines 874, 1055)
    *   Let observers handle all EKAD pushes
    
5.  **Register New Observer**:
    *   Update `app/Providers/EventServiceProvider.php` to register `PatientCareProviderObserver`

## 4. Reliability Improvements
*   **Single Responsibility**: Each observer watches its own model changes
*   **No Duplicate Triggers**: ADT updates trigger observers naturally through model changes
*   **Explicit Field Watching**: Only trigger on fields that actually display on the Bed Box
*   **No Race Conditions**: Database changes happen first, then observers trigger EKAD

