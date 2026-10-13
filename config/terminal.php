<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bedside Patient Information Terminal API (/api/terminal/*)
    |--------------------------------------------------------------------------
    |
    | A terminal pairs with its bed once (Admin settings, nurse-app login) and
    | then sends that pairing token with every call. Until every terminal has
    | been paired, an unpaired one still gets its bed's data, but with the
    | patient's name cut to initials and no MRN. Set this to true to turn
    | unpaired terminals away (401) instead.
    |
    */

    'require_device_token' => (bool) env('TERMINAL_REQUIRE_DEVICE_TOKEN', false),

];
