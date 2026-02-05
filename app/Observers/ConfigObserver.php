<?php

namespace App\Observers;

use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ConfigObserver
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->logActivity($model, 'created', 'Created new configuration', $model->getAttributes());
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->logActivity($model, 'updated', 'Updated configuration', [
            'old' => $model->getOriginal(),
            'new' => $model->getChanges(),
        ]);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->logActivity($model, 'deleted', 'Deleted configuration', $model->getAttributes());
    }

    /**
     * Log the activity.
     */
    protected function logActivity(Model $model, string $action, string $description, array $properties): void
    {
        // Skip if not authenticated (e.g., seeding or background jobs)
        // Unless it's a critical system change we want to track anyway, but usually we need a user.
        // For system changes, we might log with user_id null.

        $user = Auth::user();
        $modelName = class_basename($model);

        UserActivity::create([
            'user_id' => $user ? $user->id : null,
            'activity_type' => "config_{$action}",
            'description' => "{$description}: {$modelName}",
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
