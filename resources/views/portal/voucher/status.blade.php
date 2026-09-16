@extends('portal.layout')

@section('content')
<div class="min-h-screen bg-[#F8F9FA] py-8 px-4" style="font-family:'Inter',sans-serif">
    <div class="container mx-auto px-4 max-w-md">
        <!-- Logo/Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl {{ session('success') ? 'bg-gradient-to-br from-[#00A335] to-[#00C040]' : 'bg-gradient-to-br from-[#007A28] to-[#00A335]' }} shadow-[0_4px_12px_rgba(0,0,0,0.1)] mb-3">
                @if(session('success'))
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                @else
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                @endif
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-[#00A335] mb-0.5">Starlink · Tocantins Transporte</p>
            <h1 class="text-xl font-bold text-[#111] leading-tight">{{ session('success') ? 'Voucher ativado' : 'Status do Voucher' }}</h1>
            <p class="text-xs text-[#888] mt-1">{{ session('success') ? 'Agora voce ja pode navegar' : 'Consulte seu acesso por CPF ou voucher' }}</p>
        </div>

        <!-- Mensagens -->
        @if (session('success'))
            <div class="mb-4 overflow-hidden rounded-2xl border border-[#00A335]/20 bg-white shadow-[0_18px_50px_rgba(0,0,0,0.08)]">
                <div class="bg-gradient-to-r from-[#007A28] via-[#00A335] to-[#00C040] px-5 py-4 text-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="text-base font-extrabold leading-tight">Voucher ativado com sucesso!</p>
                            <p class="text-xs text-white/80 mt-0.5">Seu acesso foi liberado. Agora voce pode navegar.</p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4">
                    <p class="text-xs leading-5 text-[#555]">
                        Se a internet nao liberar em alguns segundos, desligue e ligue o Wi-Fi do celular ou abra uma nova pagina no navegador.
                    </p>
                </div>
            </div>
            <script>
                // Notificar app Android quando voucher for ativado
                (function() {
                    console.log('Status: Verificando AndroidApp interface...');
                    
                    if (window.AndroidApp && typeof window.AndroidApp.showConnectionNotification === 'function') {
                        console.log('Status: AndroidApp detectado! Enviando notificação...');
                        
                        var successMessage = "{{ session('success') }}";
                        console.log('Status: Mensagem:', successMessage);
                        
                        var timeMatch = successMessage.match(/(\d+)\s*(hora|horas|minuto|minutos|dia|dias)/i);
                        var timeText = "";
                        
                        if (timeMatch) {
                            var amount = timeMatch[1];
                            var unit = timeMatch[2].toLowerCase();
                            
                            if (unit.includes('hora')) {
                                timeText = amount + (amount == 1 ? " hora" : " horas");
                            } else if (unit.includes('minuto')) {
                                timeText = amount + (amount == 1 ? " minuto" : " minutos");
                            } else if (unit.includes('dia')) {
                                timeText = amount + (amount == 1 ? " dia" : " dias");
                            }
                        }
                        
                        console.log('Status: Tempo extraído:', timeText || 'Nenhum');
                        window.AndroidApp.showConnectionNotification(timeText || "");
                        console.log('Status: Notificação enviada!');
                    }
                })();
            </script>
        @endif

        @if (session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <span class="text-xl mr-2">❌</span>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @if (!isset($user))
            <!-- Formulário de Consulta -->
            <div class="bg-white rounded-2xl border border-[#E5E5E5] overflow-hidden shadow-[0_18px_50px_rgba(0,0,0,0.08)]">
                <div class="px-5 py-3 bg-[#111] text-white">
                    <p class="text-sm font-bold">Consultar status</p>
                    <p class="text-[10px] text-white/60 mt-0.5">Confira se o voucher esta ativo neste aparelho</p>
                </div>
                <form action="{{ route('voucher.status.check') }}" method="POST">
                    @csrf

                    <div class="p-5 pb-0">
                        <label for="driver_document" class="block text-[11px] font-semibold text-[#333] uppercase tracking-wider mb-1.5">
                            CPF ou número do voucher
                        </label>
                        <input 
                            type="text" 
                            id="driver_document" 
                            name="driver_document" 
                            class="w-full px-4 py-3.5 text-center text-lg font-bold text-[#111] bg-[#F8F9FA] border border-[#E5E5E5] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#00A335]/30 focus:border-[#00A335] transition-all placeholder:text-[#888] placeholder:font-normal placeholder:text-base"
                            placeholder="CPF ou número do voucher"
                            value="{{ $document ?? $phone ?? '' }}"
                            required
                            maxlength="30"
                        >
                        <p class="text-[10px] text-[#888] mt-1 text-center">Use o CPF cadastrado ou o codigo do voucher.</p>
                    </div>

                    <button 
                        type="submit" 
                        class="m-5 mt-4 w-[calc(100%-2.5rem)] bg-[#00A335] hover:bg-[#00C040] active:bg-[#007A28] text-white font-bold py-3.5 px-6 rounded-xl shadow-[0_4px_12px_rgba(0,163,53,0.3)] transition-all flex items-center justify-center gap-2 text-sm"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span>Verificar Status</span>
                    </button>
                </form>

                <div class="px-5 pb-5 text-center">
                    <a href="{{ route('voucher.activate') }}" class="text-xs text-[#888] hover:text-[#00A335] transition">
                        ← Voltar para ativar voucher
                    </a>
                </div>
            </div>
        @else
            <!-- Exibir Status do Voucher -->
            <div class="space-y-6">
                <!-- Card de Status Principal -->
                <div class="bg-white rounded-3xl p-8 shadow-2xl">
                    <!-- Status Badge -->
                    <div class="flex items-center justify-center mb-6">
                        @if ($isActive)
                            <div class="bg-green-100 text-green-800 px-6 py-3 rounded-full font-bold text-lg flex items-center gap-2">
                                <span class="text-2xl">✅</span>
                                <span>VOUCHER ATIVO</span>
                            </div>
                        @else
                            <div class="bg-red-100 text-red-800 px-6 py-3 rounded-full font-bold text-lg flex items-center gap-2">
                                <span class="text-2xl">❌</span>
                                <span>VOUCHER EXPIRADO</span>
                            </div>
                        @endif
                    </div>

                    <!-- Informações do Voucher -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b">
                            <span class="text-gray-600 font-medium">🪪 CPF</span>
                            <span class="font-bold text-gray-800">{{ $voucher->driver_document ?? $user->driver_phone ?? 'N/A' }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3 border-b">
                            <span class="text-gray-600 font-medium">🎫 Código</span>
                            <span class="font-bold text-gray-800">{{ $voucher->code ?? 'N/A' }}</span>
                        </div>

                        @if ($isActive)
                            <div class="flex justify-between items-center py-3 border-b">
                                <span class="text-gray-600 font-medium">✅ Status</span>
                                <span class="font-bold text-green-600">Conectado</span>
                            </div>
                        @endif

                        @if ($voucher)
                            @if ($voucher->expires_at)
                                <div class="flex justify-between items-center py-3 border-b">
                                    <span class="text-gray-600 font-medium">📆 Voucher Válido Até</span>
                                    <span class="font-semibold text-gray-800">{{ $voucher->expires_at->format('d/m/Y') }}</span>
                                </div>
                            @endif
                        @endif

                        <div class="flex justify-between items-center py-3 border-b">
                            <span class="text-gray-600 font-medium">📡 MAC Address</span>
                            <span class="font-mono text-sm text-gray-800">{{ $user->mac_address ?? 'N/A' }}</span>
                        </div>

                        <div class="flex justify-between items-center py-3">
                            <span class="text-gray-600 font-medium">🌐 IP Address</span>
                            <span class="font-mono text-sm text-gray-800">{{ $user->ip_address ?? 'N/A' }}</span>
                        </div>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="mt-6 space-y-3">
                        @if (!$isActive)
                            <a href="{{ route('voucher.activate') }}" class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-4 px-6 rounded-xl shadow-lg transition transform hover:scale-105 flex items-center justify-center gap-2">
                                <span class="text-xl">🎫</span>
                                <span>Ativar Novamente</span>
                            </a>
                        @endif

                        <button 
                            onclick="location.reload()" 
                            class="w-full border-2 border-gray-300 hover:border-blue-500 text-gray-700 hover:text-blue-600 font-semibold py-3 px-6 rounded-xl transition flex items-center justify-center gap-2"
                        >
                            <span class="text-xl">🔄</span>
                            <span>Atualizar Status</span>
                        </button>
                    </div>
                </div>

                <!-- Informações Adicionais -->
                @if ($isActive)
                    <div class="bg-green-50 rounded-2xl p-6 text-sm text-green-900">
                        <h3 class="font-bold mb-2 flex items-center gap-2">
                            <span class="text-lg">✅</span>
                            Conexão Ativa
                        </h3>
                        <p>Seu voucher está ativo e você pode navegar livremente.</p>
                    </div>
                @else
                    <div class="bg-yellow-50 rounded-2xl p-6 text-sm text-yellow-900">
                        <h3 class="font-bold mb-2 flex items-center gap-2">
                            <span class="text-lg">⚠️</span>
                            Voucher Expirado
                        </h3>
                        <p>Seu voucher expirou. Para continuar navegando, você precisa ativar o voucher novamente.</p>
                    </div>
                @endif

                <!-- Botão Voltar -->
                <div class="text-center">
                    <a href="{{ route('voucher.activate') }}" class="text-sm text-gray-600 hover:text-green-600 transition">
                        ← Voltar para página inicial
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formatar CPF enquanto digita
    const cpfInput = document.getElementById('driver_document');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let raw = e.target.value.trim();

            if (/[A-Za-z]/.test(raw)) {
                e.target.value = raw.toUpperCase();
                return;
            }

            let value = raw.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 9) {
                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1.$2.$3-$4');
            } else if (value.length > 6) {
                value = value.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1.$2.$3');
            } else if (value.length > 3) {
                value = value.replace(/^(\d{3})(\d{0,3})/, '$1.$2');
            }
            
            e.target.value = value;
        });
    }

    // Auto-refresh a cada 30 segundos se o voucher estiver ativo
    @if(isset($isActive) && $isActive)
        setInterval(function() {
            location.reload();
        }, 30000); // 30 segundos
    @endif
});
</script>

<style>
.elegant-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.container > div {
    animation: fadeIn 0.5s ease-out;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.bg-green-100 {
    animation: pulse 2s infinite;
}
</style>
@endsection
