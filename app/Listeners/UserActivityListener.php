<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\UserActivity;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Request;

class UserActivityListener
{
    /**
     * Handle login events.
     */
    public function handleLogin($event): void
    {
        $this->log($event->user, 'login', 'User logged in');
    }

    /**
     * Handle logout events.
     */
    public function handleLogout($event): void
    {
        if ($event->user) {
            $this->log($event->user, 'logout', 'User logged out');
        }
    }

    /**
     * Handle password reset events.
     */
    public function handlePasswordReset($event): void
    {
        $this->log($event->user, 'password_reset', 'User reset password via email');
    }

    /**
     * Log the activity.
     */
    protected function log($user, $type, $description): void
    {
        UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => $type,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }
}
