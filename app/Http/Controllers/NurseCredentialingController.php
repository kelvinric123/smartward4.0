<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseCredential;
use App\Models\NursePrivilege;
use App\Models\UserActivity;
use App\Support\NurseCredentialing;
use App\Support\NursePrivilegeCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Credentialing and Privileging tab of the nurse edit page. Credentials
 * and privileges are saved from their own forms, apart from the nurse's
 * Update button: one at a time, or the privileges of the built-in list all
 * at once from its tick boxes. Every change is written to User Activity.
 */
class NurseCredentialingController extends Controller
{
    public function storeCredential(Request $request, Nurse $nurse)
    {
        $data = $this->validatedCredential($request);

        $credential = $nurse->credentials()->create($data + [
            'verified_at' => $request->boolean('credential.verified') ? now() : null,
            'verified_by' => $request->boolean('credential.verified') ? auth()->id() : null,
            'recorded_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->log($nurse, "Added credential \"{$credential->title}\" for nurse {$nurse->name}", $credential->getAttributes());

        return $this->backToTab($nurse, "{$credential->title} added.");
    }

    public function updateCredential(Request $request, Nurse $nurse, NurseCredential $credential)
    {
        $data = $this->validatedCredential($request);

        // Keep who first sighted the original; unticking clears it
        $verified = $request->boolean('credential.verified');
        if ($verified && !$credential->isVerified()) {
            $data += ['verified_at' => now(), 'verified_by' => auth()->id()];
        } elseif (!$verified) {
            $data += ['verified_at' => null, 'verified_by' => null];
        }

        $credential->fill($data + ['updated_by' => auth()->id()]);
        $changes = $this->changes($credential);
        $credential->save();

        if ($changes) {
            $this->log($nurse, "Updated credential \"{$credential->title}\" for nurse {$nurse->name}", $changes);
        }

        return $this->backToTab($nurse, "{$credential->title} saved.");
    }

    public function destroyCredential(Nurse $nurse, NurseCredential $credential)
    {
        $credential->delete();
        $this->log($nurse, "Removed credential \"{$credential->title}\" from nurse {$nurse->name}", $credential->getAttributes());

        return $this->backToTab($nurse, "{$credential->title} removed.");
    }

    public function storePrivilege(Request $request, Nurse $nurse)
    {
        $data = $this->validatedPrivilege($request, $nurse);

        $privilege = $nurse->privileges()->create($data + [
            'status_changed_at' => now(),
            'recorded_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $this->log(
            $nurse,
            "Recorded privilege \"{$privilege->name}\" ({$privilege->statusLabel()}) for nurse {$nurse->name}",
            $privilege->getAttributes()
        );

        return $this->backToTab($nurse, "{$privilege->name} recorded as {$privilege->statusLabel()}.");
    }

    public function updatePrivilege(Request $request, Nurse $nurse, NursePrivilege $privilege)
    {
        $data = $this->validatedPrivilege($request, $nurse, $privilege);
        $previousStatus = $privilege->statusLabel();

        $privilege->fill($data + ['updated_by' => auth()->id()]);
        if ($privilege->isDirty('status')) {
            $privilege->status_changed_at = now();
        }
        $statusChanged = $privilege->isDirty('status');
        $changes = $this->changes($privilege);
        $privilege->save();

        if ($changes) {
            $description = $statusChanged
                ? "Changed privilege \"{$privilege->name}\" from {$previousStatus} to {$privilege->statusLabel()} for nurse {$nurse->name}"
                : "Updated privilege \"{$privilege->name}\" for nurse {$nurse->name}";
            $this->log($nurse, $description, $changes);
        }

        return $this->backToTab($nurse, $statusChanged
            ? "{$privilege->name} is now {$privilege->statusLabel()}."
            : "{$privilege->name} saved.");
    }

    public function destroyPrivilege(Nurse $nurse, NursePrivilege $privilege)
    {
        $privilege->delete();
        $this->log($nurse, "Removed privilege \"{$privilege->name}\" from nurse {$nurse->name}", $privilege->getAttributes());

        return $this->backToTab($nurse, "{$privilege->name} removed.");
    }

    /**
     * The tick boxes of the built-in list, saved in one go. Only what was
     * changed on the page is applied ("was" is how each privilege stood when
     * the page was drawn), so one that someone else changed in the meantime
     * is left as they left it. Unticking withdraws a privilege, keeping the
     * record, and needs a reason. A privilege may be ticked without the
     * certificate it needs; the nurse manager is warned.
     */
    public function saveChecklist(Request $request, Nurse $nurse)
    {
        $data = $request->validate([
            'checklist.items' => ['nullable', 'array'],
            'checklist.items.*.held' => ['nullable', 'boolean'],
            'checklist.items.*.status' => ['nullable', Rule::in(NursePrivilege::ACTIVE_STATUSES)],
            'checklist.items.*.was' => ['nullable', Rule::in(NursePrivilege::ACTIVE_STATUSES)],
            'checklist.granted_on' => ['required', 'date', 'before_or_equal:today'],
            'checklist.review' => ['required', Rule::in(['level', 'date', 'none'])],
            'checklist.review_on' => ['nullable', 'required_if:checklist.review,date', 'date', 'after_or_equal:checklist.granted_on'],
            'checklist.approved_by' => ['nullable', 'string', 'max:150'],
            'checklist.reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'checklist.granted_on.before_or_equal' => 'The date granted cannot be in the future.',
            'checklist.review_on.required_if' => 'Pick the review date.',
            'checklist.review_on.after_or_equal' => 'The review date cannot be before the date granted.',
        ], [
            'checklist.granted_on' => 'date granted',
            'checklist.review_on' => 'review date',
            'checklist.approved_by' => 'approved by',
            'checklist.reason' => 'reason',
        ])['checklist'];

        $records = $nurse->privileges()->get()
            ->keyBy(fn (NursePrivilege $privilege) => $privilege->catalogueCode() ?? 'own-' . $privilege->id);

        // What to change: from and to are the active status, or null for not held
        $changes = [];
        foreach (NursePrivilegeCatalogue::items() as $code => $item) {
            $input = $data['items'][$code] ?? [];
            $to = !empty($input['held']) ? ($input['status'] ?? 'granted') : null;
            if ($to === ($input['was'] ?? null)) {
                continue;
            }

            $record = $records->get($code);
            $from = $record?->isActive() ? $record->status : null;
            if ($to !== $from) {
                $changes[] = compact('item', 'record', 'from', 'to');
            }
        }

        if (!$changes) {
            return $this->backToTab($nurse, 'No changes to the privileges.');
        }

        $withdrawn = array_values(array_filter($changes, fn (array $change) => $change['to'] === null));
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($withdrawn && $reason === '') {
            throw ValidationException::withMessages([
                'checklist.reason' => count($withdrawn) === 1
                    ? "Give the reason for withdrawing {$withdrawn[0]['item']['name']}."
                    : 'Give the reason for withdrawing these ' . count($withdrawn) . ' privileges.',
            ]);
        }

        $grantedOn = Carbon::parse($data['granted_on'])->startOfDay();
        $reviewOn = fn (string $level) => match ($data['review']) {
            'level' => NursePrivilege::defaultReviewOn($level, $grantedOn)->toDateString(),
            'date' => $data['review_on'],
            default => null,
        };
        $approvedBy = $data['approved_by'] ?? null;

        DB::transaction(function () use ($changes, $nurse, $grantedOn, $reviewOn, $approvedBy, $reason) {
            foreach ($changes as ['item' => $item, 'record' => $record, 'from' => $from, 'to' => $to]) {
                if (!$record) {
                    $privilege = $nurse->privileges()->create([
                        'code' => $item['code'],
                        'name' => $item['name'],
                        'category' => $item['level'],
                        'status' => $to,
                        'status_changed_at' => now(),
                        'granted_on' => $grantedOn->toDateString(),
                        'review_on' => $reviewOn($item['level']),
                        'approved_by' => $approvedBy,
                        'recorded_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                    $this->log(
                        $nurse,
                        "Recorded privilege \"{$privilege->name}\" ({$privilege->statusLabel()}) for nurse {$nurse->name}",
                        $privilege->getAttributes()
                    );
                    continue;
                }

                $previousStatus = $record->statusLabel();
                $record->fill(['code' => $item['code'], 'status' => $to ?? 'withdrawn', 'updated_by' => auth()->id()]);
                if ($to === null) {
                    $record->notes = $reason;
                } elseif ($from === null) {
                    // Held again after being suspended or withdrawn: a new grant
                    $record->fill([
                        'name' => $item['name'],
                        'category' => $item['level'],
                        'granted_on' => $grantedOn->toDateString(),
                        'review_on' => $reviewOn($item['level']),
                        'approved_by' => $approvedBy,
                        'notes' => null,
                    ]);
                }
                $record->status_changed_at = now();

                $changed = $this->changes($record);
                $record->save();
                $this->log(
                    $nurse,
                    "Changed privilege \"{$record->name}\" from {$previousStatus} to {$record->statusLabel()} for nurse {$nurse->name}",
                    $changed
                );
            }
        });

        $count = fn (callable $test) => count(array_filter($changes, $test));
        $message = 'Privileges saved: ' . implode(', ', array_filter([
            ($n = $count(fn (array $change) => $change['from'] === null)) ? "{$n} granted" : null,
            ($n = $count(fn (array $change) => $change['from'] !== null && $change['to'] !== null)) ? "{$n} changed" : null,
            $withdrawn ? count($withdrawn) . ' withdrawn' : null,
        ])) . '.';

        // Ticked, but the certificate it needs is not on file or has run out
        $certificates = NurseCredentialing::certificatesFor($nurse);
        $unbacked = collect($changes)
            ->filter(fn (array $change) => $change['from'] === null && $change['item']['requires']
                && in_array($certificates[$change['item']['requires']]['key'], NurseCredentialing::CERTIFICATE_AT_RISK, true))
            ->map(function (array $change) use ($certificates) {
                $requires = $change['item']['requires'];
                $status = $certificates[$requires]['key'] === 'missing'
                    ? 'needs ' . NursePrivilegeCatalogue::needs($requires)
                    : $certificates[$requires]['chip'];

                return "{$change['item']['name']} ({$status})";
            });

        $response = $this->backToTab($nurse, $message);

        return $unbacked->isEmpty()
            ? $response
            : $response->with('warning', 'Granted without a valid certificate: ' . $unbacked->implode(', ')
                . '. Add the certificate under Credentials, or the privilege stays at risk.');
    }

    private function validatedCredential(Request $request): array
    {
        $validated = $request->validate([
            'credential.type' => ['required', Rule::in(array_keys(NurseCredential::TYPES))],
            'credential.title' => ['required', 'string', 'max:150'],
            'credential.reference_number' => ['nullable', 'string', 'max:100'],
            'credential.issuing_body' => ['nullable', 'string', 'max:150'],
            'credential.issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'credential.expires_on' => [
                'nullable',
                'required_if:credential.type,' . implode(',', NurseCredential::EXPIRING_TYPES),
                'date',
                'after_or_equal:credential.issued_on',
            ],
            'credential.verified' => ['nullable', 'boolean'],
            'credential.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'credential.issued_on.before_or_equal' => 'The issue date cannot be in the future.',
            'credential.expires_on.required_if' => 'Enter the expiry date. APCs and life support certificates always have one.',
            'credential.expires_on.after_or_equal' => 'The expiry date cannot be before the issue date.',
        ], [
            'credential.type' => 'type',
            'credential.title' => 'title',
            'credential.reference_number' => 'reference number',
            'credential.issuing_body' => 'issuing body',
            'credential.issued_on' => 'issue date',
            'credential.expires_on' => 'expiry date',
            'credential.notes' => 'notes',
        ])['credential'];

        unset($validated['verified']);

        return $validated;
    }

    private function validatedPrivilege(Request $request, Nurse $nurse, ?NursePrivilege $privilege = null): array
    {
        // Match the list's spelling before checking for a duplicate. One of the
        // built-in list keeps the list's name, whatever was typed over it.
        $listed = NursePrivilegeCatalogue::item($privilege?->catalogueCode());
        $input = $request->input('privilege');
        if (is_array($input) && is_string($input['name'] ?? null)) {
            $input['name'] = $listed['name'] ?? NursePrivilege::canonicalName($input['name']);
            $request->merge(['privilege' => $input]);
        }

        $data = $request->validate([
            'privilege.name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('nurse_privileges', 'name')->where('nurse_id', $nurse->id)->ignore($privilege?->id),
            ],
            'privilege.category' => ['required', Rule::in(array_keys(NursePrivilege::CATEGORIES))],
            'privilege.status' => ['required', Rule::in(array_keys(NursePrivilege::STATUSES))],
            'privilege.granted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'privilege.review_on' => ['nullable', 'date', 'after_or_equal:privilege.granted_on'],
            'privilege.approved_by' => ['nullable', 'string', 'max:150'],
            'privilege.notes' => [
                'nullable',
                'required_if:privilege.status,' . implode(',', NursePrivilege::REASON_REQUIRED),
                'string',
                'max:1000',
            ],
        ], [
            'privilege.name.unique' => 'This nurse already has :input on record. Edit that entry instead.',
            'privilege.granted_on.before_or_equal' => 'The date granted cannot be in the future.',
            'privilege.review_on.after_or_equal' => 'The review date cannot be before the date it was granted.',
            'privilege.notes.required_if' => 'Give the reason for suspending or withdrawing this privilege.',
        ], [
            'privilege.name' => 'privilege',
            'privilege.category' => 'level',
            'privilege.status' => 'status',
            'privilege.granted_on' => 'date granted',
            'privilege.review_on' => 'review date',
            'privilege.approved_by' => 'approved by',
            'privilege.notes' => 'notes',
        ])['privilege'];

        // A privilege of the built-in list is linked to it and takes its level
        $item = NursePrivilegeCatalogue::item(NursePrivilegeCatalogue::codeForName($data['name']));
        $taken = $item && $nurse->privileges()
            ->where('code', $item['code'])
            ->when($privilege, fn ($query) => $query->whereKeyNot($privilege->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'privilege.name' => "This nurse already has {$item['name']} on record. Edit that entry instead.",
            ]);
        }

        $data['code'] = $item['code'] ?? null;
        if ($item) {
            $data['category'] = $item['level'];
        }

        return $data;
    }

    /**
     * The fields about to change, as old => new, leaving out the bookkeeping.
     * Raw values, so dates are logged as the dates entered rather than
     * shifted into UTC.
     */
    private function changes(NurseCredential|NursePrivilege $record): array
    {
        $changes = [];
        foreach ($record->getDirty() as $field => $new) {
            if (in_array($field, ['updated_by', 'updated_at', 'status_changed_at'], true)) {
                continue;
            }
            $changes[$field] = ['old' => $record->getRawOriginal($field), 'new' => $new];
        }

        return $changes;
    }

    private function log(Nurse $nurse, string $description, array $properties): void
    {
        UserActivity::create([
            'user_id' => auth()->id(),
            'activity_type' => 'nurse_credentialing',
            'description' => $description,
            'properties' => ['nurse_id' => $nurse->id] + $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function backToTab(Nurse $nurse, string $message)
    {
        return redirect()
            ->route('nurses.edit', ['nurse' => $nurse, 'tab' => NurseCredentialing::TAB])
            ->with('success', $message);
    }
}
