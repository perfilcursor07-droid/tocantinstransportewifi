@extends('layouts.admin')

@section('title', 'Relatórios')

@section('breadcrumb')
    <span class="text-muted">›</span>
    <span class="text-green font-semibold">Relatórios</span>
@endsection

@section('page-title', 'Relatórios')

@push('scripts')
    <script src="{{ asset('js/reports.js') }}"></script>
@endpush

@section('content')
    @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-green/20 bg-green-pale px-4 py-3 text-sm text-green font-medium">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-red/20 bg-red-pale px-4 py-3 text-sm text-red font-medium">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Filtros Avançados -->
    <div class="bg-white rounded-xl shadow-card border border-border mb-6 overflow-hidden">
        <button type="button" id="toggleAdvancedFilters"
                class="w-full flex items-center justify-between px-5 py-3 hover:bg-surface transition-colors">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                <span class="text-sm font-bold text-ink">Filtros Avançados</span>
            </div>
            <svg id="filterChevron" class="w-4 h-4 text-muted transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="advancedFiltersPanel" class="border-t border-border p-5 hidden">
        <form method="GET" action="{{ route('admin.reports') }}">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div>
                    <label class="block text-[11px] font-semibold text-ink2 uppercase tracking-wider mb-1.5">Data e Hora Inicial</label>
                    <input type="datetime-local" name="start_date" value="{{ $startDate }}"
                           class="w-full px-3 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-ink2 uppercase tracking-wider mb-1.5">Data e Hora Final</label>
                    <input type="datetime-local" name="end_date" value="{{ $endDate }}"
                           class="w-full px-3 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-ink2 uppercase tracking-wider mb-1.5">Status Pagamento</label>
                    <select name="payment_status" class="w-full px-3 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
                        <option value="all" {{ $paymentStatus == 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="pending" {{ $paymentStatus == 'pending' ? 'selected' : '' }}>Pendente</option>
                        <option value="completed" {{ $paymentStatus == 'completed' ? 'selected' : '' }}>Pago</option>
                        <option value="failed" {{ $paymentStatus == 'failed' ? 'selected' : '' }}>Falhou</option>
                        <option value="cancelled" {{ $paymentStatus == 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-ink2 uppercase tracking-wider mb-1.5">Ônibus</label>
                    <select name="bus" class="w-full px-3 py-2 text-sm text-ink bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-green/30 focus:border-green transition-all">
                        <option value="all" {{ ($busFilter ?? 'all') == 'all' ? 'selected' : '' }}>Todos os Ônibus</option>
                        @foreach($busList as $bus)
                            <option value="{{ $bus->mikrotik_serial }}" {{ ($busFilter ?? '') == $bus->mikrotik_serial ? 'selected' : '' }}>
                                {{ $bus->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 bg-green hover:bg-green-light text-white font-semibold text-xs px-4 py-2 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Aplicar Filtros
                </button>
                <a href="{{ route('admin.reports') }}" class="inline-flex items-center gap-1.5 bg-surface border border-border text-ink2 font-semibold text-xs px-4 py-2 rounded-lg hover:bg-border transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Limpar
                </a>
            </div>
        </form>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">

        <div class="bg-white rounded-xl shadow-card border border-border p-4 hover:shadow-hover transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 bg-green-pale rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-green/10 text-green px-1.5 py-0.5 rounded">Pagos</span>
            </div>
            <p class="text-xl font-bold text-ink">R$ {{ number_format($stats['total_revenue'], 2, ',', '.') }}</p>
            <p class="text-[11px] text-muted mt-0.5">Ticket médio: R$ {{ number_format($stats['avg_payment'], 2, ',', '.') }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-card border border-border p-4 hover:shadow-hover transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 bg-gold-pale rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-gold/10 text-gold px-1.5 py-0.5 rounded">Pendente</span>
            </div>
            <p class="text-xl font-bold text-ink">R$ {{ number_format($stats['pending_payments'], 2, ',', '.') }}</p>
            <p class="text-[11px] text-muted mt-0.5">Aguardando pagamento</p>
        </div>

        <div class="bg-white rounded-xl shadow-card border border-border p-4 hover:shadow-hover transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 bg-blue-pale rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-blue/10 text-blue px-1.5 py-0.5 rounded">Total</span>
            </div>
            <p class="text-xl font-bold text-ink">{{ $stats['total_payments'] }}</p>
            <div class="flex gap-1.5 mt-2">
                <span class="text-[9px] font-bold bg-green/10 text-green px-1.5 py-0.5 rounded">{{ $stats['completed_payments_count'] }} pagos</span>
                <span class="text-[9px] font-bold bg-gold/10 text-gold px-1.5 py-0.5 rounded">{{ $stats['pending_payments_count'] }} pend.</span>
                <span class="text-[9px] font-bold bg-red/10 text-red px-1.5 py-0.5 rounded">{{ $stats['failed_payments_count'] }} falhos</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card border border-border p-4 hover:shadow-hover transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 bg-green-pale rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-green/10 text-green px-1.5 py-0.5 rounded">Usuários</span>
            </div>
            <p class="text-xl font-bold text-ink">{{ $stats['total_users'] }}</p>
            <p class="text-[11px] text-muted mt-0.5">{{ $stats['connected_users'] }} conectados agora</p>
        </div>

        <div class="bg-white rounded-xl shadow-card border border-border p-4 hover:shadow-hover transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="w-9 h-9 bg-blue-pale rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-blue/10 text-blue px-1.5 py-0.5 rounded">Sessões</span>
            </div>
            <p class="text-xl font-bold text-ink">{{ $stats['active_sessions'] }}</p>
            <p class="text-[11px] text-muted mt-0.5">No período selecionado</p>
        </div>
    </div>

    <!-- Receita por Ônibus -->
    @if($revenueByBus->count() > 0)
    <div class="bg-white rounded-xl shadow-card border border-border mb-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-border px-5 py-3">
            <h3 class="text-sm font-bold text-ink">Receita por Ônibus</h3>
            <span class="text-[11px] text-muted">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y H:i') }} — {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y H:i') }}</span>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @php $maxRevenue = $revenueByBus->max('total') ?: 1; @endphp
                @foreach($revenueByBus as $bus)
                <div class="bg-surface rounded-xl p-4 border border-border hover:shadow-hover transition-all">
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-green-dark to-green rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-ink truncate">{{ $bus->bus_name }}</p>
                            <p class="text-[10px] text-muted font-mono">{{ $bus->bus_id }}</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-green">R$ {{ number_format($bus->total, 2, ',', '.') }}</p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-[10px] text-muted">{{ $bus->count }} pagamentos</span>
                        <span class="text-[10px] font-semibold text-ink2">{{ number_format(($bus->total / $maxRevenue) * 100, 0) }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mt-1.5">
                        <div class="h-full bg-green rounded-full" style="width: {{ ($bus->total / $maxRevenue) * 100 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Gráficos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-card border border-border p-5 hover:shadow-hover transition-all">
            <div class="flex justify-between items-center border-b border-border pb-3 mb-4">
                <h3 class="text-sm font-bold text-ink">Receita por Dia</h3>
                <span class="text-[11px] text-muted">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y H:i') }} — {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y H:i') }}</span>
            </div>
            <div class="relative h-64"><canvas id="revenueChart" class="w-full h-full"></canvas></div>
        </div>
        <div class="bg-white rounded-xl shadow-card border border-border p-5 hover:shadow-hover transition-all">
            <div class="flex justify-between items-center border-b border-border pb-3 mb-4">
                <h3 class="text-sm font-bold text-ink">Pagamentos por Status</h3>
            </div>
            <div class="relative h-64"><canvas id="paymentsStatusChart" class="w-full h-full"></canvas></div>
        </div>
    </div>

    <!-- Abas de Conteúdo -->
    <div class="bg-white rounded-xl shadow-card border border-border overflow-hidden">
        <!-- Navegação das Abas -->
        <div class="flex border-b border-border">
            <button onclick="showTab('payments')" id="tab-payments"
                    class="tab-button flex-1 px-5 py-3 text-xs font-bold text-green border-b-2 border-green bg-green-pale transition-colors">
                Pagamentos ({{ $payments->total() }})
            </button>
            @if($canViewUsersTab)
            <button onclick="showTab('users')" id="tab-users"
                    class="tab-button flex-1 px-5 py-3 text-xs font-bold text-muted border-b-2 border-transparent hover:text-ink hover:bg-surface transition-colors">
                Usuários ({{ $users->total() }})
            </button>
            @endif
        </div>

        <!-- Aba de Pagamentos -->
        <div id="content-payments" class="tab-content p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-ink">Lista de Pagamentos</h3>
                <div class="flex gap-2">
                    @if(auth()->user()?->role === 'admin')
                    <form id="bulk-delete-form" method="POST" action="{{ route('admin.reports.payments.bulk-destroy') }}" onsubmit="return confirmBulkDelete();">
                        @csrf
                        @method('DELETE')
                        <button id="bulk-delete-button" type="submit" disabled
                                class="inline-flex items-center gap-1.5 bg-red-pale text-red font-semibold text-xs px-3 py-1.5 rounded-lg opacity-50 cursor-not-allowed transition-all">
                            Excluir selecionados (0)
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('admin.reports.export', ['type' => 'payments', 'format' => 'csv', 'start_date' => $startDate, 'end_date' => $endDate, 'payment_status' => $paymentStatus]) }}"
                       class="inline-flex items-center gap-1.5 bg-green hover:bg-green-light text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Exportar CSV
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-border bg-surface">
                            @if(auth()->user()?->role === 'admin')
                            <th class="py-2.5 px-3 w-8"><input type="checkbox" id="select-all-payments" class="rounded border-border accent-green"></th>
                            @endif
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">ID</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Usuário</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Valor</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Tipo</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Status</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Pago em</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Criado</th>
                            @if(auth()->user()?->role === 'admin')
                            <th class="py-2.5 px-3"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($payments as $payment)
                        <tr class="hover:bg-surface transition-colors">
                            @if(auth()->user()?->role === 'admin')
                            <td class="py-3 px-3"><input type="checkbox" class="payment-checkbox rounded border-border accent-green" value="{{ $payment->id }}"></td>
                            @endif
                            <td class="py-3 px-3 text-xs text-muted font-mono">#{{ $payment->id }}</td>
                            <td class="py-3 px-3">
                                <p class="text-xs font-medium text-ink">{{ $payment->user->name ?? 'N/A' }}</p>
                                <p class="text-[10px] text-muted">{{ $payment->user->email ?? 'N/A' }}</p>
                            </td>
                            <td class="py-3 px-3 text-xs font-bold text-green">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                            <td class="py-3 px-3">
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded {{ $payment->payment_type === 'pix' ? 'bg-blue/10 text-blue' : 'bg-green/10 text-green' }}">
                                    {{ $payment->payment_type === 'pix' ? 'PIX' : 'Cartão' }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $stMap = [
                                        'completed' => 'bg-green/10 text-green',
                                        'pending'   => 'bg-gold/10 text-gold',
                                        'failed'    => 'bg-red/10 text-red',
                                    ];
                                    $stLabel = ['completed'=>'Pago','pending'=>'Pendente','failed'=>'Falhou'];
                                @endphp
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded {{ $stMap[$payment->status] ?? 'bg-surface text-muted' }}">
                                    {{ $stLabel[$payment->status] ?? ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-xs text-ink2">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : '—' }}</td>
                            <td class="py-3 px-3 text-xs text-muted">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                            @if(auth()->user()?->role === 'admin')
                            <td class="py-3 px-3">
                                <form method="POST" action="{{ route('admin.reports.payments.destroy', $payment) }}" onsubmit="return confirm('Excluir este registro e o usuário vinculado?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[10px] font-bold bg-red-pale text-red px-2 py-1 rounded-lg hover:bg-red/10 transition-colors">Excluir</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->role === 'admin' ? 9 : 7 }}" class="py-10 text-center">
                                <div class="w-10 h-10 bg-surface rounded-full flex items-center justify-center mx-auto mb-2">
                                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                </div>
                                <p class="text-sm text-muted">Nenhum pagamento encontrado no período.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($payments->hasPages())
            <div class="mt-5 flex items-center justify-between">
                <p class="text-[11px] text-muted">Mostrando {{ $payments->firstItem() }}–{{ $payments->lastItem() }} de {{ $payments->total() }}</p>
                <div class="flex items-center gap-1.5">
                    @if($payments->onFirstPage())
                        <span class="px-3 py-1.5 rounded-lg border border-border bg-surface text-muted text-xs">Anterior</span>
                    @else
                        <a href="{{ $payments->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-border bg-white text-ink2 hover:bg-surface text-xs transition-colors">Anterior</a>
                    @endif
                    <span class="px-3 py-1.5 rounded-lg border border-green/30 bg-green-pale text-green text-xs font-semibold">
                        {{ $payments->currentPage() }} / {{ $payments->lastPage() }}
                    </span>
                    @if($payments->hasMorePages())
                        <a href="{{ $payments->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-border bg-white text-ink2 hover:bg-surface text-xs transition-colors">Próxima</a>
                    @else
                        <span class="px-3 py-1.5 rounded-lg border border-border bg-surface text-muted text-xs">Próxima</span>
                    @endif
                </div>
            </div>
            @endif
        </div>

        @if($canViewUsersTab)
        <!-- Aba de Usuários -->
        <div id="content-users" class="tab-content p-5 hidden">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-ink">Lista de Usuários</h3>
                <a href="{{ route('admin.reports.export', ['type' => 'users', 'format' => 'csv', 'start_date' => $startDate, 'end_date' => $endDate, 'user_status' => $userStatus]) }}"
                   class="inline-flex items-center gap-1.5 bg-green hover:bg-green-light text-white font-semibold text-xs px-3 py-1.5 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exportar CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-border bg-surface">
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">ID</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Usuário</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">MAC</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Status</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Conectado</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Expira</th>
                            <th class="text-left text-[10px] font-bold text-muted uppercase tracking-wider py-2.5 px-3">Cadastro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($users as $user)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="py-3 px-3 text-xs text-muted font-mono">#{{ $user->id }}</td>
                            <td class="py-3 px-3">
                                <p class="text-xs font-medium text-ink">{{ $user->name ?? 'N/A' }}</p>
                                <p class="text-[10px] text-muted">{{ $user->email ?? ($user->phone ?? 'N/A') }}</p>
                            </td>
                            <td class="py-3 px-3">
                                @if($user->mac_address)
                                    <span class="text-xs font-mono text-ink2">{{ $user->mac_address }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $uMap = ['connected'=>'bg-green/10 text-green','active'=>'bg-blue/10 text-blue','temp_bypass'=>'bg-gold/10 text-gold'];
                                    $uLabel = ['connected'=>'Conectado','active'=>'Ativo','temp_bypass'=>'Bypass'];
                                @endphp
                                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded {{ $uMap[$user->status] ?? 'bg-surface text-muted' }}">
                                    {{ $uLabel[$user->status] ?? ucfirst($user->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-xs text-ink2">{{ $user->connected_at ? $user->connected_at->format('d/m/Y H:i') : '—' }}</td>
                            <td class="py-3 px-3 text-xs text-ink2">{{ $user->expires_at ? $user->expires_at->format('d/m/Y H:i') : '—' }}</td>
                            <td class="py-3 px-3 text-xs text-muted">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center">
                                <div class="w-10 h-10 bg-surface rounded-full flex items-center justify-center mx-auto mb-2">
                                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <p class="text-sm text-muted">Nenhum usuário encontrado no período.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($users->hasPages())
            <div class="mt-5 flex items-center justify-between">
                <p class="text-[11px] text-muted">Mostrando {{ $users->firstItem() }}–{{ $users->lastItem() }} de {{ $users->total() }}</p>
                <div class="flex items-center gap-1.5">
                    @if($users->onFirstPage())
                        <span class="px-3 py-1.5 rounded-lg border border-border bg-surface text-muted text-xs">Anterior</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-border bg-white text-ink2 hover:bg-surface text-xs transition-colors">Anterior</a>
                    @endif
                    <span class="px-3 py-1.5 rounded-lg border border-green/30 bg-green-pale text-green text-xs font-semibold">
                        {{ $users->currentPage() }} / {{ $users->lastPage() }}
                    </span>
                    @if($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-border bg-white text-ink2 hover:bg-surface text-xs transition-colors">Próxima</a>
                    @else
                        <span class="px-3 py-1.5 rounded-lg border border-border bg-surface text-muted text-xs">Próxima</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    <!-- Scripts específicos da página -->
    <script>
        // Toggle filtros avançados
        (function() {
            const btn = document.getElementById('toggleAdvancedFilters');
            const panel = document.getElementById('advancedFiltersPanel');
            const chevron = document.getElementById('filterChevron');
            const hasFilters = true; // sempre aberto
            if (hasFilters && panel) { panel.classList.remove('hidden'); chevron.classList.add('rotate-180'); }
            btn?.addEventListener('click', () => {
                panel.classList.toggle('hidden');
                chevron.classList.toggle('rotate-180');
            });
        })();

        // Função para mostrar/esconder abas
        function showTab(tabName) {
            // Esconder todos os conteúdos das abas
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remover classe ativa de todos os botões
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('text-green', 'border-green', 'bg-green-pale');
                button.classList.add('text-muted', 'border-transparent');
            });
            
            // Mostrar conteúdo da aba selecionada
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Ativar botão da aba selecionada
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.remove('text-muted', 'border-transparent');
            activeButton.classList.add('text-green', 'border-green', 'bg-green-pale');
        }

        function updateBulkDeleteState() {
            const checkboxes = Array.from(document.querySelectorAll('.payment-checkbox'));
            const selected = checkboxes.filter(cb => cb.checked);
            const button = document.getElementById('bulk-delete-button');
            const form = document.getElementById('bulk-delete-form');

            if (!button || !form) {
                return;
            }

            form.querySelectorAll('input[name="payment_ids[]"]').forEach(input => input.remove());

            selected.forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'payment_ids[]';
                hidden.value = cb.value;
                form.appendChild(hidden);
            });

            button.textContent = `Excluir selecionados (${selected.length})`;
            button.disabled = selected.length === 0;

            if (button.disabled) {
                button.classList.add('opacity-50', 'cursor-not-allowed');
                button.classList.remove('hover:bg-red-700');
            } else {
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                button.classList.add('hover:bg-red-700');
            }
        }

        function confirmBulkDelete() {
            const selectedCount = document.querySelectorAll('.payment-checkbox:checked').length;
            if (selectedCount === 0) {
                return false;
            }

            return confirm(`Tem certeza que deseja excluir ${selectedCount} registro(s)? Esta ação remove também os usuários vinculados e pagamentos relacionados.`);
        }

        // Função para inicializar gráficos
        function initializeCharts() {
            // Verificar se os elementos existem antes de criar os gráficos
            const revenueCanvas = document.getElementById('revenueChart');
            const statusCanvas = document.getElementById('paymentsStatusChart');

            if (!revenueCanvas || !statusCanvas) {
                console.log('Canvas elements not found, retrying...');
                setTimeout(initializeCharts, 100);
                return;
            }

            try {
                // Gráfico de Receita por Dia
                const revenueCtx = revenueCanvas.getContext('2d');
                
                // Destruir gráfico existente se houver
                if (window.revenueChart instanceof Chart) {
                    window.revenueChart.destroy();
                }

                window.revenueChart = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: {!! json_encode($charts['revenue_by_day']->pluck('date')->map(function($date) { return \Carbon\Carbon::parse($date)->format('d/m'); })) !!},
                        datasets: [{
                            label: 'Receita (R$)',
                            data: {!! json_encode($charts['revenue_by_day']->pluck('total')) !!},
                            borderColor: '#00A335',
                            backgroundColor: 'rgba(0, 163, 53, 0.08)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#00A335',
                            pointBorderColor: '#00A335',
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Receita: R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });

                // Gráfico de Pagamentos por Status
                const statusCtx = statusCanvas.getContext('2d');
                
                // Destruir gráfico existente se houver
                if (window.statusChart instanceof Chart) {
                    window.statusChart.destroy();
                }

                window.statusChart = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: {!! json_encode($charts['payments_by_status']->pluck('status')->map(function($status) { 
                            return $status === 'completed' ? 'Pago' : ($status === 'pending' ? 'Pendente' : ($status === 'failed' ? 'Falhou' : 'Cancelado')); 
                        })) !!},
                        datasets: [{
                            data: {!! json_encode($charts['payments_by_status']->pluck('count')) !!},
                            backgroundColor: [
                                '#00A335', // green - completed
                                '#E6A817', // gold - pending  
                                '#D32F2F', // red - failed
                                '#888888'  // muted - cancelled
                            ],
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverBorderWidth: 4,
                            hoverBorderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        },
                        animation: {
                            animateRotate: true,
                            duration: 1000
                        }
                    }
                });

                console.log('Charts initialized successfully');
            } catch (error) {
                console.error('Error initializing charts:', error);
            }
        }

        // Inicializar primeira aba como ativa e gráficos
        document.addEventListener('DOMContentLoaded', function() {
            showTab('payments');
            // Aguardar um pouco para garantir que o DOM está completamente carregado
            setTimeout(initializeCharts, 500);

            const selectAll = document.getElementById('select-all-payments');
            const checkboxes = Array.from(document.querySelectorAll('.payment-checkbox'));

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    updateBulkDeleteState();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (selectAll) {
                        selectAll.checked = checkboxes.length > 0 && checkboxes.every(item => item.checked);
                    }
                    updateBulkDeleteState();
                });
            });

            updateBulkDeleteState();
        });

        // Reinicializar gráficos se a janela for redimensionada
        window.addEventListener('resize', function() {
            clearTimeout(window.resizeTimeout);
            window.resizeTimeout = setTimeout(function() {
                if (window.revenueChart instanceof Chart) {
                    window.revenueChart.resize();
                }
                if (window.statusChart instanceof Chart) {
                    window.statusChart.resize();
                }
            }, 100);
        });
    </script>
@endsection