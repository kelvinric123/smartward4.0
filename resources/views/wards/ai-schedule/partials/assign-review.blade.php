{{--
    Review summary for a shift's bed assignment: each nurse's beds, load and
    balance, and what changes from the assignment saved now. Beds can be moved
    between nurses here before anything is applied. Reads the bedAssignment
    state, so it has to sit inside that component.
--}}

<div x-show="reviewOpen" x-cloak @keydown.escape.window="reviewOpen = false"
    class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 px-4 py-6 sm:px-8"
    role="dialog" aria-modal="true" aria-labelledby="assignment-review-title">
    <div @click.outside="reviewOpen = false" class="mx-auto w-full max-w-6xl rounded-2xl bg-white shadow-2xl">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4">
            <div>
                <h3 id="assignment-review-title" class="flex items-center gap-2 text-lg font-bold text-gray-800">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                    {{ $suggesting ? 'Review the AI suggestion' : 'Review the assignment' }}
                </h3>
                <p class="mt-0.5 text-sm text-gray-500">
                    {{ $shift }} &middot; {{ \Illuminate\Support\Carbon::parse($day)->format('l j M') }} &middot; {{ $shifts[$shift]['time'] }}.
                    Check each nurse's beds, move any bed to someone else, then apply. Nothing is saved until you do.
                </p>
            </div>
            <button type="button" @click="reviewOpen = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                <span class="sr-only">Close</span>
            </button>
        </div>

        {{-- At a glance --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 bg-gray-50 px-6 py-3 text-xs">
            <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-700 ring-1 ring-gray-200" x-text="nurses.length + (nurses.length === 1 ? ' nurse' : ' nurses')"></span>
            <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-700 ring-1 ring-gray-200" x-text="beds.filter(b => b.patient).length + ' patients in ' + beds.length + ' beds'"></span>
            <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-700 ring-1 ring-gray-200"
                x-text="'Loads ' + format(spread.min) + ' to ' + format(spread.max)"></span>
            <span class="rounded-full px-3 py-1 font-semibold"
                :class="changes.length ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                x-text="changes.length ? changes.length + (changes.length === 1 ? ' bed changes' : ' beds change') + ' from what is saved now' : 'Same as what is saved now'"></span>
            <span x-show="unassigned.length" class="rounded-full bg-red-100 px-3 py-1 font-semibold text-red-800"
                x-text="unassigned.length + (unassigned.length === 1 ? ' bed' : ' beds') + ' without a nurse'"></span>
        </div>

        {{-- Each nurse's beds --}}
        <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="load in loads" :key="load.id">
                <div class="flex flex-col rounded-xl border bg-white shadow-sm" :class="load.rostered ? 'border-gray-200' : 'border-amber-300'">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-gray-800" x-text="load.name"></div>
                                <div class="text-xs text-gray-500"
                                    x-text="load.beds.length + (load.beds.length === 1 ? ' bed' : ' beds') + ' · ' + load.patients + (load.patients === 1 ? ' patient' : ' patients')"></div>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="load.rostered ? levelBadge(load.level) : 'bg-amber-100 text-amber-800'"
                                x-text="load.rostered ? levelLabel(load.level) : 'Not rostered'"></span>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-2 flex-1 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full transition-all" :class="levelBar(load.level)"
                                    :style="'width: ' + Math.min(100, Math.round(load.score / maxScore * 100)) + '%'"></div>
                            </div>
                            <span class="w-10 text-right text-sm font-bold text-gray-800" x-text="format(load.score)"></span>
                        </div>
                    </div>

                    <ul class="flex-1 divide-y divide-gray-50">
                        <template x-for="bed in load.beds" :key="bed.id">
                            <li class="flex items-center gap-2 px-4 py-2" :class="isChanged(bed.id) ? 'bg-indigo-50/60' : ''">
                                <span class="w-10 shrink-0 text-sm font-semibold text-gray-800" x-text="bed.label"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm" :class="bed.patient ? 'text-gray-800' : 'text-gray-400'" x-text="bed.patient || 'Empty'"></div>
                                    <div x-show="isChanged(bed.id) && saved[bed.id]" class="text-[11px] text-indigo-700" x-text="'Was: ' + savedName(bed.id)"></div>
                                </div>
                                <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[11px] font-bold" :class="scoreChip(bed.score)"
                                    :title="bed.factors.join(', ')" x-text="format(bed.score)"></span>
                                @if ($canEdit)
                                    <select @change="move(bed.id, $event.target.value)" :aria-label="'Move bed ' + bed.label"
                                        class="w-28 shrink-0 rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <template x-for="nurse in nurses" :key="nurse.id">
                                            <option :value="String(nurse.id)" :selected="mapping[bed.id] === String(nurse.id)" x-text="nurse.name"></option>
                                        </template>
                                        <option value="" :selected="!mapping[bed.id]">Unassigned</option>
                                    </select>
                                @endif
                            </li>
                        </template>
                        <li x-show="!load.beds.length" class="px-4 py-4 text-center text-xs text-gray-400">No beds</li>
                    </ul>
                </div>
            </template>

            {{-- Beds nobody has --}}
            <div x-show="unassigned.length" class="flex flex-col rounded-xl border border-red-200 bg-red-50/40 shadow-sm">
                <div class="border-b border-red-100 px-4 py-3">
                    <div class="font-semibold text-red-800">Without a nurse</div>
                    <div class="text-xs text-red-700">These beds would have no nurse on the ward dashboard.</div>
                </div>
                <ul class="divide-y divide-red-100">
                    <template x-for="bed in unassigned" :key="bed.id">
                        <li class="flex items-center gap-2 px-4 py-2">
                            <span class="w-10 shrink-0 text-sm font-semibold text-gray-800" x-text="bed.label"></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm" :class="bed.patient ? 'text-gray-800' : 'text-gray-400'" x-text="bed.patient || 'Empty'"></div>
                                <div x-show="saved[bed.id]" class="text-[11px] text-indigo-700" x-text="'Was: ' + savedName(bed.id)"></div>
                            </div>
                            @if ($canEdit)
                                <select @change="move(bed.id, $event.target.value)" :aria-label="'Give bed ' + bed.label + ' to'"
                                    class="w-28 shrink-0 rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="" selected>Give to...</option>
                                    <template x-for="nurse in nurses" :key="nurse.id">
                                        <option :value="String(nurse.id)" x-text="nurse.name"></option>
                                    </template>
                                </select>
                            @endif
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        {{-- What changes --}}
        <div x-show="changes.length" class="mx-6 mb-4 rounded-xl border border-gray-200">
            <button type="button" @click="showChanges = !showChanges"
                class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <span x-text="'What changes from what is saved now (' + changes.length + ')'"></span>
                <svg class="w-4 h-4 transition-transform" :class="showChanges ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <ul x-show="showChanges" class="max-h-56 divide-y divide-gray-100 overflow-y-auto border-t border-gray-100 text-sm">
                <template x-for="change in changes" :key="change.bed.id">
                    <li class="flex flex-wrap items-center gap-x-2 px-4 py-1.5">
                        <span class="w-10 font-semibold text-gray-800" x-text="change.bed.label"></span>
                        <span class="text-gray-500" x-text="change.bed.patient || 'Empty'"></span>
                        <span class="ml-auto text-gray-500" x-text="change.from"></span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        <span class="font-semibold text-indigo-700" x-text="change.to"></span>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Decide --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-6 py-4">
            <div class="flex flex-wrap items-center gap-2">
                @if ($suggesting && $canEdit)
                    <button type="button" x-show="editedSinceSuggestion" @click="backToSuggestion()"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-gray-50">
                        Back to the AI suggestion
                    </button>
                @endif
                <span x-show="editedSinceSuggestion && {{ $suggesting ? 'true' : 'false' }}" class="text-xs text-gray-500">You have changed the AI suggestion.</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="reviewOpen = false"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-gray-50">
                    {{ $canEdit ? 'Keep editing in the table' : 'Close' }}
                </button>
                @if ($canEdit)
                    <button type="submit" form="bed-assignment-form"
                        class="inline-flex items-center px-5 py-2 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 text-white text-sm font-semibold rounded-lg shadow">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Apply to ward dashboard
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
