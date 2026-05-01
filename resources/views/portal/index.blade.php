<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Tocantins - Conecte-se à Internet</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $forceLogin = config('wifi.mikrotik.force_login_redirect', false);
        $skipLogin = request()->boolean('skip_login');
        $hasContext = request()->hasAny(['mac', 'mikrotik_mac', 'client_mac'])
            || request()->boolean('from_login')
            || request()->boolean('captive')
            || request()->boolean('from_router');
        $loginUrl = config('wifi.mikrotik.login_url', 'http://10.5.50.1/login');
    @endphp
    @if ($forceLogin && !$skipLogin && !$hasContext)
        <meta http-equiv="refresh" content="0;url={{ $loginUrl }}?dst={{ urlencode(request()->fullUrl()) }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        surface: '#F0F4F3',
                        ink: '#111111',
                        ink2: '#333333',
                        muted: '#888888',
                        border: '#E0E0E0',
                        green: { DEFAULT: '#00A335', light: '#00C040', dark: '#007A28', pale: '#E8F5E9' },
                        gold: { DEFAULT: '#E6A817', pale: '#FFF8E1' },
                        red: { DEFAULT: '#D32F2F', pale: '#FFEBEE' },
                        blue: { DEFAULT: '#1565C0', light: '#1E88E5', pale: '#E3F2FD' },
                        brand: { 50: '#E8F5E9', 100: '#C8E6C9', 500: '#00A335', 600: '#007A28', 700: '#005A1D' }
                    },
                    fontFamily: { 'sans': ['Inter', 'system-ui', 'sans-serif'] },
                    boxShadow: {
                        card: '0 1px 4px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.03)',
                        hover: '0 8px 24px rgba(0,0,0,0.10)',
                        modal: '0 20px 60px rgba(0,0,0,0.20)',
                        glow: '0 0 20px rgba(0,163,53,0.25)',
                    },
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 14px rgba(0,163,53,0.35), 0 0 0 0 rgba(0,163,53,0.4); }
            50% { box-shadow: 0 4px 20px rgba(0,163,53,0.5), 0 0 0 10px rgba(0,163,53,0); }
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        .animate-slide-up { animation: slideUp 0.5s cubic-bezier(.22,1,.36,1); }
        .animate-slide-up-delay { animation: slideUp 0.5s cubic-bezier(.22,1,.36,1) 0.1s both; }
        .btn-pulse { animation: pulse-glow 2s ease-in-out infinite; }
        .connect-button {
            background: linear-gradient(135deg, #00C040 0%, #007A28 100%);
            position: relative; overflow: hidden;
            transition: all 0.25s cubic-bezier(.22,1,.36,1);
        }
        .connect-button::after {
            content: '';
            position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 3s infinite;
        }
        .connect-button:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,163,53,0.35); }
        .connect-button:active { transform: scale(0.97); }
        .plan-card-selected {
            border-color: #00A335 !important;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%) !important;
            box-shadow: 0 0 0 2px rgba(0,163,53,0.2), 0 4px 12px rgba(0,163,53,0.08) !important;
        }
        .plan-card-selected [data-plan-radio] {
            border-width: 5px !important; border-color: #00A335 !important;
        }
        .hero-gradient { background: linear-gradient(160deg, #006B25 0%, #00A335 40%, #00C040 100%); }
        body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
    </style>
</head>
<body class="font-sans min-h-screen bg-surface">

    <!-- No-WiFi Warning Overlay -->
    <div id="no-wifi-warning" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden">
        <div class="flex items-center justify-center h-full p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-sm animate-slide-up shadow-2xl text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6 18L18 6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Conecte-se ao WiFi primeiro</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Você está acessando pelo <strong>navegador</strong> sem estar conectado ao <strong>WiFi do ônibus</strong>. Para pagar e usar a internet, siga os passos abaixo:
                </p>
                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-left">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Como conectar:</p>
                    <div class="space-y-2.5">
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                            <p class="text-sm text-gray-700"><strong>Desative os Dados Móveis</strong> (4G/5G) do celular</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                            <p class="text-sm text-gray-700">Conecte ao WiFi <strong>"TocantinsTransporteWiFi"</strong></p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                            <p class="text-sm text-gray-700">Aguarde a <strong>tela de login</strong> aparecer automaticamente</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-bold">4</span>
                            <p class="text-sm text-gray-700">Clique em <strong>"ACESSAR INTERNET AGORA"</strong> e faça o pagamento PIX</p>
                        </div>
                    </div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-5">
                    <p class="text-xs text-amber-800">
                        <strong>Importante:</strong> Se você pagar sem estar no WiFi do ônibus, o acesso <strong>não será liberado</strong> porque não conseguimos identificar seu dispositivo.
                    </p>
                </div>
                <button id="no-wifi-retry-btn" onclick="retryWifiCheck()" class="connect-button w-full text-white font-bold py-3.5 rounded-xl shadow-md text-sm mb-3">
                    JÁ CONECTEI NO WIFI, VERIFICAR
                </button>
                <p class="text-[11px] text-gray-400 mt-2">A tela de pagamento só aparece quando você estiver no WiFi do ônibus</p>
            </div>
        </div>
    </div>

    <script>
    function hasMikrotikContext() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has('mac') || urlParams.has('mikrotik_mac') || urlParams.has('client_mac') ||
               urlParams.has('from_mikrotik') || urlParams.has('from_router') ||
               urlParams.has('captive') || urlParams.has('from_login');
    }
    function showNoWifiWarning() {
        document.getElementById('no-wifi-warning').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        window._noWifiBlocked = true;
    }
    function hideNoWifiWarning() {
        document.getElementById('no-wifi-warning').classList.add('hidden');
        document.body.style.overflow = 'auto';
        window._noWifiBlocked = false;
    }
    function retryWifiCheck() {
        const btn = document.getElementById('no-wifi-retry-btn');
        btn.innerHTML = '<span class="animate-pulse">Verificando...</span>';
        btn.disabled = true;
        const img = new Image();
        let responded = false;
        img.onload = function() { responded = true; hideNoWifiWarning(); window.location.href = 'http://10.5.50.1'; };
        img.onerror = function() {
            fetch('http://10.5.50.1', { mode: 'no-cors', cache: 'no-cache' })
                .then(() => { responded = true; hideNoWifiWarning(); window.location.href = 'http://10.5.50.1'; })
                .catch(() => {
                    btn.innerHTML = 'WIFI NÃO DETECTADO!'; btn.classList.remove('connect-button'); btn.classList.add('bg-red-500');
                    setTimeout(() => { btn.innerHTML = 'JÁ CONECTEI NO WIFI, VERIFICAR'; btn.classList.add('connect-button'); btn.classList.remove('bg-red-500'); btn.disabled = false; }, 2500);
                });
        };
        img.src = 'http://10.5.50.1/favicon.ico?t=' + Date.now();
        setTimeout(function() {
            if (!responded) {
                btn.innerHTML = 'WIFI NÃO DETECTADO!'; btn.classList.remove('connect-button'); btn.classList.add('bg-red-500');
                setTimeout(() => { btn.innerHTML = 'JÁ CONECTEI NO WIFI, VERIFICAR'; btn.classList.add('connect-button'); btn.classList.remove('bg-red-500'); btn.disabled = false; }, 2500);
            }
        }, 4000);
    }
    document.addEventListener('DOMContentLoaded', function() {
        if (hasMikrotikContext()) { window._noWifiBlocked = false; return; }
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (conn && conn.type === 'cellular') { showNoWifiWarning(); return; }
        let gatewayReached = false;
        fetch('http://10.5.50.1', { mode: 'no-cors', cache: 'no-cache' })
            .then(() => { gatewayReached = true; window.location.href = 'http://10.5.50.1'; })
            .catch(() => { if (!gatewayReached) showNoWifiWarning(); });
        setTimeout(function() { if (!gatewayReached) showNoWifiWarning(); }, 4000);
    });
    </script>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center h-full">
            <div class="bg-white rounded-2xl p-8 text-center shadow-xl">
                <div class="animate-spin rounded-full h-10 w-10 border-3 border-gray-200 border-t-brand-600 mx-auto mb-4"></div>
                <p class="text-gray-800 font-semibold text-sm">Processando pagamento...</p>
                <p class="text-gray-400 text-xs mt-1">Por favor, aguarde</p>
            </div>
        </div>
    </div>

    <div class="min-h-screen flex flex-col">

        <!-- Conteudo Principal -->
        <main class="flex-1 px-4 pt-5 pb-6 sm:pt-6 sm:pb-8">
            <div class="max-w-lg mx-auto space-y-4 sm:space-y-5">

                <!-- Como se conectar -->
                <section class="bg-white rounded-xl border border-border shadow-card animate-fade-in">
                    <div class="px-4 py-3">
                        <p class="text-[11px] font-bold text-ink mb-2">Como se conectar</p>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div>
                                <span class="w-5 h-5 rounded-full bg-green-pale text-green-dark text-[9px] font-bold flex items-center justify-center mx-auto">1</span>
                                <p class="text-[9px] text-muted mt-1 leading-tight">Desative os dados móveis</p>
                            </div>
                            <div>
                                <span class="w-5 h-5 rounded-full bg-green-pale text-green-dark text-[9px] font-bold flex items-center justify-center mx-auto">2</span>
                                <p class="text-[9px] text-muted mt-1 leading-tight">Conecte ao WiFi do ônibus</p>
                            </div>
                            <div>
                                <span class="w-5 h-5 rounded-full bg-green-pale text-green-dark text-[9px] font-bold flex items-center justify-center mx-auto">3</span>
                                <p class="text-[9px] text-muted mt-1 leading-tight">Escolha o plano e pague via PIX</p>
                            </div>
                            <div>
                                <span class="w-5 h-5 rounded-full bg-green text-white text-[9px] font-bold flex items-center justify-center mx-auto">✓</span>
                                <p class="text-[9px] text-green-dark font-medium mt-1 leading-tight">Pronto! É só navegar</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Card de Planos -->
                <section class="bg-white rounded-2xl border border-border shadow-card overflow-hidden animate-slide-up">
                    <div class="px-5 py-5 sm:px-6 sm:py-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-green to-green-dark flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-ink leading-tight">Escolha seu plano</p>
                                <p class="text-[11px] text-muted">Selecione e conecte-se</p>
                            </div>
                        </div>

                        <div class="space-y-3" id="wifi-plan-options">
                            @if($plan_short_enabled ?? true)
                            <!-- Plano 1 hora (compacto) -->
                            <button type="button" data-plan-option data-plan-price="{{ $wifi_price_short ?? 5.99 }}" data-plan-duration="{{ $session_duration_short ?? 1 }}" data-plan-name="{{ $session_duration_short ?? 1 }} hora(s) de acesso" data-plan-suffix="/ hora"
                                class="wifi-plan-card flex w-full items-center gap-3 rounded-2xl border-2 border-gray-200 bg-white px-4 py-3 text-left transition-all duration-200 hover:border-green/40 focus:outline-none focus:ring-2 focus:ring-green/20">
                                <span data-plan-radio class="h-5 w-5 rounded-full border-2 border-gray-300 bg-white flex-shrink-0 transition-all duration-200"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-ink leading-tight">{{ $session_duration_short ?? 1 }} hora(s) de acesso</p>
                                    <p class="text-[11px] text-muted mt-0.5">Ideal para uso rápido</p>
                                </div>
                                <p data-plan-price-display class="text-lg font-extrabold text-ink tracking-tight">R${{ number_format($wifi_price_short ?? 5.99, 2, ',', '.') }}</p>
                            </button>
                            @endif

                            @if($plan_full_enabled ?? true)
                            <!-- Plano Viagem Completa (PRÉ-SELECIONADO) -->
                            <button type="button" data-plan-option data-plan-price="{{ $wifi_price_full ?? 6.99 }}" data-plan-duration="{{ $session_duration ?? 12 }}" data-plan-name="Viagem completa" data-plan-suffix="/ viagem" data-plan-default="true"
                                class="wifi-plan-card plan-card-selected relative flex w-full rounded-2xl border-2 border-green text-left transition-all duration-200 hover:shadow-hover focus:outline-none focus:ring-2 focus:ring-green/20 flex-col">
                                <span class="absolute -top-2.5 right-4 bg-gradient-to-r from-green to-green-dark text-white text-[9px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full shadow-sm z-10">Mais escolhido</span>
                                <div class="flex items-center gap-3 px-4 pt-4 pb-2.5">
                                    <span data-plan-radio class="h-5 w-5 rounded-full border-[5px] border-green bg-white flex-shrink-0 transition-all duration-200"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] font-extrabold text-ink leading-tight">Viagem completa</p>
                                        <p class="text-xs text-green-dark font-medium mt-0.5">WiFi até o destino final</p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="flex items-center gap-1.5 justify-end">
                                            <span class="text-[11px] text-gray-400 line-through font-medium">R${{ number_format($original_price ?? 9.99, 2, ',', '.') }}</span>
                                            <span class="text-[9px] font-bold text-white bg-red-500 rounded px-1 py-0.5 leading-none">-{{ $discount_percentage ?? 30 }}%</span>
                                        </div>
                                        <p data-plan-price-display class="text-[22px] font-black text-green-dark tracking-tight leading-none mt-0.5">R${{ number_format($wifi_price_full ?? 6.99, 2, ',', '.') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 px-4 pb-3.5 text-[11px] text-green-dark/70 font-medium">
                                    <span>✓ Apps</span>
                                    <span>✓ Streaming</span>
                                    <span>✓ Redes sociais</span>
                                    <span>✓ Melhor custo</span>
                                </div>
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="border-t border-gray-100"></div>

                    <div class="px-5 py-5 sm:px-6">
                        <!-- Desconto por Vídeo -->
                        @if($video_discount_enabled ?? false)
                        <div id="video-discount-section" class="mb-5">
                            <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl border-2 border-purple-200 p-4 relative overflow-hidden">
                                <div class="absolute top-0 right-0 bg-purple-500 text-white text-[9px] font-bold px-3 py-1 rounded-bl-lg">ECONOMIZE</div>
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0 border border-purple-200">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-purple-900">Assista e ganhe R${{ number_format($video_discount_amount ?? 1, 2, ',', '.') }} de desconto!</p>
                                        <p class="text-[11px] text-purple-600 mt-0.5">Vídeo curto de 42 segundos. Assista completo para desbloquear.</p>
                                    </div>
                                </div>
                                <button id="watch-video-discount-btn" onclick="openVideoDiscount()" class="mt-3 w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold py-3 rounded-xl text-sm flex items-center justify-center gap-2 transition-all shadow-md active:scale-[0.98]">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    ASSISTIR VÍDEO E GANHAR DESCONTO
                                </button>
                                <div id="video-discount-applied" class="hidden mt-3 bg-green-50 border border-green-300 rounded-lg p-3 text-center">
                                    <p class="text-green-700 font-bold text-sm flex items-center justify-center gap-1.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        Desconto de R${{ number_format($video_discount_amount ?? 1, 2, ',', '.') }} aplicado!
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                        <!-- Apps compatíveis -->
                        <div class="flex justify-center items-center gap-2.5 mb-5">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400 flex items-center justify-center shadow-sm" title="Instagram">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center shadow-sm" title="YouTube">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-black flex items-center justify-center shadow-sm" title="Netflix">
                                <span class="text-red-600 font-black text-base">N</span>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center shadow-sm" title="WhatsApp">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-sm" title="Facebook">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-black flex items-center justify-center shadow-sm" title="TikTok">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.46V13a8.28 8.28 0 005.58 2.17V11.7a4.83 4.83 0 01-3.77-1.24V6.69h3.77z"/></svg>
                            </div>
                        </div>

                        <!-- Botão CTA Principal - PULSANDO -->
                        <div id="plan-cta-wrapper">
                            <button id="connect-btn"
                                class="connect-button btn-pulse w-full text-white font-extrabold py-4 rounded-xl text-base flex items-center justify-center gap-2.5 shadow-lg lg:hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                ACESSAR INTERNET AGORA
                            </button>
                            <button id="connect-btn-desktop"
                                class="connect-button btn-pulse w-full text-white font-extrabold py-4 rounded-xl text-base items-center justify-center gap-2.5 shadow-lg hidden lg:flex">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                ACESSAR INTERNET AGORA
                            </button>
                        </div>

                        <!-- Indicadores de confiança -->
                        <div class="mt-4 flex items-center justify-center gap-5 text-xs text-muted">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-green" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                Pagamento seguro
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-green" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                                PIX instantâneo
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-green" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Liberação automática
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Tutorial em vídeo -->
                <section class="bg-white rounded-xl border border-border shadow-card animate-slide-up-delay">
                    <button onclick="openVideoTutorial()" class="flex items-center justify-between p-4 w-full group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-red-pale rounded-xl flex items-center justify-center border border-red/20">
                                <svg class="w-5 h-5 text-red" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-ink">Como se conectar?</p>
                                <p class="text-[11px] text-muted">Assista o passo a passo</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-red group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </section>

                <!-- Voucher do motorista -->
                <section class="bg-white rounded-xl border border-border shadow-card animate-slide-up-delay">
                    <a href="{{ route('voucher.activate') }}{{ request()->has('mac') ? '?source=mikrotik&mac=' . request('mac') . '&ip=' . request('ip') : '' }}" class="flex items-center justify-between p-4 group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gold-pale rounded-xl flex items-center justify-center border border-gold/20">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-ink">Motorista? Ative seu voucher</p>
                                <p class="text-[11px] text-muted">Acesso gratuito com código</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-green group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </section>

                

            </div>
        </main>

        <!-- Footer -->
        <footer class="text-center py-5 px-4">
            <div class="flex items-center justify-center gap-2 mb-1">
                <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0"/></svg>
                <p class="text-[11px] text-gray-300 font-medium">WiFi Tocantins Express</p>
            </div>
            <p class="text-[10px] text-gray-300/60">Internet de alta velocidade via Starlink</p>
        </footer>
    </div>

    <!-- Modal Vídeo Tutorial -->
    <div id="video-tutorial-modal" class="fixed inset-0 bg-black/90 z-50 hidden flex-col lg:items-center lg:justify-center">
        <div class="hidden lg:flex flex-col w-full max-w-2xl bg-gray-900 rounded-2xl overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between px-5 py-3 bg-gray-800">
                <p class="text-white font-semibold text-sm">Como se conectar ao WiFi</p>
                <button onclick="closeVideoTutorial()" class="flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold px-4 py-2 rounded-full transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    FECHAR
                </button>
            </div>
            <video id="tutorial-video-desktop" class="w-full max-h-[70vh] object-contain bg-black" autoplay controls playsinline preload="none">
                <source src="{{ asset('videos/CaptivePortalVideo.mp4') }}" type="video/mp4">
            </video>
        </div>
        <div class="flex flex-col flex-1 w-full lg:hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-gray-900 flex-shrink-0">
                <p class="text-white font-semibold text-sm">Como se conectar ao WiFi</p>
                <button onclick="closeVideoTutorial()" class="flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold px-4 py-2 rounded-full transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    FECHAR
                </button>
            </div>
            <div class="flex-1 flex items-center justify-center bg-black">
                <video id="tutorial-video" class="w-full h-full object-contain" autoplay controls playsinline preload="none">
                    <source src="{{ asset('videos/CaptivePortalVideo.mp4') }}" type="video/mp4">
                </video>
            </div>
        </div>
    </div>

    <script>
    function openVideoTutorial() {
        const modal = document.getElementById('video-tutorial-modal');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        const isDesktop = window.innerWidth >= 1024;
        const video = document.getElementById(isDesktop ? 'tutorial-video-desktop' : 'tutorial-video');
        if (video) { video.currentTime = 0; video.play(); }
    }
    function closeVideoTutorial() {
        const modal = document.getElementById('video-tutorial-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
        ['tutorial-video', 'tutorial-video-desktop'].forEach(id => { const v = document.getElementById(id); if (v) v.pause(); });
    }
    document.getElementById('video-tutorial-modal').addEventListener('click', function(e) { if (e.target === this) closeVideoTutorial(); });
    </script>

    <!-- Modal Vídeo Desconto -->
    @if($video_discount_enabled ?? false)
    <div id="video-discount-modal" class="fixed inset-0 bg-black/95 z-[60] hidden flex-col items-center justify-center">
        <div class="w-full max-w-lg mx-auto flex flex-col h-full sm:h-auto sm:max-h-[90vh]">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-700 flex-shrink-0 sm:rounded-t-2xl">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-white/20 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-white font-bold text-sm">Assista e ganhe desconto!</p>
                        <p class="text-purple-200 text-[10px]">Assista o vídeo completo (42s) para desbloquear</p>
                    </div>
                </div>
                <button onclick="closeVideoDiscount()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Barra de progresso -->
            <div class="bg-gray-800 px-4 py-2 flex items-center gap-3 flex-shrink-0">
                <div class="flex-1 bg-gray-700 rounded-full h-2.5 overflow-hidden">
                    <div id="video-discount-progress" class="bg-gradient-to-r from-purple-400 to-pink-400 h-full rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <span id="video-discount-timer" class="text-white text-xs font-bold min-w-[40px] text-right">0:42</span>
            </div>

            <!-- Vídeo -->
            <div class="flex-1 flex items-center justify-center bg-black sm:rounded-b-2xl overflow-hidden">
                <video id="discount-video" class="w-full h-full object-contain" playsinline preload="metadata"
                    oncontextmenu="return false;">
                    <source src="{{ asset('videos/video-tocantins.mp4') }}" type="video/mp4">
                </video>
            </div>

            <!-- Mensagem de conclusão (oculta inicialmente) -->
            <div id="video-discount-complete" class="hidden bg-gradient-to-r from-green-500 to-emerald-500 px-4 py-4 text-center flex-shrink-0 sm:rounded-b-2xl">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <p class="text-white font-extrabold text-base">Desconto desbloqueado!</p>
                </div>
                <p class="text-green-100 text-sm">R${{ number_format($video_discount_amount ?? 1, 2, ',', '.') }} de desconto aplicado ao seu plano</p>
                <button onclick="closeVideoDiscount()" class="mt-3 bg-white text-green-700 font-bold py-2.5 px-8 rounded-xl text-sm shadow-lg hover:bg-green-50 transition-colors">
                    CONTINUAR COM DESCONTO
                </button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const VIDEO_DISCOUNT_AMOUNT = {{ $video_discount_amount ?? 1 }};
        const VIDEO_DURATION = 42; // segundos
        let videoDiscountApplied = false;
        let videoWatchedFully = false;

        // Flag global para o selectWifiPlan saber que tem desconto ativo
        window.VIDEO_DISCOUNT_APPLIED = false;
        window.VIDEO_DISCOUNT_AMOUNT = 0;

        window.openVideoDiscount = function() {
            if (videoDiscountApplied) return;

            const modal = document.getElementById('video-discount-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            const video = document.getElementById('discount-video');
            if (video) {
                video.currentTime = 0;
                video.play().catch(() => {});
            }
        };

        window.closeVideoDiscount = function() {
            const modal = document.getElementById('video-discount-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';

            const video = document.getElementById('discount-video');
            if (video) video.pause();

            if (videoWatchedFully && !videoDiscountApplied) {
                applyVideoDiscount();
            }
        };

        function applyVideoDiscount() {
            videoDiscountApplied = true;
            window.VIDEO_DISCOUNT_APPLIED = true;
            window.VIDEO_DISCOUNT_AMOUNT = VIDEO_DISCOUNT_AMOUNT;

            // Mostrar badge de desconto aplicado, esconder botão
            const btn = document.getElementById('watch-video-discount-btn');
            const applied = document.getElementById('video-discount-applied');
            if (btn) btn.classList.add('hidden');
            if (applied) applied.classList.remove('hidden');

            // Atualizar preços visuais nos cards de plano
            document.querySelectorAll('[data-plan-option]').forEach(card => {
                const originalPrice = Number(card.dataset.planOriginalPrice || card.dataset.planPrice);
                // Guardar preço original se ainda não guardou
                if (!card.dataset.planOriginalPrice) {
                    card.dataset.planOriginalPrice = card.dataset.planPrice;
                }
                const newPrice = Math.max(0.01, originalPrice - VIDEO_DISCOUNT_AMOUNT);
                card.dataset.planPrice = String(newPrice);

                // Atualizar texto do preço no card
                const priceDisplay = card.querySelector('[data-plan-price-display]');
                if (priceDisplay) {
                    priceDisplay.textContent = 'R$' + newPrice.toFixed(2).replace('.', ',');
                }
            });

            // Re-selecionar plano atual para atualizar window.WIFI_PRICE e modal
            const selectedCard = document.querySelector('.plan-card-selected');
            if (selectedCard && typeof selectWifiPlan === 'function') {
                selectWifiPlan(selectedCard);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('discount-video');
            if (!video) return;

            const progressBar = document.getElementById('video-discount-progress');
            const timerEl = document.getElementById('video-discount-timer');
            const completeEl = document.getElementById('video-discount-complete');

            // Impedir seek (pular) no vídeo
            let lastValidTime = 0;
            video.addEventListener('seeking', function() {
                if (video.currentTime > lastValidTime + 1) {
                    video.currentTime = lastValidTime;
                }
            });

            video.addEventListener('timeupdate', function() {
                if (video.currentTime > lastValidTime) {
                    lastValidTime = video.currentTime;
                }

                const duration = video.duration || VIDEO_DURATION;
                const progress = Math.min((video.currentTime / duration) * 100, 100);
                if (progressBar) progressBar.style.width = progress + '%';

                const remaining = Math.max(0, Math.ceil(duration - video.currentTime));
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                if (timerEl) timerEl.textContent = mins + ':' + secs.toString().padStart(2, '0');
            });

            video.addEventListener('ended', function() {
                videoWatchedFully = true;

                if (progressBar) progressBar.style.width = '100%';
                if (timerEl) timerEl.textContent = '0:00';

                // Mostrar mensagem de conclusão
                if (completeEl) {
                    completeEl.classList.remove('hidden');
                    // Esconder o vídeo
                    video.parentElement.classList.add('hidden');
                }

                applyVideoDiscount();
            });

            // Impedir clique direito no vídeo
            video.addEventListener('contextmenu', function(e) { e.preventDefault(); });
        });
    })();
    </script>
    @endif

    <!-- Registration Modal -->
    <div id="registration-modal" class="fixed inset-0 bg-black/50 z-50 hidden backdrop-blur-sm">
        <div class="flex items-center justify-center h-full p-4">
            <div class="bg-white rounded-2xl p-6 sm:p-8 w-full max-w-md animate-slide-up shadow-xl">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Acesso rápido</h3>
                    <button id="close-registration-modal" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 transition-colors">&times;</button>
                </div>
                <p class="text-sm text-gray-500 mb-5">Informe seus dados para gerar o QR Code PIX.</p>
                <form id="registration-form" class="space-y-4">
                    <div id="registration-errors" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm"></div>
                    <div>
                        <label for="user_phone" class="block text-sm font-medium text-gray-700 mb-1.5">Telefone com DDD</label>
                        <input type="tel" id="user_phone" name="phone" required placeholder="(63) 9 8101-3050" maxlength="16" autofocus
                            class="w-full border border-gray-300 rounded-xl px-4 py-3.5 focus:outline-none focus:border-green focus:ring-2 focus:ring-green/20 transition-all text-base text-center font-medium">
                    </div>
                    <button type="submit" id="registration-submit-btn" class="connect-button w-full text-white font-bold py-3.5 rounded-xl shadow-md text-sm">
                        GERAR QR CODE PIX
                    </button>
                </form>
                <p class="text-center text-xs text-gray-400 mt-4">Pagamento seguro &bull; Liberação automática</p>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="fixed inset-0 bg-black/50 z-40 hidden backdrop-blur-sm">
        <div class="flex items-end sm:items-center justify-center h-full p-0 sm:p-4">
            <div class="bg-white rounded-t-2xl sm:rounded-2xl p-6 sm:p-8 w-full max-w-md animate-slide-up shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Pagamento PIX</h3>
                    <button id="close-modal" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 transition-colors">&times;</button>
                </div>
                <div class="bg-emerald-50 rounded-xl p-5 mb-5 text-center border border-emerald-100">
                    <p id="selected-plan-price" class="text-3xl font-extrabold text-emerald-700">R$6,99</p>
                    <p id="selected-plan-name" class="text-sm text-emerald-600 mt-1">Viagem completa / viagem</p>
                </div>
                <button data-payment="pix" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl transition-colors shadow-md text-sm">
                    PAGAR AGORA
                </button>
                <p class="text-center text-xs text-gray-400 mt-3">Pagamento seguro e instantâneo</p>
            </div>
        </div>
    </div>

    <script>
        // ===== VOUCHER SYSTEM =====
        document.addEventListener('DOMContentLoaded', function() {
            function applyVoucher(inputId, buttonId) {
                const input = document.getElementById(inputId);
                const button = document.getElementById(buttonId);
                if (!input || !button) return;
                const voucherCode = input.value.trim().toUpperCase();
                if (!voucherCode) { alert('Por favor, digite o código do voucher'); return; }
                button.disabled = true; button.textContent = '...';
                fetch('/api/voucher/validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ voucher_code: voucherCode })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(`${data.message}\n\nTipo: ${data.voucher_type === 'unlimited' ? 'Ilimitado' : 'Limitado'}\nHoras: ${data.hours_granted}h\nVálido até: ${new Date(data.expires_at).toLocaleString('pt-BR')}` +
                            (data.voucher_type === 'limited' ? `\nHoras restantes hoje: ${data.remaining_hours_today}h` : ''));
                        setTimeout(() => { window.location.href = 'https://www.google.com'; }, 2000);
                    } else { alert(data.message); button.disabled = false; button.textContent = 'OK'; }
                })
                .catch(() => { alert('Erro ao processar voucher. Tente novamente.'); button.disabled = false; button.textContent = 'OK'; });
            }
            ['mobile', 'desktop'].forEach(suffix => {
                const btn = document.getElementById(`apply-voucher-${suffix}`);
                if (btn) btn.addEventListener('click', () => applyVoucher(`voucher-code-${suffix}`, `apply-voucher-${suffix}`));
                const input = document.getElementById(`voucher-code-${suffix}`);
                if (input) input.addEventListener('keypress', e => { if (e.key === 'Enter') applyVoucher(`voucher-code-${suffix}`, `apply-voucher-${suffix}`); });
            });
        });
    </script>

    <script>
        window.WIFI_PRICE = {{ ($plan_full_enabled ?? true) ? ($wifi_price_full ?? 6.99) : ($wifi_price_short ?? 5.99) }};
        window.SESSION_DURATION = {{ ($plan_full_enabled ?? true) ? ($session_duration ?? 12) : ($session_duration_short ?? 1) }};
        window.WIFI_SELECTED_PLAN = null;

        function selectWifiPlan(card) {
            const amount = Number(card.dataset.planPrice);
            const duration = Number(card.dataset.planDuration);
            const name = card.dataset.planName;
            const suffix = card.dataset.planSuffix;
            window.WIFI_PRICE = amount;
            window.SESSION_DURATION = duration;
            window.WIFI_SELECTED_PLAN = { amount, duration, name, suffix };
            if (window.wifiPortal) window.wifiPortal.sessionDurationHours = duration;

            document.querySelectorAll('[data-plan-option]').forEach(option => {
                const selected = option === card;
                option.classList.toggle('plan-card-selected', selected);
                option.classList.toggle('border-gray-200', !selected);
                option.classList.toggle('bg-white', !selected);
                const radio = option.querySelector('[data-plan-radio]');
                if (radio) {
                    radio.classList.toggle('border-[5px]', selected);
                    radio.classList.toggle('border-green', selected);
                    radio.classList.toggle('border-2', !selected);
                    radio.classList.toggle('border-gray-300', !selected);
                    if (selected) { radio.classList.add('h-6', 'w-6'); radio.classList.remove('h-5', 'w-5'); }
                    else { radio.classList.add('h-5', 'w-5'); radio.classList.remove('h-6', 'w-6'); }
                }
            });

            const formatted = amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const priceEl = document.getElementById('selected-plan-price');
            const nameEl = document.getElementById('selected-plan-name');
            if (priceEl) priceEl.textContent = formatted;
            if (nameEl) nameEl.textContent = `${name} ${suffix}`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-plan-option]').forEach(card => {
                card.addEventListener('click', () => selectWifiPlan(card));
            });
            // Auto-selecionar plano: default primeiro, senão o primeiro disponível
            const defaultPlan = document.querySelector('[data-plan-default="true"]');
            const firstPlan = document.querySelector('[data-plan-option]');
            const planToSelect = defaultPlan || firstPlan;
            if (planToSelect) selectWifiPlan(planToSelect);
        });
    </script>

    <script src="{{ asset('js/mac-detector.js') }}?v={{ filemtime(public_path('js/mac-detector.js')) }}"></script>
    <script src="{{ asset('js/portal.js') }}?v={{ filemtime(public_path('js/portal.js')) }}"></script>

    @include('components.chat-widget')
</body>
</html>
