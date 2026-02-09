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

    // Create a request with data
    $request = Request::create('/save-special-duty', 'POST', [
        'ward_id' => $ward->id,
        'duties' => [
            'test_entry' => [
                'duty_type' => 'team_leader',
                'date' => now()->toDateString(),
                'shift' => 'AM',
                'nurse_id' => $nurse->id
            ]
        ]
    ]);

    // Set user for Auth::id() call in catch block if needed, though we hope not to catch
    // But Auth::id() might be null in CLI, which is fine for the log call.

    $controller = app(WardScheduleController::class);

    // We can't easily capture the redirect response in CLI without running the full app stack,
    // but we can check if it throws exception.

    // Mock user login if necessary?
    // Auth::loginUsingId(1);

    $response = $controller->saveSpecialDuty($request);

    echo "Controller executed successfully.\n";
    // echo "Response: " . print_r($response, true) . "\n";

} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Validation Error: " . print_r($e->errors(), true) . "\n";
} catch (\Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
