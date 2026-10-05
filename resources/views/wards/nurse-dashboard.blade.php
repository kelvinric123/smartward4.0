<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0b2435">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $nurse->name }} - Nurse Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-style />
    <style>
        :root {
            color-scheme: light;
            --app-bg: #eef4f9;
            --app-surface: #f7fbff;
            --app-surface-2: #ffffff;
            --app-ink: #0f172a;
            --app-muted: #64748b;
            --app-line: rgba(15, 23, 42, 0.08);
            --app-accent: #0f766e;
            --app-accent-2: #155e75;
            --app-alert: #dc2626;
            --app-warn: #d97706;
            --app-header: linear-gradient(180deg, #0b2435 0%, #0e7490 100%);
            --header-h: 168px;
            --tabbar-h: 68px;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            overscroll-behavior: none;
        }

        body {
            background: var(--app-header);
            min-height: 100vh;
            min-height: 100dvh;
        }

        .app-viewport {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            background: var(--app-surface);
        }

        .app-header {
            flex: 0 0 auto;
            background: var(--app-header);
            color: #fff;
            padding-top: max(0.75rem, env(safe-area-inset-top));
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            padding-bottom: 1.25rem;
            box-shadow: 0 14px 40px -28px rgba(0, 0, 0, 0.55);
            z-index: 20;
        }

        .app-body {
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            background: var(--app-surface);
            border-top-left-radius: 26px;
            border-top-right-radius: 26px;
            margin-top: -22px;
            padding-top: 22px;
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            padding-bottom: calc(var(--tabbar-h) + max(1rem, env(safe-area-inset-bottom)) + 1rem);
        }

        .app-tabbar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 30;
            padding-bottom: env(safe-area-inset-bottom);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: saturate(160%) blur(16px);
            -webkit-backdrop-filter: saturate(160%) blur(16px);
            border-top: 1px solid var(--app-line);
        }

        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(207, 250, 254, 0.9);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #34d399;
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.2);
        }
    </style>
</head>
<body class="font-sans antialiased text-slate-900">
    <div class="app-viewport" x-data="{ showWardSwitcher: false }">
        <header class="app-header">
            <div class="flex items-center justify-between text-[12px]">
                <div class="status-pill">
                    <span class="status-dot"></span>
                    Live
                </div>
                <p class="font-semibold tracking-wide text-cyan-50/90">
                    {{ now()->format('D, d M') }} &middot; <span id="app-clock">{{ now()->format('H:i') }}</span>
                </p>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3">
                <a href="{{ route('nurses.index') }}"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-white/10 backdrop-blur">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div class="min-w-0 text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.28em] text-cyan-100">Nurse</p>
                    <h1 class="mt-0.5 truncate text-base font-bold">{{ $nurse->name }}</h1>
                </div>
                <button type="button" @click="showWardSwitcher = !showWardSwitcher"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/15 bg-white/10 backdrop-blur">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 flex items-end justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.28em] text-cyan-100">Current Ward</p>
                    <h2 class="mt-1 truncate text-2xl font-bold leading-tight">{{ $selectedWard?->ward_name ?? 'No ward selected' }}</h2>
                    <p class="mt-1 text-xs text-cyan-50/85">
                        @if($currentShift)
                            {{ $currentShift->shift_name ?? $currentShift->shift_code }} shift
                        @else
                            Shift assignment unavailable
                        @endif
                    </p>
                </div>
                <div class="shrink-0 rounded-2xl bg-white/12 px-3 py-2 text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-100">Patients</p>
                    <p class="mt-0.5 text-xl font-bold leading-none">{{ $summary['occupied_beds'] }}</p>
                </div>
            </div>

            <div x-show="showWardSwitcher" x-cloak x-transition.opacity
                class="mt-4 rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-xl">
                <form method="GET" action="{{ route('nurses.dashboard', $nurse) }}">
                    <label for="ward_id" class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-100">Switch Ward</label>
                    <div class="flex gap-2">
                        <select id="ward_id" name="ward_id"
                            class="w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 text-sm font-medium text-white focus:border-cyan-200 focus:outline-none focus:ring-2 focus:ring-cyan-200">
                            @foreach($wards as $ward)
                                <option value="{{ $ward->id }}" @selected(optional($selectedWard)->id === $ward->id) class="text-slate-900">
                                    {{ $ward->ward_name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit"
                            class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-cyan-900 transition hover:bg-cyan-50">
                            Open
                        </button>
                    </div>
                </form>
            </div>
        </header>

        <main class="app-body">
            <section class="hide-scrollbar -mx-1 flex gap-3 overflow-x-auto px-1 pb-2">
                <div class="min-w-[140px] rounded-2xl bg-white p-3 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.6)] ring-1 ring-black/5">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Assigned</p>
                    <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ $summary['assigned_beds'] }}</p>
                    <p class="text-[11px] text-slate-500">For this nurse</p>
                </div>
                <div class="min-w-[140px] rounded-2xl bg-rose-50 p-3 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.6)] ring-1 ring-rose-100">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-rose-600">Critical</p>
                    <p class="mt-1.5 text-2xl font-bold text-rose-700">{{ $summary['critical_patients'] }}</p>
                    <p class="text-[11px] text-rose-600">Needs attention</p>
                </div>
                <div class="min-w-[140px] rounded-2xl bg-amber-50 p-3 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.6)] ring-1 ring-amber-100">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-amber-700">Infusions</p>
                    <p class="mt-1.5 text-2xl font-bold text-amber-700">{{ $summary['active_infusions'] }}</p>
                    <p class="text-[11px] text-amber-700">{{ $summary['infusion_alerts'] }} alerts</p>
                </div>
                <div class="min-w-[140px] rounded-2xl bg-emerald-50 p-3 shadow-[0_18px_40px_-32px_rgba(15,23,42,0.6)] ring-1 ring-emerald-100">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Occupancy</p>
                    <p class="mt-1.5 text-2xl font-bold text-emerald-700">{{ $summary['ward_occupancy'] }}%</p>
                    <p class="text-[11px] text-emerald-700">Whole ward</p>
                </div>
            </section>

            <section class="mt-4">
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-500">Bedside Queue</p>
                        <h3 class="mt-0.5 text-lg font-bold text-slate-900">Patients</h3>
                    </div>
                    <div class="rounded-xl bg-slate-900 px-2.5 py-1.5 text-right text-white">
                        <p class="text-[9px] uppercase tracking-[0.18em] text-slate-300">Total</p>
                        <p class="text-sm font-bold leading-tight">{{ $assignedBeds->count() }}</p>
                    </div>
                </div>

                @if($assignedBeds->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">No beds assigned right now</h3>
                        <p class="mt-2 text-sm text-slate-500">
                            This view will fill automatically when this nurse has current shift bed assignments.
                        </p>
                    </div>
                @else
                    <div
                        x-data="{
                            currentIndex: 0,
                            total: {{ $assignedBeds->count() }},
                            next() { if (this.currentIndex < this.total - 1) this.currentIndex++ },
                            prev() { if (this.currentIndex > 0) this.currentIndex-- },
                            jumpTo(index) { this.currentIndex = index },
                        }"
                        class="space-y-4"
                    >
                        <div class="rounded-2xl bg-white p-2.5 shadow-sm ring-1 ring-black/5">
                            <div class="hide-scrollbar flex gap-1.5 overflow-x-auto">
                                @foreach($assignedBeds as $bedIndex => $bed)
                                    <button type="button" @click="jumpTo({{ $bedIndex }})"
                                        :class="currentIndex === {{ $bedIndex }} ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
                                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition">
                                        Bed {{ $bed['number'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="relative">
                            @foreach($assignedBeds as $bedIndex => $bed)
                                @php
                                    $vitals = $bed['patient_id'] ? $latestVitalsByPatient->get($bed['patient_id']) : null;
                                    $infusions = $bed['patient_id'] ? ($infusionsByPatient->get($bed['patient_id']) ?? collect()) : collect();
                                    $headerClass = match ($bed['status']) {
                                        'occupied' => 'from-slate-950 via-cyan-900 to-teal-800',
                                        'reserved' => 'from-amber-900 via-amber-700 to-orange-500',
                                        default => 'from-slate-600 via-slate-500 to-slate-400',
                                    };
                                    $ewsClass = 'bg-emerald-500';
                                    if (($bed['ews'] ?? 0) >= 5) {
                                        $ewsClass = 'bg-rose-500';
                                    } elseif (($bed['ews'] ?? 0) >= 3) {
                                        $ewsClass = 'bg-amber-500';
                                    }
                                @endphp

                                <article
                                    x-show="currentIndex === {{ $bedIndex }}"
                                    x-transition:enter="transition ease-out duration-250"
                                    x-transition:enter-start="opacity-0 translate-x-4"
                                    x-transition:enter-end="opacity-100 translate-x-0"
                                    x-transition:leave="transition ease-in duration-200"
                                    x-transition:leave-start="opacity-100 translate-x-0"
                                    x-transition:leave-end="opacity-0 -translate-x-4"
                                    class="overflow-hidden rounded-2xl bg-white shadow-[0_22px_50px_-38px_rgba(15,23,42,0.65)] ring-1 ring-black/5"
                                >
                                    <div class="bg-gradient-to-r {{ $headerClass }} p-4 text-white">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="inline-flex items-center rounded-full bg-white/12 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.24em] text-cyan-50">
                                                    Bed {{ $bed['number'] }}
                                                </div>
                                                <h4 class="mt-2 truncate text-xl font-bold">{{ $bed['patient_name'] ?? 'Unoccupied Bed' }}</h4>
                                                <p class="mt-1 text-xs text-cyan-50/85">
                                                    MRN {{ $bed['mrn'] ?? 'N/A' }}
                                                    @if($bed['gender'] || $bed['age'])
                                                        <span class="mx-1.5">&bull;</span>{{ $bed['gender'] ?? '-' }}, {{ $bed['age'] ?? '-' }} yrs
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                @if($bed['ews_has_vitals'] && $bed['ews'] !== null)
                                                    <span class="inline-flex items-center rounded-full {{ $ewsClass }} px-3 py-1 text-sm font-bold text-white">
                                                        EWS {{ $bed['ews'] }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                                                        No vitals
                                                    </span>
                                                @endif
                                                <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-50/75">
                                                    {{ ucfirst($bed['status']) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3 p-4">
                                        <div class="grid grid-cols-2 gap-2.5">
                                            <div class="rounded-xl bg-slate-50 p-2.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Stay</p>
                                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                                    {{ $bed['days'] !== null ? $bed['days'] . 'd ' . ($bed['hours'] ?? 0) . 'h' : 'N/A' }}
                                                </p>
                                            </div>
                                            <div class="rounded-xl bg-slate-50 p-2.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Nurse</p>
                                                <p class="mt-1 line-clamp-2 text-sm font-semibold text-slate-900">{{ $bed['nurse_on_duty'] ?? $nurse->name }}</p>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-200 p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <h5 class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Patient Details</h5>
                                                @if($bed['is_pending_discharge'])
                                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Pending discharge</span>
                                                @endif
                                            </div>
                                            <dl class="mt-2 space-y-2 text-xs">
                                                <div class="flex items-start justify-between gap-4">
                                                    <dt class="text-slate-500">Consultant</dt>
                                                    <dd class="max-w-[58%] text-right font-medium text-slate-900">{{ $bed['consultant'] ?? 'Not assigned' }}</dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-4">
                                                    <dt class="text-slate-500">Diet</dt>
                                                    <dd class="max-w-[58%] text-right font-medium text-slate-900">{{ $bed['diet_types_display'] ?? 'Regular diet' }}</dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-4">
                                                    <dt class="text-slate-500">Isolation</dt>
                                                    <dd class="max-w-[58%] text-right font-medium text-slate-900">{{ $bed['isolation_type_name'] ?? 'None' }}</dd>
                                                </div>
                                                <div class="flex items-start justify-between gap-4">
                                                    <dt class="text-slate-500">Fall risk</dt>
                                                    <dd class="max-w-[58%] text-right font-medium text-slate-900">{{ $bed['fall_risk'] ? ucfirst(str_replace('_', ' ', $bed['fall_risk'])) : 'None' }}</dd>
                                                </div>
                                                @if($bed['is_outside'])
                                                    <div class="flex items-start justify-between gap-4">
                                                        <dt class="text-slate-500">Movement</dt>
                                                        <dd class="max-w-[58%] text-right font-medium text-amber-700">{{ $bed['current_movement_location'] ?? 'Outside ward' }}</dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>

                                        <div class="rounded-xl bg-slate-950 p-3 text-white">
                                            <div class="flex items-center justify-between gap-3">
                                                <h5 class="text-[10px] font-semibold uppercase tracking-[0.22em] text-cyan-100">Latest Vitals</h5>
                                                <span class="text-[10px] text-slate-300">
                                                    {{ $vitals?->recorded_at ? $vitals->recorded_at->format('d M H:i') : 'Awaiting data' }}
                                                </span>
                                            </div>
                                            <div class="mt-2.5 grid grid-cols-3 gap-2">
                                                <div class="rounded-lg bg-white/8 p-2">
                                                    <p class="text-[9px] uppercase tracking-[0.16em] text-slate-300">Pulse</p>
                                                    <p class="mt-1 text-sm font-semibold">{{ $vitals?->pulse_rate_display ?? '--' }}</p>
                                                </div>
                                                <div class="rounded-lg bg-white/8 p-2">
                                                    <p class="text-[9px] uppercase tracking-[0.16em] text-slate-300">BP</p>
                                                    <p class="mt-1 text-sm font-semibold">
                                                        {{ $vitals && ($vitals->systolic_bp || $vitals->diastolic_bp) ? trim(($vitals->systolic_bp ?? '--') . '/' . ($vitals->diastolic_bp ?? '--'), '/') : '--' }}
                                                    </p>
                                                </div>
                                                <div class="rounded-lg bg-white/8 p-2">
                                                    <p class="text-[9px] uppercase tracking-[0.16em] text-slate-300">SpO2</p>
                                                    <p class="mt-1 text-sm font-semibold">{{ $vitals?->spo2_display ? $vitals->spo2_display . '%' : '--' }}</p>
                                                </div>
                                                <div class="rounded-lg bg-white/8 p-2">
                                                    <p class="text-[9px] uppercase tracking-[0.16em] text-slate-300">Resp</p>
                                                    <p class="mt-1 text-sm font-semibold">{{ $vitals?->respiratory_rate ?? '--' }}</p>
                                                </div>
                                                <div class="rounded-lg bg-white/8 p-2">
                                                    <p class="text-[9px] uppercase tracking-[0.16em] text-slate-300">Temp</p>
                                                    <p class="mt-1 text-sm font-semibold">{{ $vitals?->temperature ? number_format((float) $vitals->temperature, 1) . '&deg;C' : '--' }}</p>
                                                </div>
                                                <div class="rounded-lg bg-white/8 p-2">
                                                    <p class="text-[9px] uppercase tracking-[0.16em] text-slate-300">HGT</p>
                                                    <p class="mt-1 text-sm font-semibold">{{ $bed['last_hgt']['value'] ?? '--' }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-200 p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <h5 class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Infusion</h5>
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $infusions->count() }} active</span>
                                            </div>

                                            @if($infusions->isEmpty())
                                                <p class="mt-2 text-xs text-slate-500">No active infusion activity for this patient.</p>
                                            @else
                                                <div class="mt-2 space-y-2">
                                                    @foreach($infusions->take(3) as $infusion)
                                                        @php
                                                            $badgeClass = match ($infusion->status) {
                                                                'alarming' => 'bg-rose-100 text-rose-700',
                                                                'paused' => 'bg-amber-100 text-amber-700',
                                                                default => 'bg-emerald-100 text-emerald-700',
                                                            };
                                                        @endphp
                                                        <div class="rounded-xl bg-slate-50 p-2.5">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $infusion->medication_name }}</p>
                                                                    <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                                                        {{ $infusion->infusionPump?->device_id ?? 'Unknown device' }}
                                                                        @if($infusion->flow_rate)
                                                                            <span class="mx-1.5">&bull;</span>{{ number_format((float) $infusion->flow_rate, 2) }} mL/hr
                                                                        @endif
                                                                    </p>
                                                                </div>
                                                                <div class="flex flex-col items-end gap-1">
                                                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badgeClass }}">{{ ucfirst($infusion->status) }}</span>
                                                                    @if($infusion->is_warning)
                                                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Warning</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="mt-2 grid grid-cols-3 gap-2 text-[10px] text-slate-500">
                                                                <div>
                                                                    <p class="uppercase tracking-[0.16em]">Remaining</p>
                                                                    <p class="mt-0.5 text-xs font-semibold text-slate-900">{{ $infusion->formatted_remaining_time }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="uppercase tracking-[0.16em]">Volume</p>
                                                                    <p class="mt-0.5 text-xs font-semibold text-slate-900">{{ $infusion->remaining_volume !== null ? number_format((float) $infusion->remaining_volume, 1) . ' mL' : '--' }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="uppercase tracking-[0.16em]">Updated</p>
                                                                    <p class="mt-0.5 text-xs font-semibold text-slate-900">{{ $infusion->last_updated_at ? $infusion->last_updated_at->format('H:i') : '--' }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    <nav class="app-tabbar">
                        <div class="flex items-center justify-between px-3 py-2">
                            <button type="button" @click="prev()" :disabled="currentIndex === 0"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                Prev
                            </button>
                            <div class="px-3 text-center">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.2em] text-slate-500">Bed</p>
                                <p class="text-sm font-bold text-slate-900" x-text="`${currentIndex + 1} / ${total}`"></p>
                            </div>
                            <button type="button" @click="next()" :disabled="currentIndex >= total - 1"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40">
                                Next
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </nav>
                @endif
            </section>
        </main>
    </div>

    <script>
        (function () {
            const el = document.getElementById('app-clock');
            if (!el) return;
            const tick = () => {
                const d = new Date();
                el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            };
            tick();
            setInterval(tick, 30000);
        })();
    </script>
</body>
</html>
