<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Tocantins — Assista a Copa na viagem</title>
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
    {{-- Tailwind servido LOCALMENTE (mesmo domínio do portal). Evita depender do CDN
         externo, que pode ser bloqueado pelo walled garden do captive portal e quebrar
         todo o CSS da página de vendas. --}}
    <script src="{{ asset('js/tailwind.play.js') }}"></script>
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
        .hero-copa {
            background: linear-gradient(160deg, #004d1a 0%, #007A28 35%, #00A335 70%, #009c3b 100%);
            position: relative; overflow: hidden;
        }
        .hero-copa::before {
            content: '';
            position: absolute; inset: 0;
            background: repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 8px,
                rgba(255,255,255,0.03) 8px,
                rgba(255,255,255,0.03) 16px
            );
            pointer-events: none;
        }
        .hero-copa > * { position: relative; z-index: 1; }
        .price-pill {
            background: linear-gradient(135deg, #fef9c3 0%, #fde047 100%);
            color: #14532d;
        }
        body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
    </style>
</head>
<body class="font-sans min-h-screen bg-surface">

    <!-- No-WiFi Warning Overlay -->
    <div id="no-wifi-warning" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden">
        <div class="flex items-center justify-center h-full p-4">
            <div class="bg-white rounded-2xl p-5 w-full max-w-sm animate-slide-up shadow-2xl text-center">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6 18L18 6"/>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900 mb-1">Desligue os dados móveis (4G)</h3>
                <p class="text-xs text-gray-500 mb-4">Para o pagamento liberar seu acesso, você precisa estar <strong>só no WiFi do ônibus</strong>.</p>

                <div class="text-left bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3 space-y-2">
                    <div class="flex items-start gap-2">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center">1</span>
                        <p class="text-[12px] text-gray-700"><strong>Desligue o 4G / dados móveis</strong> do celular.</p>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-500 text-white text-[11px] font-bold flex items-center justify-center">2</span>
                        <div>
                            <p class="text-[12px] text-gray-700">No Wi-Fi do celular, toque na rede:</p>
                            <span class="inline-flex items-center gap-1 mt-1 bg-emerald-50 text-emerald-800 font-bold text-[12px] px-2 py-1 rounded-md border border-emerald-300">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                                TocantinsTransporteWiFi
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 mb-4">
                    <p class="text-[11px] text-amber-800">
                        ⚠️ Se pagar com o 4G ligado, o sistema <strong>não reconhece seu aparelho</strong> e o acesso <strong>não é liberado</strong>.
                    </p>
                </div>

                <button id="no-wifi-retry-btn" onclick="retryWifiCheck()" class="connect-button w-full text-white font-bold py-3 rounded-xl shadow-md text-sm mb-2">
                    JÁ CONECTEI, VERIFICAR
                </button>
                <p class="text-[10px] text-gray-400">O pagamento só funciona pelo WiFi do ônibus</p>
            </div>
        </div>
    </div>

    <script>
    // 🎯 OPÇÃO B: O backend decide se o usuário está no WiFi do ônibus.
    // $on_hotspot vem do PortalController usando os mesmos sinais confiáveis
    // (URL do MikroTik, sessão verificada, IP do hotspot, usuário vinculado).
    // O JS só faz a verificação extra quando o usuário clica "Já conectei".
    window._ON_HOTSPOT = {{ ($on_hotspot ?? false) ? 'true' : 'false' }};

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
                    setTimeout(() => { btn.innerHTML = 'JÁ CONECTEI, VERIFICAR'; btn.classList.add('connect-button'); btn.classList.remove('bg-red-500'); btn.disabled = false; }, 2500);
                });
        };
        img.src = 'http://10.5.50.1/favicon.ico?t=' + Date.now();
        setTimeout(function() {
            if (!responded) {
                btn.innerHTML = 'WIFI NÃO DETECTADO!'; btn.classList.remove('connect-button'); btn.classList.add('bg-red-500');
                setTimeout(() => { btn.innerHTML = 'JÁ CONECTEI, VERIFICAR'; btn.classList.add('connect-button'); btn.classList.remove('bg-red-500'); btn.disabled = false; }, 2500);
            }
        }, 4000);
    }
    document.addEventListener('DOMContentLoaded', function() {
        // Se o backend já confirmou que está no WiFi do ônibus, não mostra aviso.
        if (window._ON_HOTSPOT || hasMikrotikContext()) {
            window._noWifiBlocked = false;
            return;
        }
        // Backend não detectou hotspot → mostrar aviso direto (sem fetch lento/falso positivo)
        showNoWifiWarning();
    });
    </script>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden">
        <div class="flex items-center justify-center h-full">
            <div class="bg-white rounded-2xl p-8 text-center shadow-xl">
                <div class="animate-spin rounded-full h-10 w-10 border-3 border-gray-200 border-t-brand-600 mx-auto mb-4"></div>
                <p id="loading-title" class="text-gray-800 font-semibold text-sm">Processando...</p>
                <p id="loading-subtitle" class="text-gray-400 text-xs mt-1">Por favor, aguarde</p>
                <p id="loading-hint" class="text-gray-400 text-[10px] mt-2 hidden">Se demorar mais de 30s, desligue o 4G e tente de novo.</p>
            </div>
        </div>
    </div>

    <div class="min-h-screen flex flex-col">

        <!-- Header -->
        <div class="hero-copa pt-3 pb-2.5 px-4 text-center">
            <div class="flex items-center justify-center gap-1.5 mb-1">
                <span class="inline-flex items-center gap-1 bg-yellow-400/95 text-green-900 text-[9px] font-extrabold uppercase tracking-wide px-2 py-0.5 rounded-full">
                    ⚽ Copa 2026
                </span>
                <span class="text-white/50 text-[9px]">·</span>
                <span class="text-white/80 text-[9px] font-semibold">Starlink 100+ Mbps</span>
            </div>
            <p class="text-white font-extrabold text-[15px] leading-tight">Assista todos os jogos na viagem</p>
            <p class="text-white/70 text-[10px] mt-0.5">Pague no PIX · libera em segundos · sem cadastro</p>
            @php
                $reviewAverage = $review_average ?? 0;
                $reviewCount = $review_count ?? 0;
                $passengersMonth = $passengers_30d ?? 0;
            @endphp
            @if($reviewCount >= 3 || $passengersMonth >= 20)
            <p class="mt-1.5 text-[9px] text-white/85 font-medium">
                @if($reviewCount >= 3)
                    ⭐ {{ number_format($reviewAverage, 1, ',', '.') }} ({{ number_format($reviewCount, 0, ',', '.') }} avaliações)
                @endif
                @if($reviewCount >= 3 && $passengersMonth >= 20)
                    <span class="text-white/40 mx-1">·</span>
                @endif
                @if($passengersMonth >= 20)
                    +{{ number_format($passengersMonth, 0, ',', '.') }} conectados/mês
                @endif
            </p>
            @endif
        </div>

        <!-- Conteudo Principal -->
        <main class="flex-1 px-4 pt-2.5 pb-5 sm:pt-4 sm:pb-8">
            <div class="max-w-lg mx-auto space-y-2.5 sm:space-y-4">

                <!-- Status de conexão: só avisa quando NÃO está na rede do ônibus -->
                @if(!($on_hotspot ?? false))
                <section class="bg-amber-50 border-2 border-amber-400 rounded-2xl px-4 py-3 shadow-card animate-slide-up">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-amber-400 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6 18L18 6"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-amber-900 font-extrabold text-sm leading-tight">Conecte no WiFi do ônibus para pagar</p>
                            <p class="text-amber-800 text-[11px] mt-0.5 leading-snug">Entre na rede <strong>"TocantinsTransporteWiFi"</strong> e depois pague. <button type="button" onclick="showNoWifiWarning()" class="font-bold underline underline-offset-2">Ver como</button></p>
                        </div>
                    </div>
                </section>
                @endif

                <!-- Portal não abriu sozinho? (iPhone/Android) -->
                <section class="bg-white rounded-xl border border-blue-200 shadow-card px-3 py-2.5">
                    <details class="group">
                        <summary class="text-[11px] font-bold text-blue-800 cursor-pointer list-none flex items-center justify-between gap-2">
                            <span>📱 A página não abriu sozinha ao conectar?</span>
                            <span class="text-blue-400 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <div class="mt-2 space-y-1.5 text-[10px] text-gray-600 leading-snug">
                            <p><strong>iPhone:</strong> Toque em <em>"Usar sem Internet"</em> ou <em>"Cancelar"</em> na notificação do WiFi, depois abra o <strong>Safari</strong> e digite:</p>
                            <p class="font-mono bg-gray-100 rounded px-2 py-1 text-[10px] break-all">http://10.5.50.1</p>
                            <p><strong>Android:</strong> Toque na notificação <em>"Fazer login na rede"</em> ou abra o Chrome e acesse o endereço acima.</p>
                            <p class="text-amber-700">⚠️ Desligue os <strong>dados móveis (4G)</strong> antes de pagar.</p>
                        </div>
                    </details>
                </section>

                <!-- Card de Planos -->
                <section class="bg-white rounded-2xl border border-border shadow-card overflow-hidden animate-slide-up">
                    <div class="px-4 py-3 sm:px-5 sm:py-4">
                        <div class="flex items-center justify-between mb-2.5">
                            <p class="text-xs font-bold text-ink">Escolha seu plano</p>
                            <span class="text-[9px] font-bold text-green-dark bg-green-pale px-2 py-0.5 rounded-full">PIX instantâneo</span>
                        </div>

                        <div class="space-y-2" id="wifi-plan-options">
                            @if($plan_short_enabled ?? true)
                            <!-- Plano 1 hora (compacto) -->
                            <button type="button" data-plan-option data-plan-price="{{ $wifi_price_short ?? 5.99 }}" data-plan-duration="{{ $session_duration_short ?? 1 }}" data-plan-name="{{ $session_duration_short ?? 1 }} hora(s) de acesso" data-plan-suffix="/ hora"
                                class="wifi-plan-card flex w-full items-center gap-2.5 rounded-xl border-2 border-gray-200 bg-white px-3 py-2.5 text-left transition-all duration-200 hover:border-green/40 focus:outline-none focus:ring-2 focus:ring-green/20">
                                <span data-plan-radio class="h-4 w-4 rounded-full border-2 border-gray-300 bg-white flex-shrink-0 transition-all duration-200"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-ink leading-tight">{{ $session_duration_short ?? 1 }}h de WiFi</p>
                                    <p class="text-[10px] text-muted">Ideal para 1 jogo</p>
                                </div>
                                <p data-plan-price-display class="text-base font-extrabold text-ink tracking-tight">R${{ number_format($wifi_price_short ?? 5.99, 2, ',', '.') }}</p>
                            </button>
                            @endif

                            @if($plan_full_enabled ?? true)
                            <!-- Plano Viagem Completa (PRÉ-SELECIONADO) -->
                            <button type="button" data-plan-option data-plan-price="{{ $wifi_price_full ?? 6.99 }}" data-plan-duration="{{ $session_duration ?? 12 }}" data-plan-name="Viagem completa" data-plan-suffix="/ viagem" data-plan-default="true"
                                class="wifi-plan-card plan-card-selected relative flex w-full rounded-xl border-2 border-green text-left transition-all duration-200 hover:shadow-hover focus:outline-none focus:ring-2 focus:ring-green/20">
                                <span class="absolute -top-2 right-3 price-pill text-[8px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full shadow-sm z-10">⚽ Melhor p/ Copa</span>
                                <div class="flex items-center gap-2.5 px-3 py-2.5">
                                    <span data-plan-radio class="h-4 w-4 rounded-full border-[4px] border-green bg-white flex-shrink-0 transition-all duration-200"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] font-extrabold text-ink leading-tight">Viagem completa</p>
                                        <p class="text-[10px] text-green-dark font-medium">Todos os jogos até chegar</p>
                                        @if(($savings ?? 0) > 0)
                                        <p class="text-[9px] text-amber-700 font-semibold mt-0.5">Economize R${{ number_format($savings, 2, ',', '.') }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="flex items-center gap-1 justify-end">
                                            <span class="text-[10px] text-gray-400 line-through">R${{ number_format($original_price ?? 9.99, 2, ',', '.') }}</span>
                                            <span class="text-[9px] font-bold text-white bg-red-500 rounded px-1 py-px leading-none">-{{ $discount_percentage ?? 30 }}%</span>
                                        </div>
                                        <p data-plan-price-display class="text-[22px] font-black text-green-dark tracking-tight leading-none mt-0.5">R${{ number_format($wifi_price_full ?? 6.99, 2, ',', '.') }}</p>
                                    </div>
                                </div>
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="border-t border-gray-100"></div>

                    <div class="px-4 py-3 sm:px-5 sm:py-4">
                        <!-- CTA primeiro: usuário vê plano + botão sem rolar -->
                        <div id="plan-cta-wrapper">
                            <button id="connect-btn"
                                class="connect-button btn-pulse w-full text-white font-extrabold py-3.5 rounded-xl text-[15px] flex flex-col items-center justify-center gap-0.5 shadow-lg lg:hidden">
                                <span>CONECTAR AGORA</span>
                                <span class="text-[10px] font-semibold text-white/80">Pague via PIX e navegue na hora · rápido e fácil</span>
                            </button>
                            <button id="connect-btn-desktop"
                                class="connect-button btn-pulse w-full text-white font-extrabold py-3.5 rounded-xl text-[15px] flex flex-col items-center justify-center gap-0.5 shadow-lg hidden lg:flex">
                                <span>CONECTAR AGORA</span>
                                <span class="text-[10px] font-semibold text-white/80">Pague via PIX e navegue na hora · rápido e fácil</span>
                            </button>
                        </div>

                        <p class="mt-1.5 text-center text-[9px] text-muted leading-tight">
                            🔒 Pagamento seguro · ⚡ libera em ~30s · 📱 suporte WhatsApp
                        </p>

                        <!-- Apps compatíveis (prova visual abaixo do CTA) -->
                        <p class="text-center text-[9px] text-muted font-medium mt-2.5 mb-1.5 uppercase tracking-wider">Assista onde quiser</p>
                        <div class="flex justify-center items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #833AB4, #E1306C, #F77737);" title="Instagram">
                                <svg class="w-[16px] h-[16px] text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 01-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 017.8 2m-.2 2A3.6 3.6 0 004 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 003.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 110 2.5 1.25 1.25 0 010-2.5M12 7a5 5 0 110 10 5 5 0 010-10m0 2a3 3 0 100 6 3 3 0 000-6z"/></svg>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-[#25D366] flex items-center justify-center" title="WhatsApp">
                                <svg class="w-[16px] h-[16px] text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-[#FF0000] flex items-center justify-center" title="YouTube">
                                <svg class="w-[16px] h-[16px] text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-[#1877F2] flex items-center justify-center" title="Facebook">
                                <svg class="w-[16px] h-[16px] text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-black flex items-center justify-center" title="TikTok">
                                <svg class="w-[16px] h-[16px] text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.46V13a8.28 8.28 0 005.58 2.17V11.7a4.83 4.83 0 01-3.77-1.24V6.69h3.77z"/></svg>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-black flex items-center justify-center" title="Netflix">
                                <svg class="w-[14px] h-[14px]" fill="#E50914" viewBox="0 0 24 24"><path d="M5.398 0v.006c3.028 8.556 5.37 15.175 8.348 23.596 2.344.058 4.85.398 4.854.398-2.8-7.924-5.923-16.747-8.487-24zm8.489 0v9.63L18.6 22.951c-.043-7.86-.004-15.913.002-22.95zM5.398 1.05V24c1.873-.225 2.81-.312 4.715-.398v-9.22z"/></svg>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Como funciona: 3 passos simples -->
                <section class="bg-white rounded-2xl border border-border shadow-card px-4 py-3 animate-slide-up-delay">
                    <div class="flex items-start justify-between gap-0.5">
                        <div class="flex flex-col items-center text-center flex-1 px-0.5">
                            <div class="w-9 h-9 rounded-full bg-green-pale text-green-dark flex items-center justify-center mb-1.5 border border-green/20">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                            </div>
                            <p class="text-[11px] font-bold text-ink leading-tight">Entre no WiFi</p>
                            <p class="text-[9px] text-muted leading-tight mt-0.5">rede do ônibus</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 flex-shrink-0 mt-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <div class="flex flex-col items-center text-center flex-1 px-0.5">
                            <div class="w-9 h-9 rounded-full bg-blue-pale text-blue text-sm font-extrabold flex items-center justify-center mb-1.5 border border-blue/20">2</div>
                            <p class="text-[11px] font-bold text-ink leading-tight">Pague no PIX</p>
                            <p class="text-[9px] text-muted leading-tight mt-0.5">copie e cole no banco</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 flex-shrink-0 mt-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <div class="flex flex-col items-center text-center flex-1 px-0.5">
                            <div class="w-9 h-9 rounded-full bg-green text-white flex items-center justify-center mb-1.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-[11px] font-bold text-ink leading-tight">Pronto!</p>
                            <p class="text-[9px] text-muted leading-tight mt-0.5">internet na hora</p>
                        </div>
                    </div>
                </section>

                <!-- Tutorial em vídeo -->
                <section class="bg-white rounded-xl border border-border shadow-card animate-slide-up-delay">
                    <button onclick="openVideoTutorial()" class="flex items-center justify-between p-3 w-full group">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-red-pale rounded-xl flex items-center justify-center border border-red/20">
                                <svg class="w-4 h-4 text-red" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-ink">Como se conectar?</p>
                                <p class="text-[10px] text-muted">Vídeo passo a passo</p>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-red group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </section>

                <!-- Já paguei mas sem internet? (recuperação rápida) -->
                <section class="bg-white rounded-xl border border-amber-200 shadow-card animate-slide-up-delay">
                    <button type="button" onclick="openRecoveryModal()" class="flex items-center justify-between p-3 w-full group">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-amber-50 rounded-xl flex items-center justify-center border border-amber-200">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-bold text-ink">Já paguei mas sem internet?</p>
                                <p class="text-[10px] text-muted">Recuperar com seu telefone</p>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-500 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </section>

                <!-- Voucher do motorista -->
                <section class="bg-white rounded-xl border border-border shadow-card animate-slide-up-delay">
                    <a href="{{ route('voucher.activate') }}{{ request()->has('mac') ? '?source=mikrotik&mac=' . request('mac') . '&ip=' . request('ip') : '' }}" class="flex items-center justify-between p-3 group">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 bg-gold-pale rounded-xl flex items-center justify-center border border-gold/20">
                                <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-ink">Motorista? Ative seu voucher</p>
                                <p class="text-[10px] text-muted">Acesso gratuito com código</p>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-green group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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

    <!-- Modal Escolha: Vídeo com Desconto OU Pagar Normal -->
    @if($video_discount_enabled ?? false)
    <div id="video-choice-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60] hidden">
        <div class="flex items-center justify-center h-full p-4">
            <div class="bg-white rounded-2xl w-full max-w-sm animate-slide-up shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-5 py-4 text-center">
                    <p class="text-white font-extrabold text-base">Quer pagar menos?</p>
                    <p class="text-emerald-100 text-xs mt-1">Assista um vídeo curto e ganhe desconto</p>
                </div>

                <div class="p-5 space-y-3">
                    <!-- Opção 1: Pular direto (preço normal) -->
                    <button id="btn-skip-video" onclick="skipVideoDiscount()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-2xl p-4 text-left transition-all active:scale-[0.98] border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 bg-gray-200 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-gray-800">Pular e pagar normal</p>
                                <p class="text-gray-500 text-xs mt-0.5">Sem vídeo, sem desconto</p>
                                <p class="text-gray-600 text-xs font-bold mt-1" id="video-choice-normal-price"></p>
                            </div>
                        </div>
                    </button>

                    <!-- Opção 2: Assistir vídeo com desconto -->
                    <button id="btn-watch-video" onclick="startVideoDiscount()" class="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-2xl p-4 text-left transition-all active:scale-[0.98] shadow-lg relative overflow-hidden">
                        <div class="absolute top-2 right-3 bg-yellow-400 text-yellow-900 text-[9px] font-extrabold px-2 py-0.5 rounded-full">ECONOMIZE</div>
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                            <div>
                                <p class="font-extrabold text-sm">Assistir vídeo (42s)</p>
                                <p class="text-purple-100 text-xs mt-0.5">Ganhe <strong>R${{ number_format($video_discount_amount ?? 1, 2, ',', '.') }} de desconto</strong> no plano</p>
                                <p class="text-yellow-300 text-xs font-bold mt-1" id="video-choice-discounted-price"></p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Player de Vídeo -->
    <div id="video-discount-modal" class="fixed inset-0 bg-black/95 z-[70] hidden flex-col items-center justify-center">
        <div class="w-full max-w-lg mx-auto flex flex-col h-full sm:h-auto sm:max-h-[90vh]">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-green-dark to-green flex-shrink-0 sm:rounded-t-2xl">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center backdrop-blur-sm">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-white font-bold text-sm">Assista até o final</p>
                        <p class="text-green-100/70 text-[10px]">Desconto liberado ao terminar o vídeo</p>
                    </div>
                </div>
                <button onclick="skipFromVideoPlayer()" class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-[11px] font-bold px-4 py-2 rounded-full transition-colors animate-pulse backdrop-blur-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    Pular agora
                </button>
            </div>

            <!-- Barra de progresso -->
            <div class="bg-gray-900 px-4 py-2.5 flex items-center gap-3 flex-shrink-0">
                <div class="flex-1 bg-gray-700/60 rounded-full h-3 overflow-hidden relative">
                    <div id="video-discount-progress" class="bg-gradient-to-r from-green to-green-light h-full rounded-full transition-all duration-300 relative" style="width: 0%">
                        <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3.5 h-3.5 bg-white rounded-full shadow-md border-2 border-green"></div>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 bg-gray-800 rounded-lg px-2.5 py-1 min-w-[52px] justify-center">
                    <svg class="w-3 h-3 text-green-light" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span id="video-discount-timer" class="text-white text-xs font-bold">0:42</span>
                </div>
            </div>

            <!-- Vídeo -->
            <div id="video-player-container" class="flex-1 flex items-center justify-center bg-black sm:rounded-b-2xl overflow-hidden">
                <video id="discount-video" class="w-full h-full object-contain" playsinline preload="none" oncontextmenu="return false;">
                    <source src="{{ asset('videos/video-tocantins.mp4') }}" type="video/mp4">
                </video>
            </div>

        </div>
    </div>

    <script>
    (function() {
        const VIDEO_DISCOUNT_AMOUNT = {{ $video_discount_amount ?? 1 }};
        let videoDiscountApplied = false;
        let videoWatchedFully = false;
        let pendingConnectAction = null; // guarda a callback do handleConnectClick original

        // Flags globais
        window.VIDEO_DISCOUNT_APPLIED = false;
        window.VIDEO_DISCOUNT_AMOUNT = 0;

        /**
         * Intercepta o clique em "ACESSAR INTERNET AGORA"
         * Se desconto por vídeo está ativo e ainda não foi usado, mostra modal de escolha.
         * Senão, segue o fluxo normal.
         */
        window.VIDEO_DISCOUNT_INTERCEPT = function(originalCallback) {
            if (videoDiscountApplied) {
                originalCallback();
                return;
            }
            pendingConnectAction = originalCallback;

            // Esconder loading overlay antes de mostrar o modal
            var loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) loadingOverlay.classList.add('hidden');

            const currentPrice = window.WIFI_PRICE || 6.99;
            const discountedPrice = Math.max(0.01, currentPrice - VIDEO_DISCOUNT_AMOUNT);
            const normalEl = document.getElementById('video-choice-normal-price');
            const discountEl = document.getElementById('video-choice-discounted-price');
            if (normalEl) normalEl.textContent = 'Pagar R$' + currentPrice.toFixed(2).replace('.', ',');
            if (discountEl) discountEl.textContent = 'Pagar apenas R$' + discountedPrice.toFixed(2).replace('.', ',');

            const modal = document.getElementById('video-choice-modal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };

        /** Usuário escolheu assistir o vídeo */
        window.startVideoDiscount = function() {
            // Fechar modal de escolha
            document.getElementById('video-choice-modal').classList.add('hidden');

            // Abrir player de vídeo
            const modal = document.getElementById('video-discount-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            const video = document.getElementById('discount-video');
            if (video) {
                // Forçar carregamento e play
                video.load();
                video.play().catch(function() {
                    // Autoplay bloqueado — mostrar botão de play
                    video.controls = true;
                });
            }
        };

        /** Usuário escolheu pular — preço normal, segue fluxo */
        window.skipVideoDiscount = function() {
            videoDiscountApplied = true; // marca como "já decidiu" para não perguntar de novo
            document.getElementById('video-choice-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';

            if (pendingConnectAction) {
                pendingConnectAction();
                pendingConnectAction = null;
            }
        };

        /** Fecha o player de vídeo (usuário fechou antes de terminar) */
        window.closeVideoDiscount = function() {
            var modal = document.getElementById('video-discount-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';

            var video = document.getElementById('discount-video');
            if (video) { video.pause(); video.controls = false; video.currentTime = 0; }
            var progressBar = document.getElementById('video-discount-progress');
            var timerEl = document.getElementById('video-discount-timer');
            if (progressBar) progressBar.style.width = '0%';
            if (timerEl) timerEl.textContent = '0:42';

            // Voltar ao modal de escolha
            document.getElementById('video-choice-modal').classList.remove('hidden');
        };

        /** Usuário clicou "Não quero assistir" dentro do player — pula direto pro pagamento */
        window.skipFromVideoPlayer = function() {
            videoDiscountApplied = true; // não perguntar de novo
            var modal = document.getElementById('video-discount-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';

            var video = document.getElementById('discount-video');
            if (video) { video.pause(); video.controls = false; }

            if (pendingConnectAction) {
                pendingConnectAction();
                pendingConnectAction = null;
            }
        };

        function applyVideoDiscount() {
            videoDiscountApplied = true;
            window.VIDEO_DISCOUNT_APPLIED = true;
            window.VIDEO_DISCOUNT_AMOUNT = VIDEO_DISCOUNT_AMOUNT;

            // Atualizar preços nos cards de plano
            document.querySelectorAll('[data-plan-option]').forEach(function(card) {
                var originalPrice = Number(card.dataset.planOriginalPrice || card.dataset.planPrice);
                if (!card.dataset.planOriginalPrice) {
                    card.dataset.planOriginalPrice = card.dataset.planPrice;
                }
                var newPrice = Math.max(0.01, originalPrice - VIDEO_DISCOUNT_AMOUNT);
                card.dataset.planPrice = String(newPrice);

                var priceDisplay = card.querySelector('[data-plan-price-display]');
                if (priceDisplay) {
                    priceDisplay.textContent = 'R$' + newPrice.toFixed(2).replace('.', ',');
                }
            });

            // Re-selecionar plano atual para atualizar window.WIFI_PRICE
            var selectedCard = document.querySelector('.plan-card-selected');
            if (selectedCard && typeof selectWifiPlan === 'function') {
                selectWifiPlan(selectedCard);
            }
        }

        // Setup do player de vídeo
        document.addEventListener('DOMContentLoaded', function() {
            var video = document.getElementById('discount-video');
            if (!video) return;

            var progressBar = document.getElementById('video-discount-progress');
            var timerEl = document.getElementById('video-discount-timer');

            // Impedir seek (pular)
            var lastValidTime = 0;
            video.addEventListener('seeking', function() {
                if (video.currentTime > lastValidTime + 1) {
                    video.currentTime = lastValidTime;
                }
            });

            video.addEventListener('timeupdate', function() {
                if (video.currentTime > lastValidTime) {
                    lastValidTime = video.currentTime;
                }
                var duration = video.duration || 42;
                var progress = Math.min((video.currentTime / duration) * 100, 100);
                if (progressBar) progressBar.style.width = progress + '%';

                var remaining = Math.max(0, Math.ceil(duration - video.currentTime));
                var mins = Math.floor(remaining / 60);
                var secs = remaining % 60;
                if (timerEl) timerEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
            });

            video.addEventListener('ended', function() {
                videoWatchedFully = true;
                lastValidTime = 0;
                if (progressBar) progressBar.style.width = '100%';
                if (timerEl) timerEl.textContent = '0:00';

                // Aplicar desconto e seguir direto pro pagamento
                applyVideoDiscount();

                // Fechar modal do vídeo e seguir o fluxo
                var modal = document.getElementById('video-discount-modal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = 'auto';
                var vid = document.getElementById('discount-video');
                if (vid) { vid.pause(); vid.controls = false; }

                if (pendingConnectAction) {
                    pendingConnectAction();
                    pendingConnectAction = null;
                }
            });

            video.addEventListener('contextmenu', function(e) { e.preventDefault(); });
        });
    })();
    </script>
    @endif

    <!-- Modal de Recuperação por Telefone -->
    <div id="recovery-modal" class="fixed inset-0 bg-black/60 z-[55] hidden backdrop-blur-sm">
        <div class="flex items-center justify-center h-full p-4">
            <div class="bg-white rounded-2xl w-full max-w-sm animate-slide-up shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-4 text-center">
                    <p class="text-white font-extrabold text-base">Recuperar acesso</p>
                    <p class="text-amber-50 text-xs mt-1">Já pagou mas não conseguiu conectar?</p>
                </div>

                <div class="p-5">
                    <div id="recovery-form-area">
                        <p class="text-sm text-gray-600 mb-4 text-center">
                            Informe o telefone que você usou para pagar. Vamos liberar seu acesso automaticamente.
                        </p>

                        <input type="tel" id="recovery-phone" placeholder="(63) 9 8101-3050" maxlength="16"
                            class="w-full border-2 border-gray-300 rounded-xl px-4 py-3.5 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all text-base text-center font-medium">

                        <div id="recovery-error" class="hidden mt-3 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-xs"></div>

                        <button id="recovery-submit-btn" onclick="submitRecovery()" class="mt-4 w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold py-3.5 rounded-xl shadow-md text-sm flex items-center justify-center gap-2 transition-all active:scale-[0.98]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            RECUPERAR ACESSO
                        </button>

                        <button onclick="closeRecoveryModal()" class="mt-2 w-full text-gray-500 text-xs py-2 hover:text-gray-700">
                            Cancelar
                        </button>
                    </div>

                    <!-- Tela de sucesso (escondida) -->
                    <div id="recovery-success-area" class="hidden text-center py-2">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-base font-extrabold text-green-700 mb-1">Acesso liberado!</p>
                        <p class="text-sm text-gray-600 mb-4" id="recovery-success-msg">Sua internet será liberada em até 30 segundos.</p>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 text-left">
                            <p class="text-xs font-bold text-blue-800 mb-2">Se ainda não funcionar, faça isso:</p>
                            <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                                <li>Desconecte do WiFi</li>
                                <li>Desligue o WiFi do celular</li>
                                <li>Ligue o WiFi de novo e reconecte</li>
                            </ol>
                        </div>

                        <button onclick="closeRecoveryModal()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-sm shadow-md">
                            FECHAR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        window.openRecoveryModal = function() {
            const modal = document.getElementById('recovery-modal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Reset
            document.getElementById('recovery-form-area').classList.remove('hidden');
            document.getElementById('recovery-success-area').classList.add('hidden');
            document.getElementById('recovery-error').classList.add('hidden');
            document.getElementById('recovery-phone').value = '';
            
            setTimeout(() => document.getElementById('recovery-phone').focus(), 100);
        };

        window.closeRecoveryModal = function() {
            document.getElementById('recovery-modal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        };

        // Máscara de telefone
        const phoneInput = document.getElementById('recovery-phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 11) v = v.substring(0, 11);
                if (v.length > 10) {
                    v = '(' + v.substring(0, 2) + ') ' + v.substring(2, 3) + ' ' + v.substring(3, 7) + '-' + v.substring(7);
                } else if (v.length > 6) {
                    v = '(' + v.substring(0, 2) + ') ' + v.substring(2, 6) + '-' + v.substring(6);
                } else if (v.length > 2) {
                    v = '(' + v.substring(0, 2) + ') ' + v.substring(2);
                }
                e.target.value = v;
            });
            phoneInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') submitRecovery();
            });
        }

        window.submitRecovery = function() {
            const input = document.getElementById('recovery-phone');
            const errorEl = document.getElementById('recovery-error');
            const btn = document.getElementById('recovery-submit-btn');
            const phone = (input.value || '').replace(/\D/g, '');

            errorEl.classList.add('hidden');

            if (phone.length < 10) {
                errorEl.textContent = 'Informe um telefone válido com DDD.';
                errorEl.classList.remove('hidden');
                return;
            }

            // Pegar MAC e IP atuais (do portal.js)
            const macAddress = (window.wifiPortal && window.wifiPortal.deviceMac) || '';
            const ipAddress = (window.wifiPortal && window.wifiPortal.deviceIp) || '';

            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="animate-pulse">⏳ VERIFICANDO...</span>';
            btn.disabled = true;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch('/api/reativar-acesso', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ phone, mac_address: macAddress, ip_address: ipAddress }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('recovery-form-area').classList.add('hidden');
                    document.getElementById('recovery-success-area').classList.remove('hidden');
                    if (data.message) {
                        document.getElementById('recovery-success-msg').textContent = data.message;
                    }
                } else {
                    errorEl.textContent = data.message || 'Não foi possível recuperar o acesso. Verifique o telefone.';
                    errorEl.classList.remove('hidden');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(() => {
                errorEl.textContent = 'Erro de conexão. Tente novamente.';
                errorEl.classList.remove('hidden');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        };

        // Fechar clicando fora
        document.getElementById('recovery-modal').addEventListener('click', function(e) {
            if (e.target === this) closeRecoveryModal();
        });
    })();
    </script>

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
                    <p id="selected-plan-name" class="text-sm text-emerald-600 mt-1">Viagem completa / ate o destino final</p>
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
        window.PORTAL_MAC = @json(request('mac') ?: request('mikrotik_mac') ?: request('client_mac'));
        window.PORTAL_IP = @json(request('ip') ?: request('client_ip'));
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

    @if($video_discount_enabled ?? false)
    <script>
    /**
     * Intercepta processPixPayment e processPixPaymentFast do WiFiPortal
     * para mostrar modal de escolha (vídeo com desconto OU preço normal)
     * DEPOIS do cadastro de telefone, antes de gerar o QR Code PIX.
     */
    document.addEventListener('DOMContentLoaded', function() {
        var checkPortal = setInterval(function() {
            if (window.wifiPortal) {
                clearInterval(checkPortal);

                // Guardar métodos originais
                var originalProcessPix = window.wifiPortal.processPixPayment.bind(window.wifiPortal);
                var originalProcessPixFast = window.wifiPortal.processPixPaymentFast.bind(window.wifiPortal);

                // Interceptar processPixPayment (usuário já cadastrado)
                window.wifiPortal.processPixPayment = function() {
                    if (typeof window.VIDEO_DISCOUNT_INTERCEPT === 'function') {
                        window.VIDEO_DISCOUNT_INTERCEPT(originalProcessPix);
                    } else {
                        originalProcessPix();
                    }
                };

                // Interceptar processPixPaymentFast (após cadastro de telefone)
                window.wifiPortal.processPixPaymentFast = function() {
                    if (typeof window.VIDEO_DISCOUNT_INTERCEPT === 'function') {
                        window.VIDEO_DISCOUNT_INTERCEPT(originalProcessPixFast);
                    } else {
                        originalProcessPixFast();
                    }
                };
            }
        }, 100);
    });
    </script>
    @endif

    @include('components.chat-widget')
</body>
</html>
