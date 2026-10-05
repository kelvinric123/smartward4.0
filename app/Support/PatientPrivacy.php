<?php

namespace App\Support;

/**
 * Privacy Mode for the ward dashboards: how a patient's name and MRN show on
 * the board, chosen per user under Ward Settings > Dashboard Display. A wall
 * screen is in view of visitors and other patients, so a ward can mask who is
 * in each bed. The patient details popup still shows the full identity, since
 * staff must confirm who the patient is before they act.
 */
final class PatientPrivacy
{
    /** Name display modes, the first is the default (no masking). */
    public const NAME_MODES = ['full', 'first_only', 'last_only', 'initials', 'first_last_initial', 'all_asterisk'];

    /** MRN display modes, the first is the default (no masking). */
    public const MRN_MODES = ['full', 'last4', 'hidden'];

    public static function name(?string $name, ?string $mode): ?string
    {
        $name = trim((string) $name);
        if ($name === '' || !in_array($mode, self::NAME_MODES, true) || $mode === 'full') {
            return $name;
        }

        $parts = preg_split('/\s+/u', $name);
        $last = count($parts) - 1;

        // First letter kept, the rest starred: "Michael" => "M******"
        $starRest = fn (string $part) => mb_substr($part, 0, 1) . str_repeat('*', max(mb_strlen($part) - 1, 0));

        return match ($mode) {
            'first_only' => implode(' ', array_map(
                fn ($part, $i) => $i === 0 ? $part : $starRest($part), $parts, array_keys($parts))),
            'last_only' => implode(' ', array_map(
                fn ($part, $i) => $i === $last ? $part : $starRest($part), $parts, array_keys($parts))),
            'initials' => implode('', array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)) . '.', $parts)),
            'first_last_initial' => $last === 0
                ? $parts[0]
                : $parts[0] . ' ' . mb_strtoupper(mb_substr($parts[$last], 0, 1)) . '.',
            'all_asterisk' => implode(' ', array_map(fn ($part) => str_repeat('*', mb_strlen($part)), $parts)),
        };
    }

    /**
     * The MRN as the board shows it, or null when it is hidden. "last4" keeps
     * the last four characters; an MRN of four or fewer is starred in full,
     * as showing its "last four" would show all of it.
     */
    public static function mrn(?string $mrn, ?string $mode): ?string
    {
        if ($mode === 'hidden') {
            return null;
        }

        $mrn = (string) $mrn;
        if ($mode !== 'last4' || $mrn === '') {
            return $mrn;
        }

        $length = mb_strlen($mrn);

        return $length <= 4
            ? str_repeat('*', $length)
            : str_repeat('*', $length - 4) . mb_substr($mrn, -4);
    }

    /** True when either the name or the MRN is masked. */
    public static function isActive(array $dashboardDisplay): bool
    {
        return ($dashboardDisplay['patient_name_mask'] ?? 'full') !== 'full'
            || ($dashboardDisplay['patient_mrn_mask'] ?? 'full') !== 'full';
    }
}
