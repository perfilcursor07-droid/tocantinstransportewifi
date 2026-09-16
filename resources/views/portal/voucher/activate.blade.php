@extends('portal.layout')

@section('content')
<div class="min-h-screen bg-[#F8F9FA] py-8 px-4" style="font-family:'Inter',sans-serif">
<div class="max-w-sm mx-auto" style="animation:fadeUp .5s cubic-bezier(.22,1,.36,1) both">

    <!-- Header -->
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-[#007A28] to-[#00A335] shadow-[0_4px_12px_rgba(0,0,0,0.1)] mb-3">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                      d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
            </svg>
        </div>
        <p class="text-[10px] font-bold uppercase tracking-widest text-[#00A335] mb-0.5">Starlink · Tocantins Transporte</p>
        <h1 class="text-xl font-bold text-[#111] leading-tight">Voucher de Motorista</h1>
        <p class="text-xs text-[#888] mt-1">Digite seu CPF ou número do voucher</p>
    </div>

    <!-- Alertas -->
    @if (session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-[#00A335]/20 bg-[#E8F5E9] px-4 py-3">
            <svg class="w-4 h-4 text-[#00A335] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-xs text-[#00A335] font-medium">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-[#D32F2F]/20 bg-[#FFEBEE] px-4 py-3">
            <svg class="w-4 h-4 text-[#D32F2F] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <p class="text-xs text-[#D32F2F] font-medium" style="white-space:pre-line">{{ session('error') }}</p>
        </div>
    @endif
    @if (session('warning'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-[#E6A817]/20 bg-[#FFF8E1] px-4 py-3">
            <svg class="w-4 h-4 text-[#E6A817] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <p class="text-xs text-[#E6A817] font-medium" style="white-space:pre-line">{{ session('warning') }}</p>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-[#D32F2F]/20 bg-[#FFEBEE] px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-xs text-[#D32F2F] font-medium">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (!isset($voucher))
    {{-- ═══ ETAPA 1: Busca ═══ --}}
    <div class="bg-white border border-[#E5E5E5] rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.08)] overflow-hidden">
        <!-- Hero strip -->
        <div class="bg-gradient-to-r from-[#007A28] via-[#00A335] to-[#00C040] px-5 py-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div>
                <p class="text-white font-semibold text-sm leading-none">Acesso Motorista</p>
                <p class="text-white/70 text-[10px] mt-0.5">Digite seu CPF ou número do voucher para liberar a internet</p>
            </div>
        </div>

        <form action="{{ route('voucher.search') }}" method="POST" id="searchForm" class="p-5">
            @csrf
            <div class="mb-4">
                <label for="search_term" class="block text-[11px] font-semibold text-[#333] uppercase tracking-wider mb-1.5">CPF ou número do voucher</label>
                <input type="text" id="search_term" name="search_term" required autofocus
                       value="{{ old('search_term') }}"
                       placeholder="Digite CPF ou número do voucher"
                       class="w-full px-4 py-3.5 text-center text-lg font-bold text-[#111] bg-[#F8F9FA] border border-[#E5E5E5] rounded-xl
                              focus:outline-none focus:ring-2 focus:ring-[#00A335]/30 focus:border-[#00A335] transition-all placeholder:text-[#888] placeholder:font-normal placeholder:text-base">
            </div>
            <input type="hidden" name="mac_address" value="{{ $mac_address ?? '' }}">
            <input type="hidden" name="ip_address" value="{{ $ip_address ?? '' }}">
            <button type="submit" id="searchBtn"
                    class="w-full bg-[#00A335] hover:bg-[#00C040] active:bg-[#007A28] text-white font-bold py-3.5 rounded-xl
                           shadow-[0_4px_12px_rgba(0,163,53,0.3)] hover:shadow-[0_8px_20px_rgba(0,163,53,0.35)] transition-all flex items-center justify-center gap-2 text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span id="searchBtnText">Ativar Internet</span>
            </button>
        </form>
    </div>

    @else
    {{-- ═══ ETAPA 2: Voucher encontrado ═══ --}}
    <div class="bg-white border border-[#E5E5E5] rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.08)] overflow-hidden">
        <!-- Header verde -->
        <div class="bg-gradient-to-r from-[#007A28] via-[#00A335] to-[#00C040] px-5 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/60">Voucher</p>
                    <p class="text-lg font-bold text-white font-mono">{{ $voucher->code }}</p>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider bg-white/20 text-white px-2 py-1 rounded-full">Ativo</span>
            </div>
        </div>

        <div class="p-5">
            <!-- Motorista -->
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center border-2 border-white shadow-sm flex-shrink-0">
                    <span class="text-sm font-bold text-[#888]">{{ strtoupper(substr($voucher->driver_name, 0, 2)) }}</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-[#111]">{{ $voucher->driver_name }}</p>
                    @if($voucher->driver_document)
                        <p class="text-[10px] text-[#888]">CPF: {{ $voucher->driver_document }}</p>
                    @endif
                </div>
            </div>

            <!-- Status -->
            @php
                $stColors = [
                    'success' => ['bg' => 'bg-[#E8F5E9]', 'border' => 'border-[#00A335]/20', 'text' => 'text-[#00A335]', 'icon' => 'M5 13l4 4L19 7'],
                    'info'    => ['bg' => 'bg-[#E3F2FD]', 'border' => 'border-[#1565C0]/20', 'text' => 'text-[#1565C0]', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z'],
                    'warning' => ['bg' => 'bg-[#FFF8E1]', 'border' => 'border-[#E6A817]/20', 'text' => 'text-[#E6A817]', 'icon' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
                    'error'   => ['bg' => 'bg-[#FFEBEE]', 'border' => 'border-[#D32F2F]/20', 'text' => 'text-[#D32F2F]', 'icon' => 'M6 18L18 6M6 6l12 12'],
                ];
                $st = $stColors[$voucherStatus['type']] ?? $stColors['info'];
            @endphp
            <div class="mb-4 {{ $st['bg'] }} border {{ $st['border'] }} rounded-xl px-4 py-3 flex items-start gap-2.5">
                <svg class="w-4 h-4 {{ $st['text'] }} flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $st['icon'] }}"/></svg>
                <p class="text-xs {{ $st['text'] }} font-medium" style="white-space:pre-line">{{ $voucherStatus['message'] }}</p>
            </div>

            <!-- Info cards -->
            <div class="grid grid-cols-2 gap-2 mb-5">
                @if($voucherStatus['is_active_session'])
                    <div class="col-span-2 bg-[#E3F2FD] rounded-xl p-3 text-center">
                        <p class="text-[10px] text-[#1565C0] font-medium">Status</p>
                        <p class="text-base font-bold text-[#1565C0]">Em uso</p>
                    </div>
                @elseif($voucherStatus['next_activation'])
                    <div class="col-span-2 bg-[#FFF8E1] rounded-xl p-3 text-center">
                        <p class="text-[10px] text-[#E6A817] font-medium">Próxima Ativação</p>
                        <p class="text-base font-bold text-[#E6A817]">{{ $voucherStatus['next_activation']->format('d/m/Y H:i') }}</p>
                    </div>
                @elseif($voucherStatus['can_activate'])
                    <div class="col-span-2 bg-[#E8F5E9] rounded-xl p-3 text-center">
                        <p class="text-[10px] text-[#00A335] font-medium">Status</p>
                        <p class="text-base font-bold text-[#00A335]">Pronto para ativar</p>
                    </div>
                @endif

                @if($voucher->expires_at)
                    <div class="col-span-2 bg-[#F8F9FA] border border-[#E5E5E5] rounded-xl p-3 text-center">
                        <p class="text-[10px] text-[#888] font-medium">Validade</p>
                        <p class="text-sm font-bold text-[#111]">
                            {{ $voucher->expires_at->format('d/m/Y') }}
                            @if($voucher->expires_at->isPast())
                                <span class="text-[#D32F2F]">(Expirado)</span>
                            @else
                                <span class="text-[#00A335]">({{ $voucher->expires_at->diffForHumans() }})</span>
                            @endif
                        </p>
                    </div>
                @endif
            </div>

            <!-- Botão -->
            @if($voucherStatus['can_activate'])
                <form action="{{ route('voucher.activate.submit') }}" method="POST" id="activateForm">
                    @csrf
                    <input type="hidden" name="voucher_code" value="{{ $voucher->code }}">
                    <input type="hidden" name="mac_address" value="{{ $mac_address ?? '' }}">
                    <input type="hidden" name="ip_address" value="{{ $ip_address ?? '' }}">
                    <button type="submit" id="activateBtn"
                            class="w-full bg-[#00A335] hover:bg-[#00C040] active:bg-[#007A28] text-white font-bold py-3.5 rounded-xl
                                   shadow-[0_4px_12px_rgba(0,163,53,0.3)] hover:shadow-[0_8px_20px_rgba(0,163,53,0.35)] transition-all flex items-center justify-center gap-2 text-sm">
                        <svg id="btnIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span id="btnText">Ativar Voucher Agora</span>
                    </button>
                </form>
                <!-- Loading card -->
                <div id="loadingCard" class="hidden mt-3">
                    <div class="bg-[#E3F2FD] border border-[#1565C0]/20 rounded-xl p-5">
                        <div class="flex justify-center mb-3">
                            <div class="w-12 h-12 border-3 border-[#1565C0]/20 border-t-[#1565C0] rounded-full animate-spin"></div>
                        </div>
                        <p class="text-center text-[#1565C0] font-bold text-sm mb-2" id="loadingMessage">Conectando...</p>
                        <div class="h-1.5 bg-[#1565C0]/20 rounded-full overflow-hidden mb-2">
                            <div id="progressBar" class="h-full bg-[#1565C0] rounded-full transition-all duration-1000" style="width:5%"></div>
                        </div>
                        <p class="text-center text-[10px] text-[#1565C0]" id="timerText">Aguarde até 60 segundos...</p>
                    </div>
                </div>
            @else
                <a href="{{ route('voucher.activate') }}"
                   class="w-full bg-[#F8F9FA] border border-[#E5E5E5] text-[#333] font-semibold py-3 rounded-xl
                          hover:bg-[#E5E5E5] transition-all flex items-center justify-center gap-2 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Voltar e Buscar Outro
                </a>
            @endif
        </div>
    </div>

    @if($voucherStatus['can_activate'])
        <p class="mt-3 text-center">
            <a href="{{ route('voucher.activate') }}" class="text-xs text-[#888] hover:text-[#00A335] transition-colors">← Buscar outro voucher</a>
        </p>
    @endif
    @endif

    <!-- Links -->
    <p class="mt-5 text-center">
        <a href="{{ route('voucher.status') }}" class="text-xs text-[#888] hover:text-[#00A335] transition-colors">Já ativou? Verificar status →</a>
    </p>
    <p class="mt-2 text-center text-[10px] text-[#888]/60">IP: {{ $ip_address ?? 'N/A' }} · MAC: {{ $mac_address ?? 'N/A' }}</p>

</div>
</div>

<style>
@keyframes fadeUp { 0%{opacity:0;transform:translateY(24px)} 100%{opacity:1;transform:translateY(0)} }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_term');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            if (e.target.value.toUpperCase().startsWith('WIFI') || e.target.value.includes('-')) {
                e.target.value = e.target.value.toUpperCase();
            }
        });
    }

    const searchForm = document.getElementById('searchForm');
    const searchBtn = document.getElementById('searchBtn');
    if (searchForm && searchBtn) {
        searchForm.addEventListener('submit', function() {
            searchBtn.disabled = true;
            searchBtn.classList.add('opacity-75');
            document.getElementById('searchBtnText').textContent = 'Ativando...';
        });
    }

    const activateForm = document.getElementById('activateForm');
    const activateBtn = document.getElementById('activateBtn');
    const loadingCard = document.getElementById('loadingCard');
    if (activateForm && activateBtn && loadingCard) {
        activateForm.addEventListener('submit', function() {
            activateBtn.classList.add('hidden');
            loadingCard.classList.remove('hidden');
            const msg = document.getElementById('loadingMessage');
            const bar = document.getElementById('progressBar');
            const timer = document.getElementById('timerText');
            const stages = [
                {t:0,m:'Conectando ao servidor...',p:10},
                {t:5,m:'Validando voucher...',p:25},
                {t:10,m:'Registrando dispositivo...',p:40},
                {t:15,m:'Configurando acesso...',p:55},
                {t:25,m:'Liberando internet...',p:70},
                {t:35,m:'Finalizando...',p:85},
                {t:50,m:'Aguarde mais um pouco...',p:95},
            ];
            let sec = 0, stage = 0;
            setInterval(function() {
                sec++;
                const rem = 60 - sec;
                timer.textContent = rem > 0 ? `Tempo estimado: ${rem}s...` : 'Processando...';
                for (let i = stages.length-1; i >= 0; i--) {
                    if (sec >= stages[i].t && stage < i) {
                        stage = i; msg.textContent = stages[i].m; bar.style.width = stages[i].p+'%'; break;
                    }
                }
                if (sec >= 60) { msg.textContent = 'Ainda processando...'; bar.style.width = '100%'; }
            }, 1000);
            msg.textContent = stages[0].m; bar.style.width = stages[0].p+'%';
        });
    }
});
</script>
@endsection
