{{--
    Shared gateway detail body.
    Reused by the details-view card and the control-panel "info" modal so the
    heartbeat/stats/SSH/linked-users rendering lives in one place.

    Expects: $gateway (App\Models\QmedGateway)
--}}
@php
    $hb = $gateway->last_heartbeat ?? [];
    $usingHeartbeat = (bool) $gateway->last_heartbeat_at;
    $lastSeen = $gateway->last_heartbeat_at ?? $gateway->last_ping_at;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-y-2 gap-x-6 text-sm text-gray-600">
    <div>
        <span class="font-medium text-gray-500">Ward:</span>
        {{ $gateway->ward?->ward_name ?? 'Unassigned' }}
    </div>
    <div>
        <span class="font-medium text-gray-500">Hostname:</span>
        {{ $gateway->hostname ?? 'N/A' }}
    </div>
    <div>
        <span class="font-medium text-gray-500">Location:</span>
        {{ $gateway->location ?? 'N/A' }}
    </div>
    <div>
        <span class="font-medium text-gray-500">{{ $usingHeartbeat ? 'Last Heartbeat' : 'Last Ping' }}:</span>
        {{ $lastSeen ? $lastSeen->diffForHumans() : 'Never' }}
    </div>
    <div>
        <span class="font-medium text-gray-500">Last IP:</span>
        <code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $gateway->last_ping_ip ?? 'N/A' }}</code>
    </div>
    <div>
        <span class="font-medium text-gray-500">MAC Address:</span>
        <code class="bg-gray-100 px-1.5 py-0.5 rounded">{{ $gateway->mac_address ?? 'N/A' }}</code>
    </div>
    @if($usingHeartbeat)
        @php
            $mConn = data_get($hb, 'monitor.connected');
            $mCount = data_get($hb, 'monitor.count');
            $qPending = data_get($hb, 'queue.pending', 0);
            $qDead = data_get($hb, 'queue.dead', 0);
            $uvNow = data_get($hb, 'power.undervoltage_now');
            $uvSeen = data_get($hb, 'power.undervoltage_seen');
        @endphp
        <div>
            <span class="font-medium text-gray-500">Monitor:</span>
            @if($mConn !== null)
                <span class="{{ $mConn > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $mConn }}/{{ $mCount }} connected</span>
            @else N/A @endif
        </div>
        <div>
            <span class="font-medium text-gray-500">Queue:</span>
            {{ $qPending }} pending{!! $qDead > 0 ? ', <span class="text-red-600 font-semibold">' . e($qDead) . ' dead-lettered</span>' : '' !!}
        </div>
        <div>
            <span class="font-medium text-gray-500">Power:</span>
            @if($uvNow)<span class="text-red-600 font-semibold">under-voltage now</span>
            @elseif($uvSeen)<span class="text-orange-600">under-voltage seen</span>
            @elseif($uvNow === false)<span class="text-green-600">OK</span>
            @else N/A @endif
        </div>
        <div>
            <span class="font-medium text-gray-500">App / Disk:</span>
            {{ data_get($hb, 'app_version', '?') }}@if(data_get($hb, 'disk_free_pct') !== null) &middot; {{ data_get($hb, 'disk_free_pct') }}% free @endif
        </div>
        <div>
            <span class="font-medium text-gray-500">Listener:</span>
            @php $svc = data_get($hb, 'service'); @endphp
            @if(data_get($svc, 'active_state'))
                <span class="{{ data_get($svc, 'active_state') === 'active' ? 'text-green-600' : 'text-red-600' }}">{{ data_get($svc, 'active_state') }} ({{ data_get($svc, 'sub_state', '?') }})</span>@if(data_get($svc, 'n_restarts'))<span class="text-orange-600"> &middot; {{ data_get($svc, 'n_restarts') }} restart(s)</span>@endif
            @else
                <span class="text-gray-400">N/A</span>
            @endif
        </div>
        <div>
            <span class="font-medium text-gray-500">Net recovery:</span>
            @php $nw = data_get($hb, 'netwatch'); @endphp
            @if($nw)
                <span class="{{ data_get($nw, 'reconnects', 0) > 0 ? 'text-orange-600' : 'text-green-600' }}">{{ data_get($nw, 'reconnects', 0) }} Wi-Fi reconnect(s)</span>@if(!data_get($nw, 'link_ok', true))<span class="text-red-600"> &middot; link down</span>@endif
            @else
                <span class="text-gray-400">N/A</span>
            @endif
        </div>
        @if(!empty($hb['identity_conflict']))
            <div class="md:col-span-2 text-red-700 font-semibold">
                ⚠ Identity conflict — this gateway_id is reporting from different hardware (cloned SD card?). Re-run <code>setup.sh</code> on that Pi.
            </div>
        @endif
    @endif
</div>

@if($usingHeartbeat && $gateway->last_stats)
    @php $st = $gateway->last_stats; @endphp
    <div class="mt-3 rounded-lg bg-gray-50 border border-gray-100 p-3 text-xs text-gray-600">
        <div class="font-semibold text-gray-500 uppercase tracking-wider mb-2">
            Details
            <span class="normal-case font-normal text-gray-400">· updated {{ $gateway->last_stats_at?->diffForHumans() }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-1 gap-x-6">
            <div><span class="text-gray-500">DB records:</span>
                {{ data_get($st, 'db.total_records', '—') }}
                (sent {{ data_get($st, 'db.sent', 0) }}, dead {{ data_get($st, 'db.dead', 0) }})</div>
            <div><span class="text-gray-500">DB size:</span> {{ data_get($st, 'db.db_size_mb', '—') }} MB</div>
            <div class="md:col-span-2"><span class="text-gray-500">Record range:</span>
                {{ data_get($st, 'db.oldest_record_at', '—') }} &rarr; {{ data_get($st, 'db.newest_record_at', '—') }} (UTC)</div>
            <div><span class="text-gray-500">Last sent:</span> {{ data_get($st, 'db.last_sent_at', '—') ?? 'never' }}</div>
            <div><span class="text-gray-500">Wi-Fi:</span>
                {{ data_get($st, 'network.ssid', '—') ?? '—' }}{{ data_get($st, 'network.wifi_signal') !== null ? ' (' . data_get($st, 'network.wifi_signal') . '%)' : '' }}</div>
            <div><span class="text-gray-500">Uplink:</span> {{ data_get($st, 'network.uplink_if', '—') ?? '—' }}</div>
        </div>
    </div>
@endif

@if($gateway->ssh_command)
    <div class="mt-3" x-data="{ pinging:false, result:null, ok:false, copied:false,
        async ping(){ this.pinging=true; this.result=null;
            try {
                const r = await fetch('{{ route('vital-sign-integration.gateway.ping', $gateway) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});
                const d = await r.json();
                this.ok = d.reachable;
                this.result = d.reachable ? ('Reachable · '+d.ms+' ms') : (d.message || 'Unreachable');
            } catch(e){ this.ok=false; this.result='Request failed'; }
            this.pinging=false;
        },
        copy(){ navigator.clipboard.writeText('{{ $gateway->ssh_command }}'); this.copied=true; setTimeout(()=>this.copied=false,1500); } }">
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">SSH Access</span>
        <div class="flex flex-wrap items-center gap-2 mt-1">
            <code class="text-xs bg-gray-900 text-emerald-300 px-2 py-1 rounded font-mono">{{ $gateway->ssh_command }}</code>
            <button type="button" @click="copy()" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded">Copy</button>
            <button type="button" @click="ping()" :disabled="pinging" class="px-2 py-1 text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded disabled:opacity-50">
                <span x-show="!pinging">Ping</span><span x-show="pinging">Pinging…</span>
            </button>
            <span x-show="copied" class="text-xs text-emerald-600">Copied!</span>
            <span x-show="result" class="text-xs font-medium" :class="ok ? 'text-green-600' : 'text-red-600'" x-text="result"></span>
        </div>
    </div>
@endif

<div class="mt-3">
    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Linked API Users</span>
    <div class="flex flex-wrap gap-2 mt-1">
        @forelse($gateway->apiUsers as $user)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">
                {{ $user->name }}
            </span>
        @empty
            <span class="text-xs text-gray-400 italic">No API users linked</span>
        @endforelse
    </div>
</div>
