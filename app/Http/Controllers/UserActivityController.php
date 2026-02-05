<?php

namespace App\Http\Controllers;

use App\Models\UserActivity;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activities = UserActivity::with('user')->latest()->paginate(20);
        return view('admin.user-activities.index', compact('activities'));
    }

    /**
     * Export activities to CSV.
     */
    public function export(Request $request)
    {
        $tab = $request->input('tab', 'user_activities');
        $fileName = 'activities_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($tab) {
            $file = fopen('php://output', 'w');

            // BOM for Excel to read UTF-8 correctly
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($tab === 'config_changes') {
                fputcsv($file, ['Time', 'User', 'Action', 'Description', 'Property', 'Old Value', 'New Value']);

                $query = UserActivity::with('user')
                    ->whereIn('activity_type', ['config_created', 'config_updated', 'config_deleted'])
                    ->latest();

                $query->chunk(100, function ($activities) use ($file) {
                    foreach ($activities as $activity) {
                        $userName = $activity->user ? $activity->user->name : 'System';
                        $action = ucfirst(str_replace('config_', '', $activity->activity_type));
                        $desc = $activity->description;
                        $time = $activity->created_at->format('Y-m-d H:i:s');

                        $properties = $activity->properties;

                        if (is_array($properties)) {
                            // If it's an update, we might have 'old' and 'new' keys
                            if (isset($properties['old']) || isset($properties['new'])) {
                                // It's likely a standard update with old/new
                                $oldValues = $properties['old'] ?? [];
                                $newValues = $properties['new'] ?? [];

                                // Get all keys from both
                                $keys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

                                foreach ($keys as $key) {
                                    $old = is_array($oldValues[$key] ?? null) ? json_encode($oldValues[$key] ?? null) : ($oldValues[$key] ?? '');
                                    $new = is_array($newValues[$key] ?? null) ? json_encode($newValues[$key] ?? null) : ($newValues[$key] ?? '');

                                    // Only show changed values or if forced
                                    fputcsv($file, [$time, $userName, $action, $desc, $key, $old, $new]);
                                }
                            } else {
                                // It's a create or delete, or simple array
                                foreach ($properties as $key => $value) {
                                    $valStr = is_array($value) ? json_encode($value) : $value;
                                    $old = $activity->activity_type === 'config_deleted' ? $valStr : '';
                                    $new = $activity->activity_type === 'config_created' ? $valStr : '';

                                    fputcsv($file, [$time, $userName, $action, $desc, $key, $old, $new]);
                                }
                            }
                        } else {
                            // Properties is null or empty
                            fputcsv($file, [$time, $userName, $action, $desc, '-', '-', '-']);
                        }
                    }
                });

            } else {
                // User Activities
                fputcsv($file, ['Time', 'User', 'Email', 'Activity Type', 'Description', 'IP Address']);

                $query = UserActivity::with('user')
                    ->whereNotIn('activity_type', ['config_created', 'config_updated', 'config_deleted'])
                    ->latest();

                $query->chunk(100, function ($activities) use ($file) {
                    foreach ($activities as $activity) {
                        fputcsv($file, [
                            $activity->created_at->format('Y-m-d H:i:s'),
                            $activity->user ? $activity->user->name : 'Unknown',
                            $activity->user ? $activity->user->email : '',
                            ucfirst(str_replace('_', ' ', $activity->activity_type)),
                            $activity->description,
                            $activity->ip_address,
                        ]);
                    }
                });
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
