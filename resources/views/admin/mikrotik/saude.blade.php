@extends('layouts.admin')

@section('title', 'Saúde dos MikroTiks')

@section('breadcrumb')
    <span class="text-muted">›</span>
    <span class="text-green font-semibold">Saúde dos MikroTiks</span>
@endsection

@section('page-title', 'Saúde dos MikroTiks')

@section('content')
<div class="max-w-8xl mx-auto">

    {{-- Hero --}}
    <div class="bg-gradient-to-r from-green-dark via-green to-green-light rounded-xl px-5 py-4 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-0.5">Starlink · Monitoramento</p>
            <h1 class="text-xl font-bold text-white">Saúde dos {{ $summary['total'] }} MikroTiks</h1>
            <p class="text-xs text-white/70 mt-0.5">
                Cada ônibus sincroniza a cada 15s. Histórico de uptime dos últimos {{ $days }} dias.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span id="auto-refresh-indicator" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white/15 border border-white/20 rounded-lg text-[10px] font-semibold text-white">
                <span class="w-1.5 h-1.5 rounded-full bg-green-light animate-pulse"></span>
                Auto-refresh 15s
            </span>
            <button onclick="refreshNow()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white text-green font-bold text-xs rounded-lg hover:bg-green-pale transition shadow-card">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Atualizar
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-green/30 shadow-card p-4">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-green"></span>
                <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Online</p>
            </div>
            <p class="text-3xl font-bold text-green mt-1" id="sum-online">{{ $summary['online'] }}</p>
            <p class="text-[10px] text-muted mt-0.5">sync ≤ 30s</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/30 shadow-card p-4">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-gold"></span>
                <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Atrasado</p>
            </div>
            <p class="text-3xl font-bold text-gold mt-1" id="sum-lagging">{{ $summary['lagging'] }}</p>
            <p class="text-[10px] text-muted mt-0.5">30s a 5min</p>
        </div>
        <div class="bg-white rounded-xl border border-red/30 shadow-card p-4">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-red"></span>
                <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Offline</p>
            </div>
            <p class="text-3xl font-bold text-red mt-1" id="sum-offline">{{ $summary['offline'] }}</p>
            <p class="text-[10px] text-muted mt-0.5">&gt; 5min sem sync</p>
        </div>
        <div class="bg-white rounded-xl border border-blue/30 shadow-card p-4">
            <div class="flex items-center gap-2">
                <svg class="w-2.5 h-2.5 text-blue" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>
                <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Usuários</p>
            </div>
            <p class="text-3xl font-bold text-blue mt-1" id="sum-users">{{ $summary['total_users'] }}</p>
            <p class="text-[10px] text-muted mt-0.5">conectados agora</p>
        </div>
    </div>

    {{-- Status atual + Histórico por ônibus --}}
    <div class="space-y-4" id="bus-list">
        @if($data->isEmpty())
            <div class="bg-white rounded-xl border border-border shadow-card p-8 text-center text-muted text-sm">
                Nenhum MikroTik registrado ainda. Quando qualquer ônibus fizer o primeiro sync,
                ele aparece aqui automaticamente.
            </div>
        @else
            @foreach($data as $item)
                @php
                    $bus = $item['bus'];
                    $status = $item['status'];
                    $secs = $item['seconds_since_sync'];
                    $busHistory = $history[$bus->id] ?? [];

                    $colorMap = [
                        'online'  => ['dot' => 'bg-green', 'text' => 'text-green', 'label' => 'ONLINE', 'badge' => 'bg-green-pale text-green border-green/30', 'border' => 'border-green/30'],
                        'lagging' => ['dot' => 'bg-gold', 'text' => 'text-gold', 'label' => 'ATRASADO', 'badge' => 'bg-gold-pale text-gold border-gold/30', 'border' => 'border-gold/30'],
                        'offline' => ['dot' => 'bg-red', 'text' => 'text-red', 'label' => 'OFFLINE', 'badge' => 'bg-red-pale text-red border-red/30', 'border' => 'border-red/30'],
                        'unknown' => ['dot' => 'bg-muted', 'text' => 'text-muted', 'label' => 'NUNCA SINCRONIZOU', 'badge' => 'bg-surface text-muted border-border', 'border' => 'border-border'],
                    ];
                    $c = $colorMap[$status];

                    if ($secs === null) {
                        $syncText = 'nunca sincronizou';
                    } elseif ($secs < 60) {
                        $syncText = $secs . 's atrás';
                    } elseif ($secs < 3600) {
                        $syncText = floor($secs / 60) . 'min atrás';
                    } else {
                        $syncText = floor($secs / 3600) . 'h ' . (floor(($secs % 3600) / 60)) . 'min atrás';
                    }
                @endphp

                <div class="bg-white rounded-xl border {{ $c['border'] }} shadow-card overflow-hidden" data-serial="{{ $bus->mikrotik_serial }}">
                    {{-- Cabeçalho do ônibus --}}
                    <div class="px-5 py-4 flex items-center gap-4 cursor-pointer hover:bg-surface/30 transition"
                         onclick="toggleHistory('history-{{ $bus->id }}')">
                        <div class="w-3.5 h-3.5 rounded-full {{ $c['dot'] }} flex-shrink-0 shadow-sm" data-dot></div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-ink text-sm truncate">{{ $bus->name }}</p>
                                @if($bus->plate)
                                    <span class="text-[10px] text-muted font-mono bg-surface px-1.5 py-0.5 rounded">{{ $bus->plate }}</span>
                                @endif
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $c['badge'] }}" data-badge>
                                    {{ $c['label'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-[11px] text-muted flex-wrap">
                                <span class="font-mono">{{ $bus->mikrotik_serial }}</span>
                                @if($bus->last_public_ip)
                                    <span>· IP {{ $bus->last_public_ip }}</span>
                                @endif
                                @if($bus->last_city || $bus->last_state)
                                    <span>· {{ trim(($bus->last_city ?? '') . ' ' . ($bus->last_state ?? '')) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Último sync</p>
                            <p class="text-sm font-bold {{ $c['text'] }}" data-sync-text>{{ $syncText }}</p>
                        </div>

                        <div class="text-right flex-shrink-0 w-20">
                            <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Latência</p>
                            @php
                                $latency = $item['latency_ms'] ?? null;
                                if ($latency === null) {
                                    $latencyLabel = '—';
                                    $latencyColor = 'text-muted';
                                    $latencyQuality = '';
                                } elseif ($latency <= 100) {
                                    $latencyLabel = $latency . 'ms';
                                    $latencyColor = 'text-green';
                                    $latencyQuality = 'Rápida';
                                } elseif ($latency <= 300) {
                                    $latencyLabel = $latency . 'ms';
                                    $latencyColor = 'text-gold';
                                    $latencyQuality = 'Média';
                                } else {
                                    $latencyLabel = $latency . 'ms';
                                    $latencyColor = 'text-red';
                                    $latencyQuality = 'Lenta';
                                }
                            @endphp
                            <p class="text-sm font-bold {{ $latencyColor }}" data-latency>{{ $latencyLabel }}</p>
                            @if($latencyQuality)
                                <p class="text-[9px] {{ $latencyColor }}">{{ $latencyQuality }}</p>
                            @endif
                        </div>

                        <div class="text-right flex-shrink-0 w-16">
                            <p class="text-[10px] text-muted font-medium uppercase tracking-wider">Usuários</p>
                            <p class="text-sm font-bold text-ink" data-users>{{ $item['active_users'] }}</p>
                        </div>

                        <div class="flex-shrink-0">
                            <svg class="w-4 h-4 text-muted transition-transform" id="chevron-{{ $bus->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Histórico diário (colapsável) --}}
                    <div id="history-{{ $bus->id }}" class="hidden border-t border-border">
                        @if(empty($busHistory) || collect($busHistory)->every(fn($d) => $d['total_checks'] === 0))
                            <div class="px-5 py-4 text-center text-muted text-xs">
                                <svg class="w-5 h-5 mx-auto mb-1 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Sem dados históricos ainda. O monitoramento grava snapshots a cada 5 minutos.
                            </div>
                        @else
                            <div class="px-5 py-3">
                                <p class="text-[10px] font-bold text-muted uppercase tracking-wider mb-3">
                                    Uptime dos últimos {{ $days }} dias
                                </p>

                                {{-- Tabela de uptime diário --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-[10px] text-muted uppercase tracking-wider">
                                                <th class="text-left pb-2 font-medium">Dia</th>
                                                <th class="text-center pb-2 font-medium">Uptime</th>
                                                <th class="text-center pb-2 font-medium">Online</th>
                                                <th class="text-center pb-2 font-medium">Offline</th>
                                                <th class="text-left pb-2 font-medium pl-3" style="min-width:180px">Timeline</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border/50">
                                            @foreach(array_reverse($busHistory) as $day)
                                                @php
                                                    $isToday = $day['date'] === now()->format('Y-m-d');
                                                    $uptimePct = $day['uptime_percent'];
                                                    if ($uptimePct === null) {
                                                        $barColor = 'bg-muted/20';
                                                        $uptimeLabel = '—';
                                                        $uptimeTextColor = 'text-muted';
                                                    } elseif ($uptimePct >= 95) {
                                                        $barColor = 'bg-green';
                                                        $uptimeLabel = $uptimePct . '%';
                                                        $uptimeTextColor = 'text-green';
                                                    } elseif ($uptimePct >= 70) {
                                                        $barColor = 'bg-gold';
                                                        $uptimeLabel = $uptimePct . '%';
                                                        $uptimeTextColor = 'text-gold';
                                                    } else {
                                                        $barColor = 'bg-red';
                                                        $uptimeLabel = $uptimePct . '%';
                                                        $uptimeTextColor = 'text-red';
                                                    }
                                                @endphp
                                                <tr class="{{ $isToday ? 'bg-green-pale/30' : '' }}">
                                                    <td class="py-2 pr-3">
                                                        <span class="font-bold text-ink">{{ $day['date_label'] }}</span>
                                                        <span class="text-muted ml-1">{{ $day['day_name'] }}</span>
                                                        @if($isToday)
                                                            <span class="ml-1 text-[8px] font-bold text-green bg-green-pale px-1 py-0.5 rounded">HOJE</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-center">
                                                        <span class="font-bold {{ $uptimeTextColor }}">{{ $uptimeLabel }}</span>
                                                    </td>
                                                    <td class="py-2 text-center">
                                                        @if($day['total_checks'] > 0)
                                                            <span class="text-green font-medium">{{ $day['online_hours'] }}h</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 text-center">
                                                        @if($day['total_checks'] > 0)
                                                            @php
                                                                $offlineHours = round(($day['total_minutes'] - $day['online_minutes']) / 60, 1);
                                                            @endphp
                                                            <span class="{{ $offlineHours > 0 ? 'text-red font-medium' : 'text-muted' }}">
                                                                {{ $offlineHours > 0 ? $offlineHours . 'h' : '0h' }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2 pl-3">
                                                        @if($day['total_checks'] > 0)
                                                            <div class="flex items-center gap-1">
                                                                <div class="flex-1 h-4 bg-surface rounded-full overflow-hidden relative">
                                                                    <div class="{{ $barColor }} h-full rounded-full transition-all"
                                                                         style="width: {{ $uptimePct }}%"></div>
                                                                </div>
                                                                <span class="text-[10px] text-muted font-mono w-8 text-right">{{ $day['total_hours'] }}h</span>
                                                            </div>
                                                            @if(!empty($day['events']))
                                                                <div class="mt-1.5 space-y-0.5">
                                                                    @foreach($day['events'] as $event)
                                                                        @if($event['type'] === 'went_offline')
                                                                            <div class="flex items-center gap-1 text-[10px]">
                                                                                <span class="w-1.5 h-1.5 rounded-full bg-red flex-shrink-0"></span>
                                                                                <span class="text-red font-medium">Caiu às {{ $event['at'] }}</span>
                                                                            </div>
                                                                        @elseif($event['type'] === 'came_online')
                                                                            <div class="flex items-center gap-1 text-[10px]">
                                                                                <span class="w-1.5 h-1.5 rounded-full bg-green flex-shrink-0"></span>
                                                                                <span class="text-green font-medium">Voltou às {{ $event['at'] }}</span>
                                                                                @if($event['offline_duration_min'])
                                                                                    <span class="text-muted">({{ $event['offline_duration_min'] >= 60 ? floor($event['offline_duration_min'] / 60) . 'h' . ($event['offline_duration_min'] % 60 > 0 ? str_pad($event['offline_duration_min'] % 60, 2, '0', STR_PAD_LEFT) . 'min' : '') : $event['offline_duration_min'] . 'min' }} fora)</span>
                                                                                @endif
                                                                            </div>
                                                                        @elseif($event['type'] === 'still_offline')
                                                                            <div class="flex items-center gap-1 text-[10px]">
                                                                                <span class="w-1.5 h-1.5 rounded-full bg-red animate-pulse flex-shrink-0"></span>
                                                                                <span class="text-red font-medium">Offline desde {{ $event['since'] }}</span>
                                                                                <span class="text-muted">({{ $event['duration_min'] >= 60 ? floor($event['duration_min'] / 60) . 'h' . ($event['duration_min'] % 60 > 0 ? str_pad($event['duration_min'] % 60, 2, '0', STR_PAD_LEFT) . 'min' : '') : $event['duration_min'] . 'min' }})</span>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        @else
                                                            <div class="h-4 bg-surface/50 rounded-full flex items-center justify-center">
                                                                <span class="text-[9px] text-muted">sem dados</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Resumo rápido --}}
                                @php
                                    $daysWithData = collect($busHistory)->filter(fn($d) => $d['total_checks'] > 0);
                                    $avgUptime = $daysWithData->count() > 0
                                        ? round($daysWithData->avg('uptime_percent'), 1)
                                        : null;
                                    $totalOfflineHours = $daysWithData->sum(fn($d) => ($d['total_minutes'] - $d['online_minutes']) / 60);
                                @endphp
                                @if($daysWithData->count() > 0)
                                    <div class="mt-3 pt-3 border-t border-border/50 flex items-center gap-4 text-[11px]">
                                        <span class="text-muted">
                                            Média {{ $days }} dias:
                                            <strong class="{{ $avgUptime >= 95 ? 'text-green' : ($avgUptime >= 70 ? 'text-gold' : 'text-red') }}">
                                                {{ $avgUptime }}% uptime
                                            </strong>
                                        </span>
                                        <span class="text-muted">·</span>
                                        <span class="text-muted">
                                            Total offline:
                                            <strong class="{{ $totalOfflineHours > 2 ? 'text-red' : 'text-muted' }}">
                                                {{ round($totalOfflineHours, 1) }}h no período
                                            </strong>
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Legenda --}}
    <div class="mt-6 bg-surface/50 rounded-xl border border-border p-4 text-xs text-muted">
        <p class="font-bold text-ink mb-2">Como funciona o monitoramento:</p>
        <ul class="space-y-1.5">
            <li><span class="inline-block w-2 h-2 rounded-full bg-green align-middle mr-1.5"></span> <strong class="text-green">Online</strong> — sincronizou nos últimos 30s. Funcionando normal.</li>
            <li><span class="inline-block w-2 h-2 rounded-full bg-gold align-middle mr-1.5"></span> <strong class="text-gold">Atrasado</strong> — sem sync entre 30s e 5min. Starlink pode estar instável.</li>
            <li><span class="inline-block w-2 h-2 rounded-full bg-red align-middle mr-1.5"></span> <strong class="text-red">Offline</strong> — sem sync há mais de 5min. Ônibus desligado, Starlink caiu ou MikroTik travou.</li>
        </ul>
        <p class="mt-3 pt-2 border-t border-border/50 text-[11px]">
            <strong class="text-ink">Histórico:</strong> Snapshots gravados a cada 5 minutos. Clique em cada ônibus para ver o uptime diário.
            Uptime ≥95% = <span class="text-green font-bold">verde</span>,
            70-95% = <span class="text-gold font-bold">amarelo</span>,
            &lt;70% = <span class="text-red font-bold">vermelho</span>.
        </p>
        <p class="mt-2 text-[11px]">
            <strong class="text-ink">Latência:</strong> Medida via TCP ping ao IP público do MikroTik a cada 5min.
            ≤100ms = <span class="text-green font-bold">Rápida</span>,
            100-300ms = <span class="text-gold font-bold">Média</span>,
            &gt;300ms = <span class="text-red font-bold">Lenta</span>.
            Se o ônibus estiver offline, não mede.
        </p>
    </div>
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let refreshTimer = null;

    function toggleHistory(id) {
        const el = document.getElementById(id);
        const busId = id.replace('history-', '');
        const chevron = document.getElementById('chevron-' + busId);
        if (el.classList.contains('hidden')) {
            el.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            el.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function secondsToText(secs) {
        if (secs === null || secs === undefined) return 'nunca sincronizou';
        if (secs < 60) return secs + 's atrás';
        if (secs < 3600) return Math.floor(secs / 60) + 'min atrás';
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        return h + 'h ' + m + 'min atrás';
    }

    function statusClasses(status) {
        const map = {
            online:  { dot: 'bg-green', text: 'text-green', label: 'ONLINE',   badge: 'bg-green-pale text-green border-green/30' },
            lagging: { dot: 'bg-gold',  text: 'text-gold',  label: 'ATRASADO', badge: 'bg-gold-pale text-gold border-gold/30' },
            offline: { dot: 'bg-red',   text: 'text-red',   label: 'OFFLINE',  badge: 'bg-red-pale text-red border-red/30' },
            unknown: { dot: 'bg-muted', text: 'text-muted', label: 'NUNCA SINCRONIZOU', badge: 'bg-surface text-muted border-border' },
        };
        return map[status] || map.unknown;
    }

    async function refreshNow() {
        try {
            const res = await fetch('{{ route("admin.mikrotik.health.json") }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();

            let online = 0, lagging = 0, offline = 0, totalUsers = 0;

            json.data.forEach(item => {
                if (item.status === 'online') online++;
                else if (item.status === 'lagging') lagging++;
                else offline++;
                totalUsers += item.active_users;

                const row = document.querySelector(`[data-serial="${item.serial}"]`);
                if (!row) return;
                const c = statusClasses(item.status);

                const dot = row.querySelector('[data-dot]');
                dot.className = 'w-3.5 h-3.5 rounded-full ' + c.dot + ' flex-shrink-0 shadow-sm';

                const badge = row.querySelector('[data-badge]');
                badge.className = 'px-2 py-0.5 text-[9px] font-bold rounded border ' + c.badge;
                badge.textContent = c.label;

                const syncText = row.querySelector('[data-sync-text]');
                syncText.className = 'text-sm font-bold ' + c.text;
                syncText.textContent = secondsToText(item.seconds_since_sync);

                // Atualizar latência
                const latencyEl = row.querySelector('[data-latency]');
                if (latencyEl) {
                    const ms = item.latency_ms;
                    if (ms === null || ms === undefined) {
                        latencyEl.className = 'text-sm font-bold text-muted';
                        latencyEl.textContent = '—';
                    } else if (ms <= 100) {
                        latencyEl.className = 'text-sm font-bold text-green';
                        latencyEl.textContent = ms + 'ms';
                    } else if (ms <= 300) {
                        latencyEl.className = 'text-sm font-bold text-gold';
                        latencyEl.textContent = ms + 'ms';
                    } else {
                        latencyEl.className = 'text-sm font-bold text-red';
                        latencyEl.textContent = ms + 'ms';
                    }
                }

                row.querySelector('[data-users]').textContent = item.active_users;
            });

            document.getElementById('sum-online').textContent = online;
            document.getElementById('sum-lagging').textContent = lagging;
            document.getElementById('sum-offline').textContent = offline;
            document.getElementById('sum-users').textContent = totalUsers;
        } catch (e) {
            console.error('Erro ao atualizar saúde:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshTimer = setInterval(refreshNow, 15000);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(refreshTimer);
        } else {
            refreshNow();
            refreshTimer = setInterval(refreshNow, 15000);
        }
    });
</script>
@endsection
