@extends('layouts.admin')

@section('title', 'Vouchers')

@section('breadcrumb')
    <span class="text-muted">›</span>
    <span class="text-green font-semibold">Vouchers</span>
@endsection

@section('page-title', 'Vouchers')

@section('content')
<div class="max-w-8xl mx-auto">

    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-green-dark via-green to-green-light rounded-xl px-5 py-4 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-0.5">Motoristas · Acesso WiFi</p>
            <h1 class="text-xl font-bold text-white">Vouchers</h1>
            <p class="text-xs text-white/70 mt-0.5">Gerencie os vouchers de acesso para motoristas</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.driver-requests.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/15 border border-white/20 text-white font-semibold text-xs rounded-lg hover:bg-white/25 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Pedidos Motoristas
            </a>
            <a href="{{ route('admin.vouchers.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-green font-bold text-xs rounded-lg hover:bg-green-pale transition-colors shadow-card">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Novo Voucher
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-green/20 bg-green-pale px-4 py-3 text-sm text-green font-medium">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-border shadow-card p-4 hover:shadow-hover transition-all">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-pale rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <div>
                    <p class="text-[10px] text-muted font-medium">Total</p>
                    <p class="text-xl font-bold text-ink">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-green/30 shadow-card p-4 hover:shadow-hover transition-all">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-green-pale rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-[10px] text-muted font-medium">Ativos</p>
                    <p class="text-xl font-bold text-green">{{ $stats['active'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-border shadow-card p-4 hover:shadow-hover transition-all">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-surface rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-[10px] text-muted font-medium">Inativos</p>
                    <p class="text-xl font-bold text-ink2">{{ $stats['inactive'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-red/30 shadow-card p-4 hover:shadow-hover transition-all">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-red-pale rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-[10px] text-muted font-medium">Expirados</p>
                    <p class="text-xl font-bold text-red">{{ $stats['expired'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-blue/30 shadow-card p-4 hover:shadow-hover transition-all">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-pale rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <div>
                    <p class="text-[10px] text-muted font-medium">Ilimitados</p>
                    <p class="text-xl font-bold text-blue">{{ $stats['unlimited'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-border shadow-card p-4 mb-4">
        <form method="GET" action="{{ route('admin.vouchers.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nome, código ou telefone..."
                       class="w-full pl-9 pr-4 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
            </div>
            <select name="status" class="px-3 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
                <option value="">Todos os Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Ativos</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inativos</option>
                <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expirados</option>
            </select>
            <select name="type" class="px-3 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
                <option value="">Todos os Tipos</option>
                <option value="limited" {{ request('type') == 'limited' ? 'selected' : '' }}>Limitado</option>
                <option value="unlimited" {{ request('type') == 'unlimited' ? 'selected' : '' }}>Ilimitado</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-green hover:bg-green-light text-white font-semibold text-xs rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filtrar
                </button>
                @if(request()->hasAny(['search', 'status', 'type']))
                <a href="{{ route('admin.vouchers.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-surface border border-border text-ink2 font-semibold text-xs rounded-lg hover:bg-border transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Limpar
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-xl border border-border shadow-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-border px-5 py-3">
            <h3 class="text-sm font-bold text-ink">Lista de Vouchers</h3>
            <div class="flex items-center gap-2">
                <form id="bulkDeleteVouchersForm" method="POST" action="{{ route('admin.vouchers.bulk-destroy') }}" onsubmit="return confirmBulkDeleteVouchers()">
                    @csrf
                    @method('DELETE')
                    <button id="bulkDeleteVouchersButton" type="submit" disabled class="px-3 py-1.5 bg-red-pale text-red rounded-lg text-[10px] font-bold opacity-50 cursor-not-allowed transition-colors">
                        Excluir selecionados (0)
                    </button>
                </form>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-green/10 text-green px-2 py-0.5 rounded">{{ $vouchers->total() }} registros</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-surface">
                        <th class="px-5 py-2.5 text-center w-10">
                            <input type="checkbox" id="selectAllVouchers" class="rounded border-border accent-green" onchange="toggleAllVouchers(this)">
                        </th>
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-muted uppercase tracking-wider">Código</th>
                        <th class="px-5 py-2.5 text-left text-[10px] font-bold text-muted uppercase tracking-wider">Motorista</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-muted uppercase tracking-wider">Tipo</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-muted uppercase tracking-wider">Uso Diário</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-muted uppercase tracking-wider">Expiração</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-muted uppercase tracking-wider">Status</th>
                        <th class="px-5 py-2.5 text-center text-[10px] font-bold text-muted uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($vouchers as $voucher)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-5 py-3.5 text-center">
                            <input type="checkbox" class="voucher-checkbox rounded border-border accent-green" value="{{ $voucher->id }}" onchange="updateBulkDeleteVouchersState()">
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg {{ $voucher->voucher_type === 'unlimited' ? 'bg-blue-pale' : 'bg-green-pale' }} flex items-center justify-center flex-shrink-0">
                                    @if($voucher->voucher_type === 'unlimited')
                                        <svg class="w-4 h-4 text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    @else
                                        <svg class="w-4 h-4 text-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <button type="button" class="font-mono text-xs font-bold text-green hover:text-green-dark transition-colors" onclick="copyCode('{{ $voucher->code }}', this)" title="Clique para copiar">
                                        {{ $voucher->code }}
                                    </button>
                                    <p class="text-[10px] text-muted">{{ $voucher->created_at->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-xs font-semibold text-ink">{{ $voucher->driver_name }}</p>
                            @if($voucher->driver_phone)
                                <p class="text-[10px] text-muted flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    {{ $voucher->driver_phone }}
                                </p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if($voucher->voucher_type === 'unlimited')
                                <span class="text-[9px] font-bold uppercase tracking-wider bg-blue/10 text-blue px-1.5 py-0.5 rounded">Ilimitado</span>
                            @else
                                <span class="text-[9px] font-bold uppercase tracking-wider bg-green/10 text-green px-1.5 py-0.5 rounded">{{ $voucher->daily_hours }}h/dia</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if($voucher->voucher_type === 'unlimited')
                                <span class="text-[10px] text-muted">—</span>
                            @else
                                @php
                                    $pct = $voucher->daily_hours > 0 ? ($voucher->daily_hours_used / $voucher->daily_hours) * 100 : 0;
                                    $barColor = $pct >= 100 ? 'bg-red' : ($pct >= 75 ? 'bg-gold' : 'bg-green');
                                @endphp
                                <div class="flex flex-col items-center gap-1">
                                    <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="{{ $barColor }} h-full rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-medium {{ $pct >= 100 ? 'text-red' : 'text-muted' }}">{{ $voucher->daily_hours_used }}h / {{ $voucher->daily_hours }}h</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if($voucher->expires_at)
                                @if($voucher->expires_at->isPast())
                                    <span class="text-[9px] font-bold uppercase tracking-wider bg-red/10 text-red px-1.5 py-0.5 rounded">Expirado</span>
                                @else
                                    <p class="text-xs font-medium text-ink2">{{ $voucher->expires_at->format('d/m/Y') }}</p>
                                    <p class="text-[10px] text-muted">{{ $voucher->expires_at->diffForHumans() }}</p>
                                @endif
                            @else
                                <span class="text-[10px] text-muted">Sem expiração</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if($voucher->is_active && (!$voucher->expires_at || !$voucher->expires_at->isPast()))
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider bg-green/10 text-green px-2 py-0.5 rounded">
                                    <span class="w-1.5 h-1.5 bg-green rounded-full animate-pulse"></span>Ativo
                                </span>
                            @elseif($voucher->expires_at && $voucher->expires_at->isPast())
                                <span class="text-[9px] font-bold uppercase tracking-wider bg-red/10 text-red px-2 py-0.5 rounded">Expirado</span>
                            @else
                                <span class="text-[9px] font-bold uppercase tracking-wider bg-surface text-muted px-2 py-0.5 rounded border border-border">Inativo</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="p-1.5 rounded-lg text-blue hover:bg-blue-pale transition-colors" title="Editar">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form action="{{ route('admin.vouchers.toggle', $voucher) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg {{ $voucher->is_active ? 'text-gold hover:bg-gold-pale' : 'text-green hover:bg-green-pale' }} transition-colors" title="{{ $voucher->is_active ? 'Desativar' : 'Ativar' }}">
                                        @if($voucher->is_active)
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @else
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                    </button>
                                </form>
                                @if($voucher->voucher_type === 'limited' && $voucher->daily_hours_used > 0)
                                <form action="{{ route('admin.vouchers.reset', $voucher) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 rounded-lg text-blue hover:bg-blue-pale transition-colors" title="Resetar uso diário" onclick="return confirm('Resetar o uso diário deste voucher?')">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('admin.vouchers.destroy', $voucher) }}" method="POST" class="inline" onsubmit="return confirm('Excluir este voucher?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-red hover:bg-red-pale transition-colors" title="Excluir">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center">
                            <div class="w-12 h-12 bg-surface rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            @if(request()->hasAny(['search', 'status', 'type']))
                                <p class="text-sm font-medium text-ink2 mb-1">Nenhum voucher encontrado</p>
                                <a href="{{ route('admin.vouchers.index') }}" class="inline-flex items-center gap-1.5 text-xs text-green font-semibold hover:underline">Limpar filtros</a>
                            @else
                                <p class="text-sm font-medium text-ink2 mb-1">Nenhum voucher cadastrado</p>
                                <a href="{{ route('admin.vouchers.create') }}" class="inline-flex items-center gap-1.5 bg-green text-white font-semibold text-xs px-3 py-1.5 rounded-lg hover:bg-green-light transition-colors mt-2">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                    Criar Primeiro Voucher
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($vouchers->hasPages())
        <div class="px-5 py-3 border-t border-border flex items-center justify-between">
            <p class="text-[11px] text-muted">{{ $vouchers->total() }} registros</p>
            {{ $vouchers->links() }}
        </div>
        @endif
    </div>

    <script>
        function updateBulkDeleteVouchersState() {
            const selected = Array.from(document.querySelectorAll('.voucher-checkbox:checked'));
            const all = Array.from(document.querySelectorAll('.voucher-checkbox'));
            const form = document.getElementById('bulkDeleteVouchersForm');
            const button = document.getElementById('bulkDeleteVouchersButton');
            const selectAll = document.getElementById('selectAllVouchers');

            form.querySelectorAll('input[name="voucher_ids[]"]').forEach(input => input.remove());
            selected.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'voucher_ids[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });

            button.textContent = `Excluir selecionados (${selected.length})`;
            button.disabled = selected.length === 0;
            button.classList.toggle('opacity-50', selected.length === 0);
            button.classList.toggle('cursor-not-allowed', selected.length === 0);
            button.classList.toggle('hover:bg-red/10', selected.length > 0);

            if (selectAll) {
                selectAll.checked = all.length > 0 && selected.length === all.length;
                selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
            }
        }

        function toggleAllVouchers(source) {
            document.querySelectorAll('.voucher-checkbox').forEach(checkbox => {
                checkbox.checked = source.checked;
            });
            updateBulkDeleteVouchersState();
        }

        function confirmBulkDeleteVouchers() {
            const count = document.querySelectorAll('.voucher-checkbox:checked').length;
            if (count === 0) return false;
            return confirm(`Excluir ${count} voucher(s) selecionado(s)?`);
        }

        function copyCode(code, el) {
            navigator.clipboard.writeText(code).then(() => {
                const original = el.textContent;
                el.textContent = 'Copiado!';
                el.classList.add('text-green-dark');
                setTimeout(() => { el.textContent = original; el.classList.remove('text-green-dark'); }, 1500);
            });
        }
    </script>
</div>
@endsection
