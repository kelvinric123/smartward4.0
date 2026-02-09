<?php

use App\Http\Controllers\WardScheduleController;
use Illuminate\Http\Request;
use App\Models\Ward;
use App\Models\Nurse;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $ward = Ward::firstOrFail();
    $nurse = Nurse::firstOrFail();

    $duties = [];
    $startDate = now();
    $types = [
        'team_leader' => ['AM', 'PM', 'ON'],
        'dda_mc_book' => ['AM', 'PM', 'ON'],
        'medication_fridge' => ['AM', 'PM', 'ON'],
        'e_trolley' => ['ON'],
        'qc_checking' => ['ON']
    ];

    for ($i = 0; $i < 7; $i++) {
        $date = $startDate->copy()->addDays($i)->toDateString();
        foreach ($types as $type => $shifts) {
            foreach ($shifts as $shift) {
                $key = "{$type}_{$date}_{$shift}";
                $duties[$key] = [
                    'duty_type' => $type,
                    'date' => $date,
                    'shift' => $shift,
                    'nurse_id' => $nurse->id
                ];
            }
        }
    }

    echo "Generated " . count($duties) . " duty entries.\n";

    $request = Request::create('/save-special-duty', 'POST', [
        'ward_id' => $ward->id,
        'duties' => $duties
    ]);

    $controller = app(WardScheduleController::class);
    $response = $controller->saveSpecialDuty($request);

    echo "Controller executed successfully with full payload.\n";

} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Validation Error: " . print_r($e->errors(), true) . "\n";
} catch (\Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
