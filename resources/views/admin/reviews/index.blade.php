@extends('layouts.admin')

@section('title', 'Avaliacoes')

@section('breadcrumb')
    <span class="mx-2">/</span>
    <span class="text-tocantins-green font-medium">Avaliacoes</span>
@endsection

@section('page-title', 'Lista de Avaliacoes')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-2">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reviews.index') }}" class="px-4 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.reviews.index') ? 'bg-emerald-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100' }}">Lista</a>
            <a href="{{ route('admin.reviews.settings') }}" class="px-4 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('admin.reviews.settings*') ? 'bg-emerald-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100' }}">Configuracoes</a>
            {{-- 🧩 Numero SEPARADO so para disparo de avaliacao (nao usar o numero do PIX) --}}
            <a href="{{ route('admin.whatsapp.review.connect') }}" target="_blank" rel="noopener"
               class="px-4 py-2 rounded-xl text-sm font-medium text-white shadow inline-flex items-center gap-2
                      {{ \App\Models\WhatsappSetting::isReviewConnected() ? 'bg-emerald-600' : 'bg-amber-500 hover:bg-amber-600' }}">
                <span>📱</span>
                <span>WhatsApp Avaliacao</span>
                @if(\App\Models\WhatsappSetting::isReviewConnected())
                    <span class="text-[11px] bg-white/20 px-2 py-0.5 rounded-full">Conectado</span>
                @else
                    <span class="text-[11px] bg-white/20 px-2 py-0.5 rounded-full">Conectar</span>
                @endif
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-2xl">
        {{ session('success') }}
    </div>
    @endif

    {{-- Filtros (no topo para acesso rápido) --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
        @php
            $hasActiveFilters = request()->hasAny(['status','rating','phone','date_from','date_to','answered_from','answered_to'])
                && collect(request()->only(['status','rating','phone','date_from','date_to','answered_from','answered_to']))->filter()->isNotEmpty();
            $statusLabels = [
                'answered' => 'Respondidas',
                'pending' => 'Aguardando resposta',
                'failed' => 'Envio com falha',
                'not_sent' => 'Não enviadas',
            ];
        @endphp

        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-gray-800">Filtros</h3>
                @if($hasActiveFilters)
                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] font-semibold rounded-full border border-emerald-200">Ativo</span>
                @endif
            </div>
            <button type="button" onclick="document.getElementById('filtersAdvanced').classList.toggle('hidden'); document.getElementById('filtersChevron').classList.toggle('rotate-180')" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                Mais filtros
                <svg id="filtersChevron" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        <form method="GET" id="reviewsFilterForm">
            {{-- Filtros principais (sempre visíveis): Data viagem + Status + Telefone --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Data da viagem (de)</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Data da viagem (até)</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                        <option value="">Todos</option>
                        <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>Respondidas</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Aguardando resposta</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Envio com falha</option>
                        <option value="not_sent" {{ request('status') === 'not_sent' ? 'selected' : '' }}>Não enviadas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Telefone</label>
                    <input type="text" name="phone" value="{{ request('phone') }}" placeholder="63999..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2 px-4 rounded-xl font-semibold text-sm transition-colors flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Filtrar
                    </button>
                    @if($hasActiveFilters)
                    <a href="{{ route('admin.reviews.index') }}" title="Limpar filtros"
                       class="bg-gray-100 hover:bg-gray-200 text-gray-600 py-2 px-3 rounded-xl text-sm transition-colors flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Filtros avançados (colapsáveis): Nota + Resposta de/até --}}
            <div id="filtersAdvanced" class="{{ $hasActiveFilters && (request()->hasAny(['rating','answered_from','answered_to'])) ? '' : 'hidden' }} mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nota</label>
                    <select name="rating" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                        <option value="">Todas as notas</option>
                        @for($rating = 1; $rating <= 5; $rating++)
                        <option value="{{ $rating }}" {{ (string) request('rating') === (string) $rating ? 'selected' : '' }}>{{ $rating }} estrela{{ $rating > 1 ? 's' : '' }} ★</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Respondida (de)</label>
                    <input type="date" name="answered_from" value="{{ request('answered_from') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Respondida (até)</label>
                    <input type="date" name="answered_to" value="{{ request('answered_to') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                </div>
            </div>

            {{-- Atalhos rápidos --}}
            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mr-1">Atalhos:</span>
                <button type="button" onclick="setQuickFilter('today')" class="px-3 py-1 bg-gray-50 hover:bg-emerald-50 hover:text-emerald-700 text-gray-600 text-xs rounded-lg border border-gray-200 transition-colors">Hoje</button>
                <button type="button" onclick="setQuickFilter('yesterday')" class="px-3 py-1 bg-gray-50 hover:bg-emerald-50 hover:text-emerald-700 text-gray-600 text-xs rounded-lg border border-gray-200 transition-colors">Ontem</button>
                <button type="button" onclick="setQuickFilter('week')" class="px-3 py-1 bg-gray-50 hover:bg-emerald-50 hover:text-emerald-700 text-gray-600 text-xs rounded-lg border border-gray-200 transition-colors">Últimos 7 dias</button>
                <button type="button" onclick="setQuickFilter('month')" class="px-3 py-1 bg-gray-50 hover:bg-emerald-50 hover:text-emerald-700 text-gray-600 text-xs rounded-lg border border-gray-200 transition-colors">Últimos 30 dias</button>
                <span class="w-px h-4 bg-gray-200 mx-1"></span>
                <button type="button" onclick="setStatusFilter('pending')" class="px-3 py-1 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 text-xs rounded-lg border border-yellow-200 transition-colors">Aguardando</button>
                <button type="button" onclick="setStatusFilter('answered')" class="px-3 py-1 bg-green-50 hover:bg-green-100 text-green-700 text-xs rounded-lg border border-green-200 transition-colors">Respondidas</button>
                <button type="button" onclick="setStatusFilter('failed')" class="px-3 py-1 bg-red-50 hover:bg-red-100 text-red-700 text-xs rounded-lg border border-red-200 transition-colors">Com falha</button>
            </div>
        </form>

        @if($hasActiveFilters)
        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold text-gray-500">Filtros aplicados:</span>
            @if(request('date_from') || request('date_to'))
            <span class="px-2 py-1 bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-200">
                Viagem: {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d/m/Y') : '...' }} → {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d/m/Y') : '...' }}
            </span>
            @endif
            @if(request('status'))
            <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                Status: {{ $statusLabels[request('status')] ?? request('status') }}
            </span>
            @endif
            @if(request('rating'))
            <span class="px-2 py-1 bg-amber-50 text-amber-700 rounded-lg border border-amber-200">
                Nota: {{ request('rating') }}★
            </span>
            @endif
            @if(request('phone'))
            <span class="px-2 py-1 bg-gray-50 text-gray-700 rounded-lg border border-gray-200">
                Telefone: {{ request('phone') }}
            </span>
            @endif
            @if(request('answered_from') || request('answered_to'))
            <span class="px-2 py-1 bg-purple-50 text-purple-700 rounded-lg border border-purple-200">
                Respondida: {{ request('answered_from') ? \Carbon\Carbon::parse(request('answered_from'))->format('d/m/Y') : '...' }} → {{ request('answered_to') ? \Carbon\Carbon::parse(request('answered_to'))->format('d/m/Y') : '...' }}
            </span>
            @endif
            <span class="ml-auto text-gray-500">{{ $reviews->total() }} resultado(s)</span>
        </div>
        @endif
    </div>

    <script>
        function setQuickFilter(range) {
            const today = new Date();
            const fmt = (d) => d.toISOString().split('T')[0];
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');

            switch (range) {
                case 'today':
                    dateFrom.value = fmt(today);
                    dateTo.value = fmt(today);
                    break;
                case 'yesterday':
                    const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
                    dateFrom.value = fmt(yesterday);
                    dateTo.value = fmt(yesterday);
                    break;
                case 'week':
                    const weekAgo = new Date(today); weekAgo.setDate(weekAgo.getDate() - 6);
                    dateFrom.value = fmt(weekAgo);
                    dateTo.value = fmt(today);
                    break;
                case 'month':
                    const monthAgo = new Date(today); monthAgo.setDate(monthAgo.getDate() - 29);
                    dateFrom.value = fmt(monthAgo);
                    dateTo.value = fmt(today);
                    break;
            }
            document.getElementById('reviewsFilterForm').submit();
        }
        function setStatusFilter(status) {
            document.querySelector('select[name="status"]').value = status;
            document.getElementById('reviewsFilterForm').submit();
        }
    </script>

    {{-- Cards de resumo --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Convites</p>
                    <p class="text-xl font-bold text-slate-800">{{ $stats['total_invites'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Respostas</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $stats['answered'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Nota media</p>
                    <p class="text-xl font-bold text-amber-500">{{ $stats['average_rating'] > 0 ? number_format($stats['average_rating'], 1, ',', '.') : '-' }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Taxa resposta</p>
                    <p class="text-xl font-bold text-blue-600">{{ $responseRate }}%</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Enviados</p>
                    <p class="text-xl font-bold text-green-600">{{ $stats['sent'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Notas baixas</p>
                    <p class="text-xl font-bold text-red-500">{{ $stats['low_ratings'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Graficos --}}
    <div class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-800 mb-4">Convites vs Respostas (ultimos 14 dias)</h2>
            <div class="h-56">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
            <h2 class="text-sm font-bold text-gray-800 mb-4">Distribuicao das notas</h2>
            <div class="flex items-center gap-6">
                <div class="w-40 h-40 flex-shrink-0">
                    <canvas id="ratingDonut"></canvas>
                </div>
                <div class="flex-1 space-y-2">
                    @for($rating = 5; $rating >= 1; $rating--)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-amber-500">{{ str_repeat('★', $rating) }}</span>
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-amber-400 rounded-full" style="width: {{ $stats['answered'] > 0 ? (($distribution[$rating] ?? 0) / $stats['answered']) * 100 : 0 }}%"></div>
                        </div>
                        <span class="text-xs text-gray-500 w-8 text-right">{{ $distribution[$rating] ?? 0 }}</span>
                    </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- Nota media diaria --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <h2 class="text-sm font-bold text-gray-800 mb-4">Nota media diaria (ultimos 14 dias)</h2>
        <div class="h-44">
            <canvas id="avgRatingChart"></canvas>
        </div>
    </div>

    {{-- Exportar PDF --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-gray-800">Exportar relatório PDF</h3>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.reviews.pdf') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">De</label>
                <input type="date" name="start_date" value="{{ request('date_from', now()->subDays(30)->format('Y-m-d')) }}" class="px-3 py-2 border border-gray-300 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Até</label>
                <input type="date" name="end_date" value="{{ request('date_to', now()->format('Y-m-d')) }}" class="px-3 py-2 border border-gray-300 rounded-xl text-sm">
            </div>
            <button type="submit" class="bg-red-600 text-white py-2 px-4 rounded-xl font-medium hover:bg-red-700 transition-colors text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar PDF
            </button>
        </form>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">Avaliacoes</h2>
            <span class="text-xs text-gray-500">{{ $reviews->total() }} registro(s)</span>
        </div>

        @if(Auth::user()->role === 'admin')
        <div id="bulkBar" class="hidden px-6 py-3 bg-blue-50 border-b border-blue-200 flex items-center gap-3 flex-wrap">
            <span class="text-sm text-blue-800 font-medium"><span id="bulkCount">0</span> selecionado(s)</span>
            <button type="button" onclick="openBulkEditModal()" class="px-3 py-1.5 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-lg text-xs font-medium transition-colors">Editar em lote</button>
            <button type="button" onclick="openBulkDeleteModal()" class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-xs font-medium transition-colors">Excluir em lote</button>
            <button type="button" onclick="clearSelection()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-medium transition-colors">Limpar</button>
        </div>
        @endif

        @if($reviews->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @if(Auth::user()->role === 'admin')
                        <th class="px-4 py-3 text-center w-10">
                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" onchange="toggleSelectAll(this)">
                        </th>
                        @endif
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Telefone</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Viagem</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Envio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Nota</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Motivo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Respondido em</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($reviews as $review)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        @if(Auth::user()->role === 'admin')
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" value="{{ $review->id }}" onchange="updateBulkBar()">
                        </td>
                        @endif
                        <td class="px-4 py-3 font-mono text-gray-700 text-xs">{{ $review->phone ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $review->registration_at?->format('d/m/Y H:i') ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $sendBadge = match($review->whatsapp_status) {
                                    'sent' => 'bg-green-100 text-green-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                    'skipped' => 'bg-gray-100 text-gray-700',
                                    default => 'bg-yellow-100 text-yellow-700',
                                };
                                $sendLabel = match($review->whatsapp_status) {
                                    'sent' => 'Enviado',
                                    'failed' => 'Falha',
                                    'skipped' => 'Ignorado',
                                    default => 'Pendente',
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $sendBadge }}">{{ $sendLabel }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($review->rating)
                            <span class="inline-flex items-center gap-1 text-sm font-bold {{ $review->rating >= 4 ? 'text-green' : ($review->rating >= 3 ? 'text-gold' : 'text-red') }}">
                                {{ $review->rating }}<span class="text-amber-400">★</span>
                            </span>
                            @else
                            <span class="text-gray-300">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs max-w-[250px]">
                            <div style="white-space:pre-line;word-break:break-word">{{ $review->reason ?: '-' }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $review->submitted_at?->format('d/m/Y H:i') ?: '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button type="button" onclick="openViewModal({{ json_encode(['id'=>$review->id,'phone'=>$review->phone,'name'=>$review->user?->name,'user_id'=>$review->user_id,'batch_date'=>$review->batch_date?->format('d/m/Y'),'registration_at'=>$review->registration_at?->format('d/m/Y H:i'),'whatsapp_status'=>$sendLabel,'rating'=>$review->rating,'reason'=>$review->reason,'submitted_at'=>$review->submitted_at?->format('d/m/Y H:i'),'token'=>$review->token,'last_mikrotik_id'=>$review->user?->last_mikrotik_id]) }})" class="p-1.5 text-blue hover:bg-blue-pale rounded-lg transition-colors" title="Visualizar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                @if(Auth::user()->role === 'admin')
                                <button type="button" onclick="openEditModal({{ $review->id }}, '{{ $review->submitted_at?->format('Y-m-d\TH:i') }}', {{ $review->rating ?? 'null' }}, '{{ addslashes($review->reason ?? '') }}')" class="p-1.5 text-gold hover:bg-gold-pale rounded-lg transition-colors" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button type="button" onclick="openDeleteModal({{ $review->id }}, '{{ addslashes($review->user?->name ?: 'Sem nome') }}')" class="p-1.5 text-red hover:bg-red-pale rounded-lg transition-colors" title="Excluir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t border-gray-100">
            {{ $reviews->withQueryString()->links() }}
        </div>
        @else
        <div class="p-12 text-center text-gray-500">
            <span class="block text-4xl mb-3">📭</span>
            <p>Nenhuma avaliacao encontrada.</p>
        </div>
        @endif
    </div>
</div>

{{-- Modal de visualização (todos os níveis) --}}
<div id="viewModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/40 backdrop-blur-sm overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-modal w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-green-dark via-green to-green-light px-5 py-4 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Detalhes da Avaliação</h3>
            <button type="button" onclick="modal('viewModal',false)" class="text-white/70 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-5 space-y-3" id="viewModalContent"></div>
    </div>
</div>

<script>
function modal(id, show) { const m = document.getElementById(id); if(show) { document.body.appendChild(m); m.classList.remove('hidden'); m.classList.add('flex'); document.body.style.overflow='hidden'; } else { m.classList.add('hidden'); m.classList.remove('flex'); document.body.style.overflow=''; } }

function openViewModal(data) {
    const busMap = {'HH50A914NK5':'3097','HH50A7TMT8M':'3099','HH60A2NSBE7':'5013','HH50AB8F056':'5021','HGD09YS6037':'5023','HGK09Q76FMP':'5031','HH50A2ER2JB':'5033','HGJ09X2F8FD':'5035'};
    const ratingStars = data.rating ? '★'.repeat(data.rating) + '☆'.repeat(5 - data.rating) : 'Sem nota';
    const ratingColor = data.rating >= 4 ? 'text-green' : (data.rating >= 3 ? 'text-gold' : 'text-red');
    const link = data.token ? '{{ url("avaliacao") }}/' + data.token : '#';
    const mikrotikId = data.last_mikrotik_id || null;
    const busNumber = mikrotikId ? (busMap[mikrotikId] || '?') : null;
    const busHtml = mikrotikId
        ? `<span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-blue-pale border border-blue/20 text-xs"><span class="font-bold text-blue">🚌 Carro ${busNumber}</span><span class="text-muted">(${mikrotikId})</span></span>`
        : '<span class="text-xs text-muted">Não identificado</span>';

    document.getElementById('viewModalContent').innerHTML = `
        <div class="flex items-center gap-3 pb-3 border-b border-border">
            <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                <span class="text-sm font-bold text-muted">${(data.name || data.phone || '?').substring(0,2).toUpperCase()}</span>
            </div>
            <div>
                <p class="text-sm font-bold text-ink">${data.name || data.phone || 'Passageiro sem nome'}</p>
                <p class="text-[10px] text-muted">ID #${data.user_id || '-'}</p>
                ${data.phone ? `<p class="text-sm font-bold text-blue mt-0.5">📱 ${data.phone}</p>` : ''}
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-surface rounded-xl p-3">
                <p class="text-[10px] text-muted font-bold uppercase tracking-wider">Lote</p>
                <p class="text-sm font-semibold text-ink">${data.batch_date || '-'}</p>
            </div>
            <div class="bg-surface rounded-xl p-3">
                <p class="text-[10px] text-muted font-bold uppercase tracking-wider">Viagem</p>
                <p class="text-sm font-semibold text-ink">${data.registration_at || '-'}</p>
            </div>
            <div class="bg-surface rounded-xl p-3">
                <p class="text-[10px] text-muted font-bold uppercase tracking-wider">Envio</p>
                <p class="text-sm font-semibold text-ink">${data.whatsapp_status || '-'}</p>
            </div>
            <div class="bg-surface rounded-xl p-3">
                <p class="text-[10px] text-muted font-bold uppercase tracking-wider">Respondido</p>
                <p class="text-sm font-semibold text-ink">${data.submitted_at || 'Não respondeu'}</p>
            </div>
        </div>
        <div class="bg-surface rounded-xl p-3">
            <p class="text-[10px] text-muted font-bold uppercase tracking-wider mb-1">Ônibus</p>
            ${busHtml}
        </div>
        ${data.rating ? `
        <div class="bg-surface rounded-xl p-3 text-center">
            <p class="text-[10px] text-muted font-bold uppercase tracking-wider mb-1">Nota</p>
            <p class="text-2xl ${ratingColor}">${ratingStars}</p>
            <p class="text-lg font-bold ${ratingColor}">${data.rating}/5</p>
        </div>` : ''}
        ${data.reason ? `
        <div class="bg-surface rounded-xl p-3">
            <p class="text-[10px] text-muted font-bold uppercase tracking-wider mb-1">Motivo</p>
            <p class="text-sm text-ink" style="white-space:pre-line;word-break:break-word">${data.reason}</p>
        </div>` : ''}
        <a href="${link}" target="_blank" class="block text-center text-xs text-blue hover:underline">Abrir link da avaliação →</a>
    `;
    modal('viewModal', true);
}
</script>

@if(Auth::user()->role === 'admin')
{{-- Modal de edicao --}}
<div id="editModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/40 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Editar avaliacao</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Respondido em</label>
                    <input type="datetime-local" name="submitted_at" id="editSubmittedAt" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-tocantins-green focus:border-transparent text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota</label>
                    <select name="rating" id="editRating" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-tocantins-green focus:border-transparent text-sm">
                        <option value="">Sem nota</option>
                        @for($r = 1; $r <= 5; $r++)
                        <option value="{{ $r }}">{{ $r }} estrela{{ $r > 1 ? 's' : '' }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                    <textarea name="reason" id="editReason" rows="3" maxlength="1000" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-tocantins-green focus:border-transparent text-sm" placeholder="Motivo da avaliacao..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3 justify-end">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-300 transition-colors">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700 transition-colors">Salvar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal de exclusao --}}
<div id="deleteModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/40 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Excluir avaliacao</h3>
            <button type="button" onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-1">Excluir avaliacao de:</p>
        <p class="text-sm font-semibold text-gray-800 mb-4" id="deleteReviewName"></p>
        <p class="text-xs text-red-500 mb-6">Esta acao nao pode ser desfeita.</p>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-300 transition-colors">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition-colors">Excluir</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal edicao em lote --}}
<div id="bulkEditModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/40 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Editar em lote</h3>
            <button type="button" onclick="closeBulkEditModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <p class="text-sm text-gray-500 mb-4">Campos preenchidos serao aplicados nos <span id="bulkEditCount" class="font-semibold text-gray-800">0</span> registros.</p>
        <form id="bulkEditForm" method="POST" action="{{ route('admin.reviews.bulk-update') }}">
            @csrf
            @method('PUT')
            <div id="bulkEditIds"></div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Respondido em</label>
                    <input type="datetime-local" name="submitted_at" id="bulkEditSubmittedAt" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nota</label>
                    <select name="rating" id="bulkEditRating" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        <option value="">Nao alterar</option>
                        @for($r = 1; $r <= 5; $r++)<option value="{{ $r }}">{{ $r }} ★</option>@endfor
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                    <textarea name="reason" id="bulkEditReason" rows="2" maxlength="1000" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm" placeholder="Vazio = nao alterar"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3 justify-end">
                <button type="button" onclick="closeBulkEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-medium">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-medium">Salvar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal exclusao em lote --}}
<div id="bulkDeleteModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/40 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Excluir em lote</h3>
            <button type="button" onclick="closeBulkDeleteModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-2">Excluir <span id="bulkDeleteCount" class="font-semibold">0</span> avaliacao(oes)?</p>
        <p class="text-xs text-red-500 mb-6">Esta acao nao pode ser desfeita.</p>
        <form id="bulkDeleteForm" method="POST" action="{{ route('admin.reviews.bulk-destroy') }}">
            @csrf
            @method('DELETE')
            <div id="bulkDeleteIds"></div>
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeBulkDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-medium">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium">Excluir</button>
            </div>
        </form>
    </div>
</div>

<script>
function getSelectedIds() { return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value); }
function updateBulkBar() {
    const ids = getSelectedIds(), bar = document.getElementById('bulkBar'), all = document.querySelectorAll('.row-checkbox');
    document.getElementById('bulkCount').textContent = ids.length;
    ids.length > 0 ? (bar.classList.remove('hidden'), bar.classList.add('flex')) : (bar.classList.add('hidden'), bar.classList.remove('flex'));
    document.getElementById('selectAll').checked = all.length > 0 && ids.length === all.length;
    document.getElementById('selectAll').indeterminate = ids.length > 0 && ids.length < all.length;
}
function toggleSelectAll(el) { document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = el.checked); updateBulkBar(); }
function clearSelection() { document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false); document.getElementById('selectAll').checked = false; updateBulkBar(); }

function openEditModal(id, s, r, reason) { document.getElementById('editForm').action = '{{ url("admin/avaliacoes") }}/' + id + window.location.search; document.getElementById('editSubmittedAt').value = s||''; document.getElementById('editRating').value = r||''; document.getElementById('editReason').value = reason||''; modal('editModal', true); }
function closeEditModal() { modal('editModal', false); }
function openDeleteModal(id, name) { document.getElementById('deleteForm').action = '{{ url("admin/avaliacoes") }}/' + id; document.getElementById('deleteReviewName').textContent = name; modal('deleteModal', true); }
function closeDeleteModal() { modal('deleteModal', false); }

function injectIds(cid, ids) { const c = document.getElementById(cid); c.innerHTML = ''; ids.forEach(id => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value=id; c.appendChild(i); }); }
function openBulkEditModal() { const ids = getSelectedIds(); if(!ids.length) return; injectIds('bulkEditIds', ids); document.getElementById('bulkEditCount').textContent = ids.length; document.getElementById('bulkEditSubmittedAt').value=''; document.getElementById('bulkEditRating').value=''; document.getElementById('bulkEditReason').value=''; modal('bulkEditModal', true); }
function closeBulkEditModal() { modal('bulkEditModal', false); }
function openBulkDeleteModal() { const ids = getSelectedIds(); if(!ids.length) return; injectIds('bulkDeleteIds', ids); document.getElementById('bulkDeleteCount').textContent = ids.length; modal('bulkDeleteModal', true); }
function closeBulkDeleteModal() { modal('bulkDeleteModal', false); }

['editModal','deleteModal','bulkEditModal','bulkDeleteModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) { if(e.target === this) modal(id, false); });
});
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const labels = @json($chartLabels);
const defaultFont = { family: 'Inter, sans-serif', size: 11 };
Chart.defaults.font = defaultFont;

// Grafico diario
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Convites', data: @json($chartInvites), backgroundColor: 'rgba(100,116,139,0.25)', borderColor: 'rgb(100,116,139)', borderWidth: 1, borderRadius: 6 },
            { label: 'Respostas', data: @json($chartAnswered), backgroundColor: 'rgba(16,185,129,0.35)', borderColor: 'rgb(16,185,129)', borderWidth: 1, borderRadius: 6 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } } }
});

// Donut de notas
new Chart(document.getElementById('ratingDonut'), {
    type: 'doughnut',
    data: {
        labels: ['5★','4★','3★','2★','1★'],
        datasets: [{ data: [{{ $distribution[5] ?? 0 }}, {{ $distribution[4] ?? 0 }}, {{ $distribution[3] ?? 0 }}, {{ $distribution[2] ?? 0 }}, {{ $distribution[1] ?? 0 }}], backgroundColor: ['#10b981','#34d399','#fbbf24','#f97316','#ef4444'], borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { display: false } } }
});

// Nota media diaria
new Chart(document.getElementById('avgRatingChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Nota media',
            data: @json($chartAvgRating),
            borderColor: 'rgb(245,158,11)',
            backgroundColor: 'rgba(245,158,11,0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: 'rgb(245,158,11)'
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 5, ticks: { stepSize: 1 } }, x: { grid: { display: false } } } }
});
</script>
@endsection
