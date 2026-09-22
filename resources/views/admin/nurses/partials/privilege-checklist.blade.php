{{--
    Clinical privileges on the Credentialing and Privileging tab: the built-in
    list (App\Support\NursePrivilegeCatalogue) by field, with this nurse's
    record of each, then any privileges of the hospital's own. "Edit
    privileges" turns the list into tick boxes, saved together by the form at
    the bottom (the rows' inputs join it through their form attribute, as
    each record's own Edit and Remove forms sit in the rows too). Expects
    $nurse and $c (NurseCredentialing::forNurse), plus the tab's chip, button
    and field classes.
--}}
@php
    $privilegeModel = \App\Models\NursePrivilege::class;
    $failedChecklist = old('_form') === 'privilege-checklist';

    // Tick box state per privilege: as saved, or as ticked before a failed save
    $checklistItems = [];
    $checklistMeta = [];
    $checklistSections = [];
    foreach ($c['checklist'] as $sectionKey => $section) {
        $codes = [];
        $sectionChanged = false;
        foreach ($section['items'] as $item) {
            $code = $item['code'];
            $codes[] = $code;
            $was = $item['held'] ? $item['row']['privilege']->status : '';
            $held = $failedChecklist ? (bool) old("checklist.items.{$code}.held") : $item['held'];
            $status = $failedChecklist ? old("checklist.items.{$code}.status") : $was;
            $status = in_array($status, $privilegeModel::ACTIVE_STATUSES, true) ? $status : 'granted';

            $checklistItems[$code] = ['held' => $held, 'status' => $status];
            $checklistMeta[$code] = [
                'name' => $item['name'],
                'search' => Str::lower($item['name'] . ' ' . $section['label']),
                'level' => $item['level'],
                'was' => $was,
                'recorded' => $item['row'] !== null,
                'certificate' => $item['requires'] ? $c['certificates'][$item['requires']]['key'] : null,
            ];
            $sectionChanged = $sectionChanged || ($held ? $status : '') !== $was;
        }
        $checklistSections[$sectionKey] = [
            'open' => $section['open'] || ($failedChecklist && $sectionChanged),
            'codes' => $codes,
        ];
    }

    $checklistOld = fn (string $key, $default = null) => $failedChecklist ? old("checklist.{$key}", $default) : $default;
    $checklistError = fn (string $key) => $failedChecklist ? $errors->first("checklist.{$key}") : null;
    $checklistConfig = [
        'editing' => $failedChecklist,
        'items' => $checklistItems,
        'meta' => $checklistMeta,
        'sections' => $checklistSections,
        'review' => $checklistOld('review', 'level'),
    ];

    $levelChip = [
        'core' => 'bg-gray-100 text-gray-600 ring-gray-200',
        'advanced' => 'bg-violet-50 text-violet-700 ring-violet-200',
    ];
    $certificateTone = ['valid' => 'green', 'expiring' => 'amber', 'expired' => 'red'];
    $stateBox = [
        'granted' => 'bg-green-600 border-green-600 text-white',
        'supervised' => 'bg-blue-500 border-blue-500 text-white',
        'suspended' => 'border-red-400 bg-red-50 text-red-500',
    ];
    $reviewYears = $privilegeModel::REVIEW_YEARS;
@endphp

<script>
    window.privilegeChecklist = function (config) {
        return {
            editing: config.editing,
            items: config.items,
            meta: config.meta,
            sections: config.sections,
            review: config.review,
            query: '',
            recordedOnly: false,

            startEditing() {
                this.editing = true;
            },
            // Back to how everything is saved
            cancel() {
                for (const code in this.meta) {
                    const was = this.meta[code].was;
                    this.items[code] = { held: was !== '', status: was || 'granted' };
                }
                this.editing = false;
            },
            searching() {
                return this.query.trim() !== '';
            },
            isOpen(section) {
                return this.searching() || this.sections[section].open;
            },
            toggleSection(section) {
                this.sections[section].open = !this.sections[section].open;
            },
            visible(code) {
                const meta = this.meta[code];
                if (this.recordedOnly && !meta.recorded && !this.items[code].held) return false;
                return !this.searching() || meta.search.includes(this.query.trim().toLowerCase());
            },
            sectionVisible(section) {
                return this.sections[section].codes.some(code => this.visible(code));
            },
            heldIn(section) {
                return this.sections[section].codes.filter(code => this.items[code].held).length;
            },
            tickAllCore(section) {
                this.sections[section].codes.forEach(code => {
                    if (this.meta[code].level === 'core' && !this.items[code].held) {
                        this.items[code] = { held: true, status: 'granted' };
                    }
                });
                this.sections[section].open = true;
            },
            state(code) {
                return this.items[code].held ? this.items[code].status : '';
            },
            changed(code) {
                return this.state(code) !== this.meta[code].was;
            },
            get changes() {
                const changes = { granted: 0, changed: 0, withdrawn: [], unbacked: 0 };
                for (const code in this.meta) {
                    if (!this.changed(code)) continue;
                    const meta = this.meta[code];
                    if (meta.was === '') {
                        changes.granted++;
                        if (['missing', 'expired'].includes(meta.certificate)) changes.unbacked++;
                    } else if (!this.items[code].held) {
                        changes.withdrawn.push(meta.name);
                    } else {
                        changes.changed++;
                    }
                }
                changes.total = changes.granted + changes.changed + changes.withdrawn.length;
                return changes;
            },
            summary() {
                const changes = this.changes;
                const parts = [];
                if (changes.granted) parts.push(changes.granted + ' to grant');
                if (changes.changed) parts.push(changes.changed + ' to change');
                if (changes.withdrawn.length) parts.push(changes.withdrawn.length + ' to withdraw');
                return parts.length ? parts.join(', ') : 'Tick or untick privileges above';
            },
        };
    };
</script>

<section class="space-y-4" x-data="privilegeChecklist(@js($checklistConfig))">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex-1 min-w-[14rem]">
            <h3 class="text-base font-semibold text-gray-800">Clinical privileges</h3>
            <p class="text-xs text-gray-500 mt-0.5">What this nurse is cleared to perform, from the built-in list by field.
                <span class="font-medium text-gray-600">Core</span>: any registered nurse once assessed as competent.
                <span class="font-medium text-violet-700">Advanced</span>: extra training and a competency sign-off.
                Unticking withdraws a privilege but keeps the record.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2" x-show="!editing">
            <button type="button" @click="toggle('privilege-new')" :aria-expanded="open === 'privilege-new'"
                class="inline-flex items-center px-4 py-2 bg-white border border-blue-200 rounded-lg font-semibold text-sm text-blue-700 shadow-sm hover:bg-blue-50">
                Add other privilege
            </button>
            <button type="button" @click="startEditing(); open = null" class="{{ $addButton }}">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                Edit privileges
            </button>
        </div>
    </div>

    <div x-show="open === 'privilege-new' && !editing" x-cloak>
        <p class="mb-2 text-xs text-gray-500">For a privilege that is not on the list below. A name picked from the list is linked to it instead.</p>
        @include('admin.nurses.partials.privilege-form', ['privilege' => null, 'formId' => 'privilege-new'])
    </div>

    @if(!$n['privileges'])
        <div x-show="!editing" class="rounded-xl border border-dashed border-gray-300 px-6 py-4 text-center text-sm text-gray-500">
            No privileges recorded yet. Press <span class="font-semibold text-gray-700">Edit privileges</span> and tick what this nurse may perform.
        </div>
    @endif

    <div x-show="editing" x-cloak class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Tick what this nurse may perform and choose <span class="font-semibold">Independent</span> or
        <span class="font-semibold">Supervised</span>, then save at the bottom. A privilege whose certificate is missing
        can still be ticked; it shows as at risk until the certificate is added under Credentials.
    </div>

    {{-- Find --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <input type="search" x-model="query" placeholder="Find a privilege" aria-label="Find a privilege"
            class="w-full sm:w-72 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" x-model="recordedOnly" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            Only this nurse's privileges
        </label>
    </div>

    {{-- The built-in list, by field --}}
    <div class="space-y-3">
        @foreach($c['checklist'] as $sectionKey => $section)
            @php
                $sectionCertificate = $section['certificate'];
                $total = count($section['items']);
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white" x-show="sectionVisible('{{ $sectionKey }}')">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                    <button type="button" @click="toggleSection('{{ $sectionKey }}')" :aria-expanded="isOpen('{{ $sectionKey }}')"
                        class="flex min-w-0 flex-1 items-center gap-2 text-left">
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="isOpen('{{ $sectionKey }}') ? 'rotate-90' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="font-semibold text-gray-800">{{ $section['label'] }}</span>
                        <span class="text-xs text-gray-500" x-text="heldIn('{{ $sectionKey }}') + ' of {{ $total }} held'">{{ $section['held'] }} of {{ $total }} held</span>
                    </button>
                    @if($sectionCertificate)
                        @if($sectionCertificate['key'] === 'missing')
                            <span class="{{ $chipBase }}" title="{{ $sectionCertificate['title'] }}"
                                :class="heldIn('{{ $sectionKey }}') ? '{{ $chip['amber'] }}' : '{{ $chip['gray'] }}'">{{ $sectionCertificate['chip'] }}</span>
                        @else
                            <span class="{{ $chipBase }} {{ $chip[$certificateTone[$sectionCertificate['key']]] }}" title="{{ $sectionCertificate['title'] }}">{{ $sectionCertificate['chip'] }}</span>
                        @endif
                    @endif
                    <button type="button" x-show="editing" x-cloak @click="tickAllCore('{{ $sectionKey }}')"
                        class="rounded-md border border-blue-200 bg-white px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Tick all core
                    </button>
                </div>

                <ul x-show="isOpen('{{ $sectionKey }}')" @if(!$section['open'] && !$failedChecklist) x-cloak @endif class="divide-y divide-gray-100 border-t border-gray-100">
                    @foreach($section['items'] as $item)
                        @php
                            $code = $item['code'];
                            $row = $item['row'];
                            $privilege = $row['privilege'] ?? null;
                            $formId = $privilege ? 'privilege-' . $privilege->id : null;
                            $certificate = $item['certificate'];
                            $inputName = "checklist[items][{$code}]";
                        @endphp
                        <li x-show="visible('{{ $code }}')" class="px-4 py-2.5 transition-colors"
                            :class="editing && changed('{{ $code }}') ? 'bg-indigo-50/70' : ''">
                            <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
                                <div class="pt-0.5">
                                    <input type="checkbox" id="pc-{{ $code }}" name="{{ $inputName }}[held]" value="1" form="privilege-checklist"
                                        x-model="items['{{ $code }}'].held" x-show="editing" x-cloak
                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span x-show="!editing" aria-hidden="true"
                                        class="flex h-4 w-4 items-center justify-center rounded border {{ $item['held'] || ($privilege && $privilege->status === 'suspended') ? $stateBox[$privilege->status] : 'border-gray-300 bg-white' }}">
                                        @if($item['held'])
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        @elseif($privilege && $privilege->status === 'suspended')
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="3" d="M6 12h12" /></svg>
                                        @endif
                                    </span>
                                    <input type="hidden" name="{{ $inputName }}[status]" form="privilege-checklist" :value="items['{{ $code }}'].status">
                                    <input type="hidden" name="{{ $inputName }}[was]" form="privilege-checklist" value="{{ $item['held'] ? $privilege->status : '' }}">
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <label :for="editing ? 'pc-{{ $code }}' : null"
                                            class="text-sm font-medium {{ $item['held'] ? 'text-gray-800' : 'text-gray-600' }}"
                                            :class="editing ? 'cursor-pointer' : ''">{{ $item['name'] }}</label>
                                        <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $levelChip[$item['level']] }}">{{ $privilegeModel::CATEGORIES[$item['level']]['label'] }}</span>
                                        @if($item['hint'])
                                            <span class="text-[11px] text-gray-400">{{ $item['hint'] }}</span>
                                        @endif
                                    </div>
                                    @if($row)
                                        <div x-show="!editing">
                                            @include('admin.nurses.partials.privilege-details', ['row' => $row])
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if($certificate)
                                        @if($certificate['key'] === 'missing')
                                            <span class="{{ $chipBase }}" title="{{ $certificate['title'] }}"
                                                :class="items['{{ $code }}'].held ? '{{ $chip['amber'] }}' : '{{ $chip['gray'] }}'">{{ $certificate['chip'] }}</span>
                                        @else
                                            <span class="{{ $chipBase }} {{ $chip[$certificateTone[$certificate['key']]] }}" title="{{ $certificate['title'] }}">{{ $certificate['chip'] }}</span>
                                        @endif
                                    @endif

                                    {{-- As saved --}}
                                    @if($row)
                                        <div x-show="!editing" class="flex flex-wrap items-center gap-2">
                                            @if($row['at_risk'])
                                                <span class="{{ $chipBase }} {{ $chip[$row['certificate']['key'] === 'expired' ? 'red' : 'amber'] }}"
                                                    title="Held without a valid certificate">At risk</span>
                                            @endif
                                            <span class="{{ $chipBase }} {{ $chip[$statusTone[$privilege->status] ?? 'gray'] }}"
                                                title="{{ $privilegeModel::STATUSES[$privilege->status]['hint'] ?? '' }}">{{ $privilege->statusLabel() }}</span>
                                            @if($row['review'] && isset($reviewTone[$row['review']['key']]))
                                                <span class="{{ $chipBase }} {{ $chip[$reviewTone[$row['review']['key']]] }}">{{ $row['review']['chip'] }}</span>
                                            @endif
                                            @include('admin.nurses.partials.privilege-actions')
                                        </div>
                                    @endif

                                    {{-- While ticking --}}
                                    <span x-show="editing && items['{{ $code }}'].held" x-cloak role="group" aria-label="How {{ $item['name'] }} is held"
                                        class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs font-semibold">
                                        <button type="button" @click="items['{{ $code }}'].status = 'granted'"
                                            :aria-pressed="items['{{ $code }}'].status === 'granted'"
                                            :class="items['{{ $code }}'].status === 'granted' ? 'bg-green-50 text-green-700' : 'bg-white text-gray-500 hover:bg-gray-50'"
                                            class="px-2.5 py-1" title="{{ $privilegeModel::STATUSES['granted']['hint'] }}">Independent</button>
                                        <button type="button" @click="items['{{ $code }}'].status = 'supervised'"
                                            :aria-pressed="items['{{ $code }}'].status === 'supervised'"
                                            :class="items['{{ $code }}'].status === 'supervised' ? 'bg-blue-50 text-blue-700' : 'bg-white text-gray-500 hover:bg-gray-50'"
                                            class="border-l border-gray-300 px-2.5 py-1" title="{{ $privilegeModel::STATUSES['supervised']['hint'] }}">Supervised</button>
                                    </span>
                                    @if($privilege && !$item['held'])
                                        <span x-show="editing" x-cloak class="{{ $chipBase }} {{ $chip[$statusTone[$privilege->status] ?? 'gray'] }}"
                                            title="Ticking it grants it again">{{ $privilege->statusLabel() }}</span>
                                    @endif
                                </div>
                            </div>

                            @if($privilege)
                                <div x-show="open === '{{ $formId }}' && !editing" x-cloak class="mt-4">
                                    @include('admin.nurses.partials.privilege-form', ['privilege' => $privilege, 'formId' => $formId])
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- The hospital's own --}}
    @if($c['other']->isNotEmpty())
        <div>
            <div class="mb-2 flex flex-wrap items-baseline gap-x-2">
                <h4 class="text-sm font-semibold text-gray-700">Other privileges</h4>
                <span class="text-xs text-gray-400">Added by hand, not on the built-in list</span>
            </div>
            <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                @foreach($c['other'] as $row)
                    @php
                        $privilege = $row['privilege'];
                        $formId = 'privilege-' . $privilege->id;
                    @endphp
                    <li class="p-4 {{ $privilege->isActive() ? '' : 'bg-gray-50/70' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold {{ $privilege->isActive() ? 'text-gray-800' : 'text-gray-500' }}">{{ $privilege->name }}</p>
                                    @isset($levelChip[$privilege->category])
                                        <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $levelChip[$privilege->category] }}">{{ $privilegeModel::CATEGORIES[$privilege->category]['label'] }}</span>
                                    @endisset
                                </div>
                                @include('admin.nurses.partials.privilege-details', ['row' => $row])
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="{{ $chipBase }} {{ $chip[$statusTone[$privilege->status] ?? 'gray'] }}"
                                    title="{{ $privilegeModel::STATUSES[$privilege->status]['hint'] ?? '' }}">{{ $privilege->statusLabel() }}</span>
                                @if($row['review'] && isset($reviewTone[$row['review']['key']]))
                                    <span class="{{ $chipBase }} {{ $chip[$reviewTone[$row['review']['key']]] }}">{{ $row['review']['chip'] }}</span>
                                @endif
                                @include('admin.nurses.partials.privilege-actions')
                            </div>
                        </div>
                        <div x-show="open === '{{ $formId }}'" x-cloak class="mt-4">
                            @include('admin.nurses.partials.privilege-form', ['privilege' => $privilege, 'formId' => $formId])
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Save the ticks: kept in view at the bottom while ticking --}}
    @php
        $compact = 'mt-1 block rounded-lg border-gray-300 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';
        $compactLabel = 'block text-xs font-medium text-gray-600';
    @endphp
    <form id="privilege-checklist" method="POST" action="{{ route('nurses.privileges.checklist', $nurse) }}"
        x-show="editing" x-cloak @submit="discardChanges() || $event.preventDefault()"
        class="sticky bottom-0 z-10 space-y-2 rounded-xl border border-blue-200 bg-white px-4 py-3 shadow-lg">
        @csrf
        <input type="hidden" name="active_tab" value="credentialing">
        <input type="hidden" name="_form" value="privilege-checklist">

        <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
            <div class="min-w-[12rem] flex-1 self-center">
                <p class="text-sm font-semibold text-gray-800" x-text="summary()"></p>
                <p x-show="changes.unbacked" class="text-xs text-amber-700"
                    x-text="changes.unbacked + (changes.unbacked === 1 ? ' is' : ' are') + ' ticked without the certificate it needs, and will show as at risk.'"></p>
            </div>
            <div>
                <label for="checklist-granted-on" class="{{ $compactLabel }}">Date granted</label>
                <input type="date" id="checklist-granted-on" name="checklist[granted_on]" required max="{{ $c['today']->format('Y-m-d') }}"
                    value="{{ $checklistOld('granted_on', $c['today']->format('Y-m-d')) }}" class="{{ $compact }}">
            </div>
            <div>
                <label for="checklist-review" class="{{ $compactLabel }}" title="For the privileges granted now. Ones already held keep their dates.">Review</label>
                <div class="flex flex-wrap gap-2">
                    <select id="checklist-review" name="checklist[review]" x-model="review" class="{{ $compact }}">
                        <option value="level">Core in {{ $reviewYears['core'] }} {{ Str::plural('year', $reviewYears['core']) }}, Advanced in {{ $reviewYears['advanced'] }} {{ Str::plural('year', $reviewYears['advanced']) }}</option>
                        <option value="date">On a date</option>
                        <option value="none">No review date</option>
                    </select>
                    <input type="date" name="checklist[review_on]" aria-label="Review date" x-show="review === 'date'" :required="review === 'date'"
                        value="{{ $checklistOld('review_on') }}" class="{{ $compact }}">
                </div>
            </div>
            <div class="w-full sm:w-80">
                <label for="checklist-approved-by" class="{{ $compactLabel }}">Approved by</label>
                <input type="text" id="checklist-approved-by" name="checklist[approved_by]" list="privilege-approvers" maxlength="150" autocomplete="off"
                    value="{{ $checklistOld('approved_by', $privilegeModel::DEFAULT_APPROVER) }}" class="{{ $compact }} w-full">
            </div>
            <div class="flex gap-2">
                <button type="button" @click="cancel()"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-50 shadow-sm transition">
                    Cancel
                </button>
                <button type="submit" class="{{ $addButton }}">Save privileges</button>
            </div>
        </div>

        @foreach(['granted_on', 'review_on', 'approved_by'] as $key)
            @if($msg = $checklistError($key))
                <p class="text-sm text-red-600">{{ $msg }}</p>
            @endif
        @endforeach

        <div x-show="changes.withdrawn.length" x-cloak>
            <label for="checklist-reason" class="{{ $compactLabel }}">
                Reason for withdrawing <span class="font-normal text-gray-500" x-text="changes.withdrawn.join(', ')"></span>
                <span class="text-red-500">*</span>
            </label>
            <textarea id="checklist-reason" name="checklist[reason]" rows="2" maxlength="1000" :required="changes.withdrawn.length > 0"
                placeholder="Why the nurse may no longer perform it" class="{{ $compact }} w-full">{{ $checklistOld('reason') }}</textarea>
        </div>
        @if($msg = $checklistError('reason'))
            <p class="text-sm text-red-600">{{ $msg }}</p>
        @endif
    </form>
</section>
