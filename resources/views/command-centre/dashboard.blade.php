<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $commandCentre->name }} — Dashboard</title>
<style>.font-sans{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;}</style>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-950 text-white overflow-hidden">
<div class="h-screen flex flex-col" x-data="pfcc()" x-init="init()">

{{-- Header --}}
<header class="flex-shrink-0 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-700/50 px-5 py-2.5">
<div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="p-1.5 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-lg shadow-lg shadow-cyan-500/20">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/></svg>
        </div>
        <div><h1 class="text-base font-black text-white">{{ $commandCentre->name }}</h1><p class="text-[10px] text-slate-400">Patient Flow Command Centre</p></div>
        <div class="ml-2 flex items-center px-2 py-0.5 bg-green-500/10 border border-green-500/30 rounded-full">
            <span class="relative flex h-1.5 w-1.5 mr-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-green-500"></span></span>
            <span class="text-[10px] font-bold text-green-400">LIVE</span>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-xs text-slate-400 hidden md:inline" x-text="clock"></span>
        <button @click="goFullscreen()" class="p-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-600 border border-slate-600 text-slate-300 hover:text-white transition-all" title="Fullscreen">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
        </button>
        <form method="POST" action="{{ route('command-centre.logout') }}" class="inline">@csrf
            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 text-xs font-semibold transition-all">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Logout
            </button>
        </form>
    </div>
</div>
</header>

{{-- Main --}}
<main class="flex-1 overflow-y-auto p-4">

@if($settings['show_summary_cards'])
<div class="grid grid-cols-3 md:grid-cols-6 gap-2 mb-4">
    <template x-for="card in summaryCards" :key="card.label">
        <div class="bg-slate-800/60 backdrop-blur rounded-xl border p-3 text-center" :class="card.border">
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-0.5" x-text="card.label"></p>
            <p class="text-xl font-black" :class="card.color" x-text="card.value"></p>
        </div>
    </template>
</div>
@endif

{{-- Summary Table --}}
<div class="bg-slate-900/80 backdrop-blur rounded-xl border border-slate-700/50 shadow-2xl overflow-hidden mb-4">
<div class="bg-gradient-to-r from-cyan-600 to-blue-700 px-4 py-2 flex items-center justify-between">
    <h3 class="text-xs font-black text-white uppercase tracking-widest">Ward Overview</h3>
    <span class="text-[10px] text-blue-200 bg-blue-800/40 px-2 py-0.5 rounded-full">Auto-refresh: <span x-text="refreshInterval"></span>s</span>
</div>
<div class="overflow-x-auto">
<table class="min-w-full text-sm">
<thead><tr class="bg-slate-800/80 border-b border-slate-700">
    <th class="px-4 py-2 text-left text-[10px] font-bold text-cyan-400 uppercase tracking-widest">Ward</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Beds</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Occupied</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Available</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Admitted</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Pend.Disch</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Discharged</th>
    <th class="px-3 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Prebooked</th>
    <th class="px-4 py-2 text-center text-[10px] font-bold text-cyan-400 uppercase">Occupancy</th>
</tr></thead>
<tbody>
<template x-for="(w,i) in wards" :key="w.id">
<tr class="border-b border-slate-700/40 hover:bg-slate-800/40 transition-colors" :class="i%2===0?'bg-slate-800/20':''">
    <td class="px-4 py-2.5"><div class="flex items-center"><div class="w-1 h-6 rounded-full mr-2" :class="w.occupancy_percent>=90?'bg-red-500':(w.occupancy_percent>=70?'bg-amber-500':'bg-green-500')"></div><div><p class="text-xs font-bold text-white" x-text="w.ward_name"></p><p class="text-[10px] text-slate-500" x-text="w.ward_code"></p></div></div></td>
    <td class="px-3 py-2.5 text-center text-xs font-bold text-slate-200" x-text="w.total_beds"></td>
    <td class="px-3 py-2.5 text-center"><span class="text-xs font-bold" :class="w.occupied_beds>0?'text-orange-400':'text-slate-600'" x-text="w.occupied_beds"></span></td>
    <td class="px-3 py-2.5 text-center"><span class="text-xs font-bold" :class="w.available_beds>0?'text-green-400':'text-red-400'" x-text="w.available_beds"></span></td>
    <td class="px-3 py-2.5 text-center"><span class="text-xs font-bold" :class="w.admissions_today>0?'text-blue-400':'text-slate-600'" x-text="w.admissions_today"></span></td>
    <td class="px-3 py-2.5 text-center"><span class="text-xs font-bold" :class="w.pending_discharge>0?'text-amber-400 animate-pulse':'text-slate-600'" x-text="w.pending_discharge"></span></td>
    <td class="px-3 py-2.5 text-center"><span class="text-xs font-bold" :class="w.discharged_today>0?'text-teal-400':'text-slate-600'" x-text="w.discharged_today"></span></td>
    <td class="px-3 py-2.5 text-center"><span class="text-xs font-bold" :class="w.prebooked>0?'text-purple-400':'text-slate-600'" x-text="w.prebooked"></span></td>
    <td class="px-4 py-2.5"><div class="flex items-center gap-2"><div class="flex-1 bg-slate-700 rounded-full h-2 min-w-[60px] overflow-hidden"><div class="h-full rounded-full transition-all duration-700" :class="w.occupancy_percent>=90?'bg-gradient-to-r from-red-500 to-red-400':(w.occupancy_percent>=70?'bg-gradient-to-r from-amber-500 to-amber-400':'bg-gradient-to-r from-green-500 to-emerald-400')" :style="'width:'+Math.min(w.occupancy_percent,100)+'%'"></div></div><span class="text-[10px] font-bold min-w-[2rem] text-right" :class="w.occupancy_percent>=90?'text-red-400':(w.occupancy_percent>=70?'text-amber-400':'text-green-400')" x-text="w.occupancy_percent+'%'"></span></div></td>
</tr>
</template>
</tbody>
</table>
</div>
<div class="bg-slate-800/60 px-4 py-1.5 border-t border-slate-700/50 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-green-500"></div><span class="text-[10px] text-slate-500">&lt;70%</span></div>
        <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-amber-500"></div><span class="text-[10px] text-slate-500">70-89%</span></div>
        <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-red-500"></div><span class="text-[10px] text-slate-500">≥90%</span></div>
    </div>
    <span class="text-[10px] text-slate-600">Updated: <span class="text-slate-500" x-text="lastUpdated"></span></span>
</div>
</div>

{{-- Bed Detail Sections --}}
@if($settings['show_bed_details'])
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <template x-for="section in activeSections" :key="section.key">
        <div class="bg-slate-900/80 backdrop-blur rounded-xl border border-slate-700/50 overflow-hidden">
            <div class="px-4 py-2 border-b border-slate-700/50 flex items-center gap-2" :class="section.headerBg">
                <div class="w-2.5 h-2.5 rounded-full" :class="section.dot"></div>
                <h4 class="text-xs font-black uppercase tracking-widest" :class="section.titleColor" x-text="section.title"></h4>
                <span class="ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full" :class="section.badge" x-text="countByStatus(section.status)"></span>
            </div>
            <div class="max-h-52 overflow-y-auto">
                <table class="min-w-full text-xs">
                    <thead><tr class="bg-slate-800/50">
                        <th class="px-3 py-1.5 text-left text-[9px] font-bold text-slate-500 uppercase">Bed</th>
                        <th class="px-3 py-1.5 text-left text-[9px] font-bold text-slate-500 uppercase" x-show="showPatientName">Patient</th>
                        <th class="px-3 py-1.5 text-left text-[9px] font-bold text-slate-500 uppercase">MRN</th>
                        <th class="px-3 py-1.5 text-left text-[9px] font-bold text-slate-500 uppercase" x-show="showConsultant">Consultant</th>
                        <th class="px-3 py-1.5 text-right text-[9px] font-bold text-slate-500 uppercase">Time</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="row in filterByStatus(section.status)" :key="row.mrn+row.bed_number">
                            <tr class="border-b border-slate-800/50 hover:bg-slate-800/30">
                                <td class="px-3 py-1.5 font-bold text-white" x-text="row.bed_number"></td>
                                <td class="px-3 py-1.5 text-slate-300" x-show="showPatientName" x-text="row.patient_name"></td>
                                <td class="px-3 py-1.5 text-slate-400 font-mono" x-text="row.mrn"></td>
                                <td class="px-3 py-1.5 text-slate-400" x-show="showConsultant" x-text="row.consultant_name"></td>
                                <td class="px-3 py-1.5 text-right text-slate-500" x-text="row.timestamp"></td>
                            </tr>
                        </template>
                        <template x-if="filterByStatus(section.status).length===0">
                            <tr><td colspan="5" class="px-3 py-4 text-center text-slate-600 text-[11px]">No records</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
</div>
@endif
</main>

<footer class="flex-shrink-0 bg-slate-900 border-t border-slate-800 px-4 py-1.5 flex items-center justify-between">
    <span class="text-[10px] text-slate-600">PHKL Smart Ward 4.0</span>
    <div class="flex items-center gap-1.5"><span class="text-[10px] text-slate-600">Developed by</span><img src="{{ asset('logo_qmed.png') }}" alt="Qmed" class="h-3 w-auto opacity-40"></div>
</footer>
</div>

<script>
function pfcc() {
    const s = @json($settings);
    return {
        wards: @json($wardData),
        clock: '', lastUpdated: '', refreshInterval: s.refresh_interval || 30,
        showPatientName: s.show_patient_name || false,
        showConsultant: s.show_consultant !== false,
        get allBedDetails() { return this.wards.flatMap(w => (w.bed_details||[]).map(b => ({...b, ward_name: w.ward_name}))); },
        get summaryCards() {
            const w=this.wards, tb=w.reduce((a,x)=>a+x.total_beds,0), to=w.reduce((a,x)=>a+x.occupied_beds,0);
            return [
                {label:'Wards',value:w.length,color:'text-white',border:'border-slate-700/50'},
                {label:'Total Beds',value:tb,color:'text-blue-400',border:'border-blue-500/20'},
                {label:'Occupied',value:to,color:'text-orange-400',border:'border-orange-500/20'},
                {label:'Available',value:w.reduce((a,x)=>a+x.available_beds,0),color:'text-green-400',border:'border-green-500/20'},
                {label:'Pend. Discharge',value:w.reduce((a,x)=>a+x.pending_discharge,0),color:'text-amber-400',border:'border-amber-500/20'},
                {label:'Discharged Today',value:w.reduce((a,x)=>a+x.discharged_today,0),color:'text-teal-400',border:'border-teal-500/20'},
            ];
        },
        get activeSections() {
            const sec = [];
            if(s.show_admitting_section) sec.push({key:'admitting',title:'Admitting',status:'Admitting',dot:'bg-blue-500',headerBg:'bg-blue-500/5',titleColor:'text-blue-400',badge:'bg-blue-500/20 text-blue-400'});
            if(s.show_pending_discharge_section) sec.push({key:'pending',title:'Pending Discharge',status:'Pending Discharge',dot:'bg-amber-500',headerBg:'bg-amber-500/5',titleColor:'text-amber-400',badge:'bg-amber-500/20 text-amber-400'});
            if(s.show_discharged_section) sec.push({key:'discharged',title:'Discharged',status:'Discharged',dot:'bg-teal-500',headerBg:'bg-teal-500/5',titleColor:'text-teal-400',badge:'bg-teal-500/20 text-teal-400'});
            if(s.show_prebooked_section) sec.push({key:'prebooked',title:'Prebooked',status:'Prebooked',dot:'bg-purple-500',headerBg:'bg-purple-500/5',titleColor:'text-purple-400',badge:'bg-purple-500/20 text-purple-400'});
            return sec;
        },
        filterByStatus(st) { return this.allBedDetails.filter(b=>b.status===st); },
        countByStatus(st) { return this.filterByStatus(st).length; },
        init() {
            this.tick(); this.lastUpdated=this.now();
            setInterval(()=>this.tick(),1000);
            setInterval(()=>this.fetchData(), this.refreshInterval*1000);
        },
        tick(){const d=new Date();this.clock=d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})+', '+d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});},
        now(){return this.clock||'';},
        async fetchData(){try{const r=await fetch('{{ route("command-centre.view.data",$commandCentre->id) }}');const d=await r.json();if(d.wardData){this.wards=d.wardData;this.lastUpdated=d.timestamp;}}catch(e){console.error(e);}},
        goFullscreen(){if(!document.fullscreenElement)document.documentElement.requestFullscreen();else document.exitFullscreen();}
    }
}
</script>
</body>
</html>
