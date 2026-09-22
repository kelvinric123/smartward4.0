<?php

namespace App\Services\NurseApp;

use App\Models\Nurse;
use App\Models\Patient;
use App\Models\User;
use App\Models\WardScheduleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Who is using the nurse app, what they may see, and who their charting is
 * recorded as.
 *
 * The app signs in a Nurse (bearer token), but every "recorded by" column in
 * SmartWard - I/O entries, doses, orders, alert responses - points at a
 * User. So app actions are recorded as the nurse's linked user (the "LDAP
 * Binding" on the Nurse page). A nurse with no linked user gets an app-only
 * account the first time they record something: named after them, role
 * nurse, deactivated so it can never sign in to the web app, and linked to
 * their nurse profile. The ward dashboard, the discharge summary and every
 * other screen then show the nurse's name like any other entry.
 */
class NurseAppActor
{
    /** App-only accounts use a reserved (.invalid) domain, so no mail is ever sent. */
    private const APP_ACCOUNT_EMAIL = 'nurse-app-%d@smartward.invalid';

    /**
     * The nurse the request's bearer token belongs to, if it is valid.
     */
    public static function nurse(Request $request): ?Nurse
    {
        $token = $request->bearerToken() ?: $request->input('token');

        return $token ? Nurse::findByAppToken((string) $token) : null;
    }

    /**
     * A nurse may open an admitted patient on their own ward, on a ward they
     * are rostered to today (or were last night), or one they are the named
     * nurse for - the same patients the ward dashboard shows that ward.
     */
    public static function canAccess(Nurse $nurse, Patient $patient): bool
    {
        if (!$patient->is_active || !in_array($patient->status, [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE], true)) {
            return false;
        }

        if ((int) $patient->nurse_id === (int) $nurse->id) {
            return true;
        }

        return $patient->ward_id && in_array((int) $patient->ward_id, self::wardIds($nurse), true);
    }

    /**
     * The nurse's own ward plus every ward they are rostered to today or
     * yesterday (a night shift runs past midnight).
     */
    public static function wardIds(Nurse $nurse): array
    {
        return WardScheduleAssignment::where('nurse_id', $nurse->id)
            ->whereIn('scheduled_date', [now()->toDateString(), now()->subDay()->toDateString()])
            ->pluck('ward_id')
            ->push($nurse->ward_id)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The users.id the nurse's app actions are recorded under (see class
     * docblock). Never a nurse id: those columns are foreign keys to users.
     */
    public static function userId(Nurse $nurse): int
    {
        if ($nurse->user_id && ($user = User::find($nurse->user_id))) {
            self::keepNameInStep($user, $nurse);

            return (int) $user->id;
        }

        return DB::transaction(function () use ($nurse) {
            // Two first actions arriving together must not make two accounts
            $locked = Nurse::whereKey($nurse->id)->lockForUpdate()->first();

            if ($locked->user_id && User::whereKey($locked->user_id)->exists()) {
                $nurse->user_id = $locked->user_id;

                return (int) $locked->user_id;
            }

            $email = sprintf(self::APP_ACCOUNT_EMAIL, $nurse->id);
            // Unusable random password (hashed by the model cast), never an
            // LDAP user (LDAP sign-in does not check deactivated_at)
            $user = User::where('email', $email)->first() ?? User::create([
                'name' => $nurse->name,
                'email' => $email,
                'password' => Str::random(64),
                'role' => User::ROLE_NURSE,
                'is_ldap_user' => false,
                'deactivated_at' => now(),
            ]);

            $locked->forceFill(['user_id' => $user->id])->save();
            $nurse->user_id = $user->id;

            Log::info('Nurse app: linked an app-only account for recording', [
                'nurse_id' => $nurse->id,
                'user_id' => $user->id,
            ]);

            return (int) $user->id;
        });
    }

    /** Whether a user is one of these app-only accounts. */
    public static function isAppAccount(?User $user): bool
    {
        return $user !== null && Str::endsWith((string) $user->email, '@smartward.invalid')
            && Str::startsWith((string) $user->email, 'nurse-app-');
    }

    /** An app-only account follows the nurse's name if it is changed later. */
    private static function keepNameInStep(User $user, Nurse $nurse): void
    {
        if (self::isAppAccount($user) && $user->name !== $nurse->name && filled($nurse->name)) {
            $user->forceFill(['name' => $nurse->name])->save();
        }
    }
}
