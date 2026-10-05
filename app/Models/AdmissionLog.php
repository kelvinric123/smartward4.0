<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionLog extends Model
{
    /**
     * Readable labels for the actions written to the log, in the order a
     * stay usually runs through them. Unknown actions fall back to the raw
     * value tidied up.
     */
    public const ACTION_LABELS = [
        'prebook' => 'Prebook',
        'prebook-pending' => 'Prebook (bed pending)',
        'prebook-activated' => 'Prebook activated',
        'cancel-prebook' => 'Prebook cancelled',
        'check-in' => 'Check-in',
        'admit' => 'Admit',
        'transfer' => 'Transfer',
        'discharge_scheduled' => 'Discharge scheduled',
        'pending_discharge' => 'Pending discharge',
        'discharge' => 'Discharge',
        'bed_release' => 'Bed release',
    ];

    public const ACTION_BADGES = [
        'prebook' => 'bg-blue-100 text-blue-800',
        'prebook-pending' => 'bg-sky-100 text-sky-800',
        'prebook-activated' => 'bg-indigo-100 text-indigo-800',
        'cancel-prebook' => 'bg-rose-100 text-rose-800',
        'check-in' => 'bg-purple-100 text-purple-800',
        'admit' => 'bg-green-100 text-green-800',
        'transfer' => 'bg-amber-100 text-amber-800',
        'discharge_scheduled' => 'bg-orange-100 text-orange-800',
        'pending_discharge' => 'bg-orange-100 text-orange-800',
        'discharge' => 'bg-gray-200 text-gray-800',
        'bed_release' => 'bg-slate-100 text-slate-800',
    ];

    /**
     * Columns the Admission Logs page and its printout can show:
     * key => [label, shown by default].
     */
    public const COLUMNS = [
        'time' => ['Date & time', true],
        'action' => ['Status', true],
        'patient' => ['Patient', true],
        'mrn' => ['MRN', true],
        'ward' => ['Ward', true],
        'bed' => ['Bed', true],
        'consultant' => ['Consultant', true],
        'nurse' => ['Nurse', false],
        'gender' => ['Gender', false],
        'age' => ['Age', false],
        'admitted_at' => ['Admitted at', false],
        'booked_at' => ['Booked for', false],
        'source' => ['Source', true],
        'notes' => ['Notes', true],
        'user' => ['Recorded by', true],
    ];

    public static function actionLabel(?string $action): string
    {
        return self::ACTION_LABELS[$action] ?? ucfirst(str_replace(['_', '-'], ' ', (string) $action));
    }

    public static function actionBadgeClass(?string $action): string
    {
        return self::ACTION_BADGES[$action] ?? 'bg-slate-100 text-slate-800';
    }

    /** The column keys shown when nobody has chosen otherwise. */
    public static function defaultColumns(): array
    {
        return array_keys(array_filter(self::COLUMNS, fn (array $column) => $column[1]));
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'adt' => 'ADT/HIS',
            'cplus' => 'C+ Bed Management',
            default => 'Manual',
        };
    }

    public function recordedByLabel(): string
    {
        return $this->user?->name ?? match ($this->source) {
            'adt' => 'System (ADT)',
            'cplus' => 'System (C+)',
            default => 'System',
        };
    }

    protected $fillable = [
        'patient_id',
        'ward_id',
        'to_ward_id', // on a discharge: the ward the patient went on to (an ED visit ending in an admission)
        'user_id',
        'bed_number',
        'action',
        'patient_name',
        'mrn',
        'consultant_name',
        'nurse_name',
        'gender',
        'age',
        'notes',
        'admitted_at',
        'discharged_at',
        'booked_at',
        'source', // 'manual', 'adt' or 'cplus'
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
        'booked_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function toWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'to_ward_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
