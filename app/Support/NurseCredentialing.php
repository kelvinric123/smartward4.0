<?php

namespace App\Support;

use App\Models\Nurse;
use App\Models\NurseCredential;
use App\Models\NursePrivilege;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything the Credentialing and Privileging tab of the nurse edit page
 * shows: the credentials with where each one stands, the privileges as the
 * built-in checklist by field (plus any of the hospital's own), whether the
 * certificates they need are on file, the counts for the summary cards and
 * the form suggestions.
 */
class NurseCredentialing
{
    public const TAB = 'credentialing';

    /**
     * Where a certificate stands that makes a privilege held with it at risk.
     */
    public const CERTIFICATE_AT_RISK = ['missing', 'expired'];

    public static function forNurse(Nurse $nurse, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        $credentials = self::credentialRows($nurse, $today);
        $current = $credentials->reject(fn (array $row) => $row['superseded']);
        $apc = $current->first(fn (array $row) => $row['credential']->type === 'apc');

        $certificates = self::certificates($current);
        $privileges = self::privilegeRows($nurse, $today, $certificates);
        $statusCount = fn (string $status) => $privileges->filter(fn (array $row) => $row['privilege']->status === $status)->count();
        $reviewCount = fn (string $key) => $privileges->where('review.key', $key)->count();
        $atRisk = $privileges->where('at_risk', true);

        $counts = [
            'credentials' => $credentials->count(),
            'expired' => $current->where('expiry.key', 'expired')->count(),
            'expiring' => $current->where('expiry.key', 'expiring')->count(),
            'unverified' => $current->filter(fn (array $row) => !$row['credential']->isVerified())->count(),
            'privileges' => $privileges->count(),
            'granted' => $statusCount('granted'),
            'supervised' => $statusCount('supervised'),
            'suspended' => $statusCount('suspended'),
            'withdrawn' => $statusCount('withdrawn'),
            'review_overdue' => $reviewCount('overdue'),
            'review_due' => $reviewCount('due'),
            'at_risk' => $atRisk->count(),
            'at_risk_lapsed' => $atRisk->where('certificate.key', 'expired')->count(),
        ];

        return [
            'credentials' => $credentials,
            'privileges' => $privileges,
            'checklist' => self::checklist($privileges, $certificates),
            'other' => $privileges->whereNull('code')->values(),
            'certificates' => $certificates,
            'apc' => $apc,
            'counts' => $counts,
            'attention' => self::attention($counts),
            'presets' => self::presets($nurse, $today),
            'suggestions' => self::suggestions($today),
            'today' => $today,
        ];
    }

    /**
     * Where each certificate a privilege can need stands for $nurse today.
     *
     * @return array<string, array{key: string, chip: string, title: string}>
     */
    public static function certificatesFor(Nurse $nurse, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return self::certificates(self::credentialRows($nurse, $today)->reject(fn (array $row) => $row['superseded']));
    }

    /**
     * Where each certificate a privilege can need stands, going by the best
     * current credential that is one: in date beats expiring beats expired.
     * "chip" is the short form for badges, "title" names the credential.
     *
     * @return array<string, array{key: string, chip: string, title: string}>
     */
    private static function certificates(Collection $current): array
    {
        $rank = ['no_expiry' => 0, 'valid' => 0, 'expiring' => 1, 'expired' => 2];
        $statuses = [];

        foreach (NursePrivilegeCatalogue::CERTIFICATES as $key => $meta) {
            $best = $current
                ->filter(fn (array $row) => NursePrivilegeCatalogue::satisfies($key, $row['credential']))
                ->sortBy(fn (array $row) => [
                    $rank[$row['expiry']['key']] ?? 3,
                    -($row['credential']->expires_on?->timestamp ?? PHP_INT_MAX),
                ])
                ->first();

            if (!$best) {
                $needs = NursePrivilegeCatalogue::needs($key);
                $statuses[$key] = ['key' => 'missing', 'chip' => "Needs {$needs}", 'title' => "No {$needs} on this nurse's file"];
                continue;
            }

            $credential = $best['credential'];
            $date = $credential->expires_on?->format('j M Y');
            $statuses[$key] = match ($best['expiry']['key']) {
                'expired' => ['key' => 'expired', 'chip' => "{$meta['label']} expired {$date}"],
                'expiring' => ['key' => 'expiring', 'chip' => "{$meta['label']} expires {$date}"],
                'valid' => ['key' => 'valid', 'chip' => "{$meta['label']} valid until {$date}"],
                default => ['key' => 'valid', 'chip' => "{$meta['label']} on file"],
            } + ['title' => $credential->title];
        }

        return $statuses;
    }

    /**
     * The built-in list by section, each privilege with this nurse's record
     * of it, if any. A general section starts open; a specialty one only
     * when the nurse works in it (holds its certificate or any of its
     * privileges). "certificate" on a privilege is the one it needs of its
     * own; a section-wide one is on the section.
     */
    private static function checklist(Collection $privileges, array $certificates): array
    {
        $byCode = $privileges->whereNotNull('code')->keyBy('code');
        $sections = [];

        foreach (NursePrivilegeCatalogue::SECTIONS as $key => $section) {
            $items = [];
            foreach ($section['items'] as $code => $entry) {
                $row = $byCode->get($code);
                $items[] = NursePrivilegeCatalogue::item($code) + [
                    'row' => $row,
                    'held' => $row !== null && $row['privilege']->isActive(),
                    'certificate' => isset($entry['requires']) ? $certificates[$entry['requires']] : null,
                ];
            }

            $certificate = isset($section['requires']) ? $certificates[$section['requires']] : null;
            $recorded = count(array_filter($items, fn (array $item) => $item['row'] !== null));
            $specialty = $section['specialty'] ?? false;

            $sections[$key] = [
                'key' => $key,
                'label' => $section['label'],
                'specialty' => $specialty,
                'certificate' => $certificate,
                'items' => $items,
                'held' => count(array_filter($items, fn (array $item) => $item['held'])),
                'open' => !$specialty || $recorded > 0 || in_array($certificate['key'] ?? null, ['valid', 'expiring'], true),
            ];
        }

        return $sections;
    }

    /**
     * Credentials in display order (by type, current before superseded,
     * newest first), each with its expiry status. A renewed APC or card
     * supersedes the older ones, which then no longer count as expired.
     */
    private static function credentialRows(Nurse $nurse, CarbonInterface $today): Collection
    {
        $credentials = $nurse->credentials()->with('verifiedBy:id,name')->get();
        $superseded = self::supersededIds($credentials);
        $typeOrder = array_flip(array_keys(NurseCredential::TYPES));

        return $credentials
            ->map(fn (NurseCredential $credential) => [
                'credential' => $credential,
                'superseded' => in_array($credential->id, $superseded, true),
                'expiry' => $credential->expiryStatus($today),
            ])
            ->sort(function (array $a, array $b) use ($typeOrder) {
                $key = fn (array $row) => [
                    $typeOrder[$row['credential']->type] ?? PHP_INT_MAX,
                    $row['superseded'] ? 1 : 0,
                ];

                return $key($a) <=> $key($b)
                    ?: self::sortDate($b['credential']) <=> self::sortDate($a['credential']);
            })
            ->values();
    }

    private static function sortDate(NurseCredential $credential): int
    {
        return ($credential->expires_on ?? $credential->issued_on ?? $credential->created_at)?->timestamp ?? 0;
    }

    /**
     * Credentials replaced by a newer one: every APC but the latest, and for
     * other types every card but the latest with the same title.
     */
    private static function supersededIds(Collection $credentials): array
    {
        return $credentials
            ->filter(fn (NurseCredential $credential) => $credential->expires_on !== null)
            ->groupBy(fn (NurseCredential $credential) => $credential->type === 'apc'
                ? 'apc'
                : $credential->type . '|' . Str::lower(trim($credential->title)))
            ->flatMap(fn (Collection $group) => $group
                ->sortByDesc(fn (NurseCredential $credential) => $credential->expires_on->timestamp)
                ->slice(1)
                ->pluck('id'))
            ->values()
            ->all();
    }

    /**
     * Privileges the nurse holds first (granted, supervised), then suspended
     * and withdrawn, each by name, with the review status of the held ones,
     * their entry in the built-in list (null for the hospital's own) and the
     * certificate that entry needs. A privilege held without that
     * certificate, or with one that has run out, is at risk.
     */
    private static function privilegeRows(Nurse $nurse, CarbonInterface $today, array $certificates): Collection
    {
        $statusOrder = array_flip(array_keys(NursePrivilege::STATUSES));

        return $nurse->privileges()->get()
            ->map(function (NursePrivilege $privilege) use ($today, $certificates) {
                $code = $privilege->catalogueCode();
                $requires = NursePrivilegeCatalogue::item($code)['requires'] ?? null;
                $certificate = $requires ? $certificates[$requires] : null;

                return [
                    'privilege' => $privilege,
                    'code' => $code,
                    'review' => $privilege->reviewStatus($today),
                    'certificate' => $certificate,
                    'at_risk' => $privilege->isActive() && in_array($certificate['key'] ?? null, self::CERTIFICATE_AT_RISK, true),
                ];
            })
            ->sort(fn (array $a, array $b) => [
                $statusOrder[$a['privilege']->status] ?? PHP_INT_MAX,
                Str::lower($a['privilege']->name),
            ] <=> [
                $statusOrder[$b['privilege']->status] ?? PHP_INT_MAX,
                Str::lower($b['privilege']->name),
            ])
            ->values();
    }

    /**
     * What the tab's marker says: red when something has already run out,
     * amber when something runs out soon, nothing otherwise.
     *
     * @return array{level: string, text: string}|null
     */
    private static function attention(array $counts): ?array
    {
        $parts = array_filter([
            $counts['expired'] ? $counts['expired'] . ' expired' : null,
            $counts['review_overdue'] ? $counts['review_overdue'] . ' ' . Str::plural('review', $counts['review_overdue']) . ' overdue' : null,
            $counts['at_risk'] ? $counts['at_risk'] . ' ' . Str::plural('privilege', $counts['at_risk']) . ' at risk' : null,
            $counts['expiring'] ? $counts['expiring'] . ' expiring soon' : null,
            $counts['review_due'] ? $counts['review_due'] . ' ' . Str::plural('review', $counts['review_due']) . ' due soon' : null,
        ]);

        if (!$parts) {
            return null;
        }

        // A privilege whose certificate has run out is as urgent as the certificate
        return [
            'level' => ($counts['expired'] || $counts['review_overdue'] || $counts['at_risk_lapsed']) ? 'red' : 'amber',
            'text' => implode(', ', $parts),
        ];
    }

    /**
     * Values the Add credential form fills in when a type is picked. The APC
     * suggested is the one after the latest on file, so a renewal is one click.
     */
    public static function presets(Nurse $nurse, ?CarbonInterface $today = null): array
    {
        $year = ($today ?? now())->year;
        $latestApc = $nurse->credentials()->where('type', 'apc')->max('expires_on');
        if ($latestApc && ($latestYear = (int) substr($latestApc, 0, 4)) >= $year) {
            $year = $latestYear + 1;
        }

        return [
            'apc' => [
                'title' => NurseCredential::apcTitle($year),
                'issuing_body' => NurseCredential::NURSING_BOARD,
                'expires_on' => "{$year}-12-31",
            ],
            'registration' => [
                'issuing_body' => NurseCredential::NURSING_BOARD,
            ],
        ];
    }

    /**
     * Datalist options: the built-in lists plus whatever this hospital has
     * already typed in for other nurses.
     */
    private static function suggestions(CarbonInterface $today): array
    {
        $merge = fn (array $builtIn, Collection $used) => collect($builtIn)
            ->merge($used)
            ->filter()
            ->unique(fn (string $value) => Str::lower($value))
            ->values()
            ->all();

        $titles = NurseCredential::TITLE_SUGGESTIONS;
        $titles['apc'] = [NurseCredential::apcTitle($today->year), NurseCredential::apcTitle($today->year + 1)];

        return [
            'titles' => $titles,
            'issuers' => $merge(
                [NurseCredential::NURSING_BOARD, 'Ministry of Health Malaysia'],
                NurseCredential::query()->distinct()->orderBy('issuing_body')->limit(100)->pluck('issuing_body')
            ),
            'privileges' => $merge(
                array_column(NursePrivilegeCatalogue::items(), 'name'),
                NursePrivilege::query()->distinct()->orderBy('name')->limit(200)->pluck('name')
            ),
            'approvers' => $merge(
                [NursePrivilege::DEFAULT_APPROVER],
                NursePrivilege::query()->distinct()->orderBy('approved_by')->limit(100)->pluck('approved_by')
            ),
        ];
    }
}
