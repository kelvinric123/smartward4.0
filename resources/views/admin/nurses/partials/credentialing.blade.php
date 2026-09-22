{{--
    Credentialing and Privileging tab of the nurse edit page.
    Credentials and privileges are records of their own, so each has a small
    form here posting to NurseCredentialingController; the nurse's Update
    button does not save them. Expects $nurse and $credentialing
    (App\Support\NurseCredentialing::forNurse), plus the edit page's field
    classes ($field, $label, $hint, $error).
--}}
@php
    $c = $credentialing;
    $n = $c['counts'];

    // Reopen the form a failed save came from
    $openForm = old('active_tab') === 'credentialing' ? old('_form') : null;

    $chipBase = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap';
    $chip = [
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'green' => 'bg-green-50 text-green-700 ring-green-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'gray' => 'bg-gray-100 text-gray-600 ring-gray-200',
    ];
    $expiryTone = ['expired' => 'red', 'expiring' => 'amber', 'valid' => 'green', 'no_expiry' => 'gray'];
    $statusTone = ['granted' => 'green', 'supervised' => 'blue', 'suspended' => 'red', 'withdrawn' => 'gray'];
    $reviewTone = ['overdue' => 'red', 'due' => 'amber'];

    $apc = $c['apc'];
    $apcTone = $apc ? $expiryTone[$apc['expiry']['key']] : 'gray';
    $apcCard = [
        'red' => 'border-red-200 bg-red-50/70',
        'amber' => 'border-amber-200 bg-amber-50/70',
        'green' => 'border-green-200 bg-green-50/70',
        'gray' => 'border-gray-200 bg-gray-50',
    ][$apcTone];
    $apcText = ['red' => 'text-red-700', 'amber' => 'text-amber-800', 'green' => 'text-green-700', 'gray' => 'text-gray-700'][$apcTone];

    $addButton = 'inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 rounded-lg font-semibold text-sm text-white shadow-md transition-all';
    $linkButton = 'text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline';
    $removeButton = 'text-xs font-medium text-red-600 hover:text-red-800 hover:underline';
@endphp

<div class="p-6 sm:p-8" x-data="{
        open: @js($openForm),
        toggle(form) { this.open = this.open === form ? null : form },
    }">

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
            {{ session('warning') }}
        </div>
    @endif

    <div x-show="dirty" x-cloak class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        You have unsaved changes on the other tabs. Save them with <span class="font-semibold">Update Nurse</span>
        first: saving anything on this tab reloads the page, and they would be lost.
    </div>

    <div class="space-y-8">
        {{-- ---------------------------------------------------- Summary --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-xl border p-4 {{ $apcCard }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Practising certificate</p>
                @if($apc)
                    <p class="mt-1 text-lg font-bold {{ $apcText }}">{{ $apc['expiry']['label'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ $apc['credential']->title }}
                        @if($apc['credential']->reference_number)
                            · No. {{ $apc['credential']->reference_number }}
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-lg font-bold text-gray-700">Not recorded</p>
                    <p class="mt-0.5 text-xs text-gray-500">Add the nurse's current APC under Credentials.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Credentials</p>
                @if($n['credentials'])
                    <p class="mt-1 text-lg font-bold text-gray-800">{{ $n['credentials'] }} on file</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if($n['expired'])
                            <span class="{{ $chipBase }} {{ $chip['red'] }}">{{ $n['expired'] }} expired</span>
                        @endif
                        @if($n['expiring'])
                            <span class="{{ $chipBase }} {{ $chip['amber'] }}">{{ $n['expiring'] }} expiring soon</span>
                        @endif
                        @if($n['unverified'])
                            <span class="{{ $chipBase }} {{ $chip['amber'] }}">{{ $n['unverified'] }} not verified</span>
                        @endif
                        @if(!$n['expired'] && !$n['expiring'] && !$n['unverified'])
                            <span class="{{ $chipBase }} {{ $chip['green'] }}">All current and verified</span>
                        @endif
                    </div>
                @else
                    <p class="mt-1 text-lg font-bold text-gray-700">None recorded</p>
                    <p class="mt-0.5 text-xs text-gray-500">Licences and certificates appear here once added.</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Clinical privileges</p>
                @if($n['privileges'])
                    <p class="mt-1 text-lg font-bold text-gray-800">{{ $n['granted'] + $n['supervised'] }} active</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if($n['granted'])
                            <span class="{{ $chipBase }} {{ $chip['green'] }}">{{ $n['granted'] }} independent</span>
                        @endif
                        @if($n['supervised'])
                            <span class="{{ $chipBase }} {{ $chip['blue'] }}">{{ $n['supervised'] }} supervised</span>
                        @endif
                        @if($n['suspended'])
                            <span class="{{ $chipBase }} {{ $chip['red'] }}">{{ $n['suspended'] }} suspended</span>
                        @endif
                        @if($n['at_risk'])
                            <span class="{{ $chipBase }} {{ $chip[$n['at_risk_lapsed'] ? 'red' : 'amber'] }}"
                                title="Held without a valid certificate">{{ $n['at_risk'] }} at risk</span>
                        @endif
                        @if($n['review_overdue'])
                            <span class="{{ $chipBase }} {{ $chip['red'] }}">{{ $n['review_overdue'] }} {{ Str::plural('review', $n['review_overdue']) }} overdue</span>
                        @endif
                        @if($n['review_due'])
                            <span class="{{ $chipBase }} {{ $chip['amber'] }}">{{ $n['review_due'] }} {{ Str::plural('review', $n['review_due']) }} due soon</span>
                        @endif
                    </div>
                @else
                    <p class="mt-1 text-lg font-bold text-gray-700">None recorded</p>
                    <p class="mt-0.5 text-xs text-gray-500">Tick what the nurse is cleared for under Clinical privileges.</p>
                @endif
            </div>
        </div>

        {{-- ------------------------------------------------ Credentials --}}
        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex-1 min-w-[14rem]">
                    <h3 class="text-base font-semibold text-gray-800">Credentials</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Licences, registrations and certificates. Add a renewal as a
                        new entry: the older one is kept as history and marked superseded.</p>
                </div>
                <button type="button" @click="toggle('credential-new')" class="{{ $addButton }}"
                    :aria-expanded="open === 'credential-new'">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add credential
                </button>
            </div>

            <div x-show="open === 'credential-new'" x-cloak>
                @include('admin.nurses.partials.credential-form', ['credential' => null, 'formId' => 'credential-new'])
            </div>

            @if($c['credentials']->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 px-6 py-8 text-center text-sm text-gray-500">
                    No credentials recorded yet. Start with the nurse's current Annual Practising Certificate.
                </div>
            @else
                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                    @foreach($c['credentials'] as $row)
                        @php
                            $credential = $row['credential'];
                            $formId = 'credential-' . $credential->id;
                            $expiry = $row['expiry'];
                            $meta = array_filter([
                                $credential->reference_number ? 'No. ' . $credential->reference_number : null,
                                $credential->issuing_body,
                                $credential->issued_on ? 'Issued ' . $credential->issued_on->format('j M Y') : null,
                                $credential->expires_on
                                    ? ($expiry['key'] === 'expired' ? 'Expired ' : 'Expires ') . $credential->expires_on->format('j M Y')
                                    : null,
                            ]);
                        @endphp
                        <li class="p-4 {{ $row['superseded'] ? 'bg-gray-50/70' : '' }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md border border-blue-100 bg-blue-50 px-1.5 py-0.5 text-[11px] font-semibold text-blue-700">{{ $credential->typeShort() }}</span>
                                        <span class="font-semibold {{ $row['superseded'] ? 'text-gray-500' : 'text-gray-800' }}">{{ $credential->title }}</span>
                                    </div>
                                    @if($meta)
                                        <p class="mt-1 text-xs text-gray-500">{{ implode(' · ', $meta) }}</p>
                                    @endif
                                    <p class="mt-1 text-xs">
                                        @if($credential->isVerified())
                                            <span class="text-green-700">Verified by {{ $credential->verifiedBy->name ?? 'a former user' }} on {{ $credential->verified_at->format('j M Y') }}</span>
                                        @else
                                            <span class="text-amber-700">Not verified: original not sighted yet</span>
                                        @endif
                                    </p>
                                    @if($credential->notes)
                                        <p class="mt-1 text-xs text-gray-600 whitespace-pre-line">{{ $credential->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    @if($row['superseded'])
                                        <span class="{{ $chipBase }} {{ $chip['gray'] }}" title="A newer one is on file">Superseded</span>
                                    @else
                                        <span class="{{ $chipBase }} {{ $chip[$expiryTone[$expiry['key']]] }}">{{ $expiry['chip'] }}</span>
                                    @endif
                                    <button type="button" @click="toggle('{{ $formId }}')" class="{{ $linkButton }}"
                                        :aria-expanded="open === '{{ $formId }}'">Edit</button>
                                    <form method="POST" action="{{ route('nurses.credentials.destroy', [$nurse, $credential]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="active_tab" value="credentialing">
                                        <button type="button" class="{{ $removeButton }}"
                                            @click="discardChanges() && confirmDelete($event, @js('Remove "' . $credential->title . '" from this nurse\'s file? A renewal should be added as a new entry instead, so this one stays as history.'))">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div x-show="open === '{{ $formId }}'" x-cloak class="mt-4">
                                @include('admin.nurses.partials.credential-form', ['credential' => $credential, 'formId' => $formId])
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- ----------------------------------------- Clinical privileges --}}
        @include('admin.nurses.partials.privilege-checklist')
    </div>

    {{-- Suggestions for the forms above --}}
    @foreach($c['suggestions']['titles'] as $type => $titles)
        <datalist id="credential-titles-{{ $type }}">
            @foreach($titles as $title)
                <option value="{{ $title }}"></option>
            @endforeach
        </datalist>
    @endforeach
    @foreach(['credential-issuers' => 'issuers', 'privilege-names' => 'privileges', 'privilege-approvers' => 'approvers'] as $listId => $key)
        <datalist id="{{ $listId }}">
            @foreach($c['suggestions'][$key] as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
    @endforeach
</div>
