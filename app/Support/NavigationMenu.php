<?php

namespace App\Support;

use App\Models\Hospital;

/**
 * The left sidebar's menu items and which of them a hospital shows, set on the
 * Theme & Menu tab of its edit page.
 *
 * Hiding an item only takes it out of the menu. The role checks in
 * layouts/navigation.blade.php still decide who sees what, and the pages stay
 * open by link to the people allowed to use them.
 */
final class NavigationMenu
{
    /**
     * The sidebar in order: top-level links (key => label) and sections, whose
     * items fold under a heading. Item keys are what hidden_nav_items stores.
     */
    public const ENTRIES = [
        'dashboard' => 'Dashboard',
        'command-center' => 'Command Center',
        'command-center-v2' => 'Command Center V2',
        'patient' => ['label' => 'Patient', 'items' => [
            'patients' => 'Patient List',
        ]],
        'admin' => ['label' => 'Admin Management', 'items' => [
            'hospitals' => 'Hospital',
            'specialties' => 'Specialties',
            'consultants' => 'Consultants',
            'anaesthetists' => 'Anaesthetist',
            'nurses' => 'Nurses',
            'users' => 'Users Management',
            'patient-flow-command-centres' => 'Patient Flow Command Centre',
            'diet-types' => 'Diet Types & Isolation',
        ]],
        'wardManagement' => ['label' => 'Ward Management', 'items' => [
            'wards' => 'Wards',
            'ward-types' => 'Ward Type',
            'beds' => 'Beds',
            'discharge-summaries' => 'Discharge Summary',
        ]],
        'schedule' => ['label' => 'Schedule', 'items' => [
            'ward-schedule' => 'Ward Schedule',
            'ai-schedule' => 'AI Nurse Schedule',
        ]],
        'vitalSign' => ['label' => 'Vital Sign', 'items' => [
            'vital-signs' => 'Record',
        ]],
        'ward-dashboard' => 'Ward Dashboard',
        'critical-care-dashboard' => 'Critical Care Ward Dashboard',
        'integration' => ['label' => 'Integration', 'items' => [
            'ldap' => 'LDAP Integration',
            'vital-sign-integration' => 'Vital Sign Integration',
            'infusion-integration' => 'Infusion Integration',
            'adt-config' => 'ADT Config',
            'adt-test' => 'ADT Test',
            'ecg' => 'ECG Admin',
            'ekad' => 'EKad (E-Ink)',
            'integration-demo' => 'Demo',
        ]],
        'appLogs' => ['label' => 'Application Logs', 'items' => [
            'user-activities' => 'User Activities',
        ]],
    ];

    /** Always in the menu, so the page that hides items stays reachable from it. */
    public const ALWAYS_SHOWN = ['hospitals'];

    /** @var list<string> */
    private array $hidden;

    /** @param array<int, string> $hidden item keys to leave out of the menu */
    public function __construct(array $hidden = [])
    {
        $this->hidden = array_values(array_diff($hidden, self::ALWAYS_SHOWN));
    }

    public static function for(?Hospital $hospital): self
    {
        return new self($hospital?->hidden_nav_items ?? []);
    }

    public function shows(string $key): bool
    {
        return !in_array($key, $this->hidden, true);
    }

    /** Whether any of the items is shown: a section stays in the menu while one of its items is. */
    public function showsAny(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if ($this->shows($key)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function hidden(): array
    {
        return $this->hidden;
    }

    /**
     * Every item for the settings form, grouped: the top-level links together
     * first, then each section.
     *
     * @return array<string, array{label: string, items: array<string, string>}>
     */
    public static function groups(): array
    {
        $groups = ['main' => ['label' => 'Main menu', 'items' => []]];

        foreach (self::ENTRIES as $key => $entry) {
            if (is_string($entry)) {
                $groups['main']['items'][$key] = $entry;
            } else {
                $groups[$key] = $entry;
            }
        }

        return $groups;
    }

    /** @return list<string> every item key, in menu order */
    public static function keys(): array
    {
        return array_merge(...array_map(
            fn ($group) => array_keys($group['items']),
            array_values(self::groups()),
        ));
    }

    /**
     * The items to hide from the form's menu[key] => 0/1 fields. Only items
     * explicitly switched off are hidden, so an item added to the menu later
     * shows until someone hides it.
     *
     * @param  array<string, mixed>  $submitted
     * @return list<string>
     */
    public static function hiddenFrom(array $submitted): array
    {
        return array_values(array_filter(
            self::keys(),
            fn ($key) => array_key_exists($key, $submitted)
                && !filter_var($submitted[$key], FILTER_VALIDATE_BOOLEAN)
                && !in_array($key, self::ALWAYS_SHOWN, true),
        ));
    }
}
