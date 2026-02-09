<?php

use App\Models\Ward;
use App\Models\Nurse;
use App\Models\WardSpecialDuty;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $ward = Ward::firstOrFail();
    $nurse = Nurse::firstOrFail();

    echo "Ward ID: " . $ward->id . "\n";
    echo "Nurse ID: " . $nurse->id . "\n";

    $model = WardSpecialDuty::updateOrCreate(
        [
            'ward_id' => $ward->id,
            'duty_type' => 'test_duty',
            'date' => now()->toDateString(),
            'shift' => 'AM',
        ],
        [
            'nurse_id' => $nurse->id,
        ]
    );

    echo "Successfully created/updated ID: " . $model->id . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
