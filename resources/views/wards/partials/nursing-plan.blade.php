{{--
    Patient Details -> Nursing Plan tab.

    Left: what is due this shift, gathered from what is already recorded
    (ShiftTasks) - doses, open orders, assessments, HGT, fluid targets, blood
    units, infusions, trips out, care plan evaluations.
    Right: the nursing care plan (NursingCarePlan) - diagnoses with goals and
    interventions, evaluated each shift, added from the library or written by
    hand. The nurse app shows and edits the same plan.
--}}
@if (($patientTabs['nursing_plan'] ?? false) && $patient)
    @php
        // Loaded by the tab button for its badge (patient-details)
        $carePlan = $nursingPlanTab['care_plan'] ?? \App\Services\NursingPlan\NursingCarePlan::forPatient($patient);
        $shiftPlan = $nursingPlanTab['shift'] ?? \App\Services\NursingPlan\ShiftTasks::forPatient($patient);
        $npErrors = $errors->getBag('nursingPlan');
        $npTemplates = collect($carePlan['templates'])->groupBy('category_label');
        $npTone = [
            'critical' => 'border-red-200 bg-red-50 text-red-800',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
            'good' => 'border-green-200 bg-green-50 text-green-800',
            'muted' => 'border-gray-200 bg-gray-50 text-gray-500',
            'default' => 'border-gray-200 bg-white text-gray-800',
        ];
        $npDot = [
            'medication' => 'bg-violet-500', 'order' => 'bg-amber-500', 'assessment' => 'bg-fuchsia-500',
            'hgt' => 'bg-pink-500', 'fluid' => 'bg-cyan-500', 'transfusion' => 'bg-rose-500',
            'infusion' => 'bg-sky-500', 'movement' => 'bg-orange-500', 'discharge' => 'bg-green-500',
            'careplan' => 'bg-indigo-500', 'vitals' => 'bg-emerald-500',
        ];
        $npOutcomeTone = [
            'met' => 'bg-green-100 text-green-800',
            'partly_met' => 'bg-amber-100 text-amber-800',
            'not_met' => 'bg-red-100 text-red-800',
        ];
    @endphp

    <div x-show="activeTab === 'nursing_plan'" x-cloak>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-800">Nursing Plan</h3>
            <p class="text-xs text-gray-500">
                {{ $shiftPlan['shift']['name'] }} shift {{ $shiftPlan['shift']['time'] }}
                @if ($shiftPlan['shift']['nurse'])
                    &middot; {{ $shiftPlan['shift']['nurse'] }}
                @endif
            </p>
        </div>

        @if ($npErrors->any())
            <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $npErrors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            {{-- This shift --}}
            <section class="lg:col-span-2">
                <div class="rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">This shift</h4>
                        <span class="text-xs text-gray-500">
                            {{ $shiftPlan['counts']['total'] }} {{ Str::plural('task', $shiftPlan['counts']['total']) }}
                            @if ($shiftPlan['counts']['overdue'])
                                &middot; <span class="font-semibold text-red-700">{{ $shiftPlan['counts']['overdue'] }} overdue</span>
                            @endif
                        </span>
                    </div>

                    @if (empty($shiftPlan['timed']) && empty($shiftPlan['standing']))
                        <p class="px-3 py-4 text-sm text-gray-500">Nothing due this shift.</p>
                    @endif

                    @if (!empty($shiftPlan['timed']))
                        <ol class="divide-y divide-gray-100">
                            @foreach ($shiftPlan['timed'] as $task)
                                <li class="flex items-start gap-3 px-3 py-2">
                                    <span class="min-w-[3rem] shrink-0 whitespace-nowrap pt-0.5 font-mono text-xs {{ $task['overdue'] ? 'font-bold text-red-700' : 'text-gray-500' }}">{{ $task['time_label'] }}</span>
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $npDot[$task['category']] ?? 'bg-gray-400' }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium {{ $task['tone'] === 'critical' ? 'text-red-700' : ($task['tone'] === 'warning' ? 'text-amber-700' : 'text-gray-900') }}">
                                            {{ $task['title'] }}
                                            @if ($task['badge'])
                                                <span class="ml-1 rounded px-1.5 py-px text-[10px] font-bold {{ $task['overdue'] ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}">{{ $task['badge'] }}</span>
                                            @endif
                                        </span>
                                        @if ($task['detail'])
                                            <span class="block text-xs text-gray-500">{{ $task['detail'] }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (!empty($shiftPlan['standing']))
                        <p class="border-t border-gray-200 bg-gray-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500">During the shift</p>
                        <ul class="space-y-1.5 p-2">
                            @foreach ($shiftPlan['standing'] as $task)
                                <li class="rounded-md border px-2.5 py-1.5 {{ $npTone[$task['tone']] ?? $npTone['default'] }}">
                                    <span class="block text-sm font-medium">
                                        {{ $task['title'] }}
                                        @if ($task['badge'])
                                            <span class="ml-1 rounded bg-white/70 px-1.5 py-px text-[10px] font-bold">{{ $task['badge'] }}</span>
                                        @endif
                                    </span>
                                    @if ($task['detail'])
                                        <span class="block text-xs opacity-80">{{ $task['detail'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Nursing care plan --}}
            <section class="lg:col-span-3">
                <div class="rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">Nursing care plan</h4>
                        <span class="text-xs text-gray-500">
                            {{ count($carePlan['active']) }} active
                            @if ($carePlan['due_evaluations'])
                                &middot; <span class="font-semibold text-indigo-700">{{ $carePlan['due_evaluations'] }} to evaluate this shift</span>
                            @endif
                        </span>
                    </div>

                    @if (!empty($carePlan['suggestions']))
                        <div class="border-b border-gray-200 bg-indigo-50/60 px-3 py-2">
                            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-800">Suggested from the record</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($carePlan['suggestions'] as $suggestion)
                                    <form method="POST" action="{{ route('ward.nursing-plan.store', $patient) }}">
                                        @csrf
                                        <input type="hidden" name="template_key" value="{{ $suggestion['key'] }}">
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-white px-2.5 py-1 text-xs text-indigo-800 hover:bg-indigo-100"
                                            title="Add {{ $suggestion['diagnosis'] }} with its usual goal and interventions">
                                            <span class="font-bold">+</span>
                                            <span class="font-semibold">{{ $suggestion['diagnosis'] }}</span>
                                            <span class="text-indigo-600/80">&middot; {{ $suggestion['reason'] }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @forelse ($carePlan['active'] as $index => $item)
                        <div class="border-b border-gray-100 px-3 py-3" x-data="{ editing: false, closing: false }">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $index + 1 }}. {{ $item['diagnosis'] }}
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-px text-[10px] font-semibold text-gray-600">{{ $item['category_label'] }}</span>
                                    </p>
                                    @if ($item['related_to'])
                                        <p class="text-xs text-gray-500">Related to {{ $item['related_to'] }}</p>
                                    @endif
                                </div>
                                @if ($item['evaluated_this_shift'])
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800">Evaluated this shift</span>
                                @else
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-800">To evaluate</span>
                                @endif
                            </div>

                            <p class="mt-1.5 text-sm text-gray-800"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Goal</span> {{ $item['goal'] }}</p>
                            @if (!empty($item['interventions']))
                                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-gray-700">
                                    @foreach ($item['interventions'] as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($item['latest'])
                                <p class="mt-2 text-xs text-gray-600">
                                    <span class="rounded px-1.5 py-px text-[10px] font-bold {{ $npOutcomeTone[$item['latest']['outcome']] ?? 'bg-gray-100 text-gray-700' }}">{{ $item['latest']['outcome_label'] }}</span>
                                    {{ $item['latest']['shift_label'] ? $item['latest']['shift_label'] . ' · ' : '' }}{{ $item['latest']['time_label'] }}{{ $item['latest']['by'] ? ' · ' . $item['latest']['by'] : '' }}
                                    @if ($item['latest']['note'])
                                        &middot; {{ $item['latest']['note'] }}
                                    @endif
                                </p>
                            @endif

                            {{-- This shift's evaluation --}}
                            <form method="POST" action="{{ route('ward.nursing-plan.evaluate', $item['id']) }}" class="mt-2 flex flex-wrap items-center gap-1.5">
                                @csrf
                                <input type="text" name="note" maxlength="1000" placeholder="Note (needed if not met)"
                                    class="min-w-[12rem] flex-1 rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="submit" name="outcome" value="met" class="rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700">Met</button>
                                <button type="submit" name="outcome" value="partly_met" class="rounded-md bg-amber-500 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-600">Partly met</button>
                                <button type="submit" name="outcome" value="not_met" class="rounded-md bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700">Not met</button>
                                <button type="button" @click="editing = !editing; closing = false" class="ml-1 text-xs font-semibold text-gray-600 underline">Edit</button>
                                <button type="button" @click="closing = !closing; editing = false" class="text-xs font-semibold text-gray-600 underline">Close</button>
                            </form>

                            <form x-show="editing" x-cloak method="POST" action="{{ route('ward.nursing-plan.update', $item['id']) }}" class="mt-2 space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                                @csrf
                                <label class="block text-[11px] font-semibold text-gray-600">Related to
                                    <input type="text" name="related_to" value="{{ $item['related_to'] }}" maxlength="1000" class="mt-0.5 block w-full rounded-md border-gray-300 py-1 text-xs">
                                </label>
                                <label class="block text-[11px] font-semibold text-gray-600">Goal
                                    <input type="text" name="goal" value="{{ $item['goal'] }}" maxlength="1000" required class="mt-0.5 block w-full rounded-md border-gray-300 py-1 text-xs">
                                </label>
                                <label class="block text-[11px] font-semibold text-gray-600">Interventions (one per line)
                                    <textarea name="interventions_text" rows="4" class="mt-0.5 block w-full rounded-md border-gray-300 text-xs">{{ implode("\n", $item['interventions']) }}</textarea>
                                </label>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="editing = false" class="text-xs font-semibold text-gray-600">Cancel</button>
                                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700">Save</button>
                                </div>
                            </form>

                            <form x-show="closing" x-cloak method="POST" action="{{ route('ward.nursing-plan.close', $item['id']) }}" class="mt-2 flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 p-2">
                                @csrf
                                <input type="text" name="note" maxlength="255" placeholder="Note (needed to discontinue)"
                                    class="min-w-[12rem] flex-1 rounded-md border-gray-300 py-1 text-xs">
                                <button type="submit" name="status" value="resolved" class="rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700">Resolved</button>
                                <button type="submit" name="status" value="discontinued" class="rounded-md bg-gray-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-gray-700">Discontinue</button>
                            </form>
                        </div>
                    @empty
                        <p class="px-3 py-4 text-sm text-gray-500">No nursing diagnosis in the plan yet. Add one below{{ !empty($carePlan['suggestions']) ? ', or take a suggestion above' : '' }}.</p>
                    @endforelse

                    {{-- Add a diagnosis --}}
                    <div class="px-3 py-3" x-data="{
                        open: false,
                        templates: @js(collect($carePlan['templates'])->keyBy('key')),
                        key: '',
                        diagnosis: '', relatedTo: '', goal: '', interventions: '',
                        pick() {
                            const t = this.templates[this.key];
                            this.diagnosis = t ? t.diagnosis : '';
                            this.relatedTo = t ? t.related_to : '';
                            this.goal = t ? t.goal : '';
                            this.interventions = t ? t.interventions.join('\n') : '';
                        },
                    }">
                        <button type="button" @click="open = !open" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" x-text="open ? 'Cancel' : '+ Add a nursing diagnosis'">+ Add a nursing diagnosis</button>
                        <form x-show="open" x-cloak method="POST" action="{{ route('ward.nursing-plan.store', $patient) }}" class="mt-2 space-y-2 rounded-lg border border-indigo-200 bg-indigo-50/40 p-3">
                            @csrf
                            <label class="block text-[11px] font-semibold text-gray-600">Start from
                                <select name="template_key" x-model="key" @change="pick()" class="mt-0.5 block w-full rounded-md border-gray-300 py-1 text-sm">
                                    <option value="">My own words</option>
                                    @foreach ($npTemplates as $category => $templates)
                                        <optgroup label="{{ $category }}">
                                            @foreach ($templates as $template)
                                                <option value="{{ $template['key'] }}" @disabled($template['in_plan'])>
                                                    {{ $template['diagnosis'] }}{{ $template['in_plan'] ? ' (in the plan)' : '' }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-[11px] font-semibold text-gray-600">Nursing diagnosis
                                <input type="text" name="diagnosis" x-model="diagnosis" maxlength="255" class="mt-0.5 block w-full rounded-md border-gray-300 py-1 text-sm" placeholder="e.g. Impaired physical mobility">
                            </label>
                            <label class="block text-[11px] font-semibold text-gray-600">Related to
                                <input type="text" name="related_to" x-model="relatedTo" maxlength="1000" class="mt-0.5 block w-full rounded-md border-gray-300 py-1 text-sm">
                            </label>
                            <label class="block text-[11px] font-semibold text-gray-600">Goal
                                <input type="text" name="goal" x-model="goal" maxlength="1000" class="mt-0.5 block w-full rounded-md border-gray-300 py-1 text-sm">
                            </label>
                            <label class="block text-[11px] font-semibold text-gray-600">Interventions (one per line)
                                <textarea name="interventions_text" x-model="interventions" rows="5" class="mt-0.5 block w-full rounded-md border-gray-300 text-sm"></textarea>
                            </label>
                            <div class="flex justify-end">
                                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Add to plan</button>
                            </div>
                        </form>
                    </div>

                    @if (!empty($carePlan['closed']))
                        <details class="border-t border-gray-200 px-3 py-2">
                            <summary class="cursor-pointer text-xs font-semibold text-gray-600">Closed this stay ({{ count($carePlan['closed']) }})</summary>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($carePlan['closed'] as $item)
                                    <li class="text-xs text-gray-600">
                                        <span class="font-semibold text-gray-800">{{ $item['diagnosis'] }}</span>
                                        &middot; {{ $item['status_label'] }} {{ $item['resolved_label'] }}{{ $item['resolved_by'] ? ' by ' . $item['resolved_by'] : '' }}
                                        @if ($item['resolve_note'])
                                            &middot; {{ $item['resolve_note'] }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endif
