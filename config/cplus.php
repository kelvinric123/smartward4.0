<?php

return [

    /*
    |--------------------------------------------------------------------------
    | C+ link (Bed Management)
    |--------------------------------------------------------------------------
    |
    | The rpa_cplus_smartward RPA reads C+ (Cerebral HIS) Bed Management and
    | pushes every ward, bed and patient to POST /api/cplus/bed-sync, with the
    | API token of an Integration User (Users > Edit > Generate token).
    |
    */

    // The SmartWard hospital the C+ wards belong to. Empty = the only active
    // hospital; with more than one, this has to be set.
    'hospital_id' => env('CPLUS_SYNC_HOSPITAL_ID'),

    // The isolation precaution (an Isolation Types code) a patient gets while
    // the Isolation box on their C+ admission card is ticked. C+ does not
    // record the kind, so a kind picked in Patient Details is kept.
    'isolation_type' => env('CPLUS_ISOLATION_TYPE', 'ISO'),

];
