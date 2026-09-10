@extends('portal.layout')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-gray-50 via-purple-50/30 to-cyan-50/30 py-10">
    <div class="container mx-auto px-4 max-w-4xl">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Olá, {{ $user->name ?? 'Passageiro' }} 👋</h1>
                <p class="text-gray-500 mt-1 text-sm">Gerencie suas cobranças PIX e conexão a bordo.</p>
            </div>
            <form action="{{ route('portal.logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm font-semibold text-tocantins-green hover:text-tocantins-dark-green transition-colors">Sair</button>
            </form>
        </div>

        <div id="interval-access-status" hidden class="mb-4 border-l-4 border-green-600 bg-green-50 px-4 py-3 text-sm" aria-live="polite">
            <p data-interval-message></p>
            <button type="button" hidden class="mt-2 font-bold text-green-700 underline">Verificar diária</button>
        </div>

        @if (session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl">
                {{ session('error') }}
            </div>
        @endif

        <!-- Botão de Acesso Rápido para Voucher (apenas se não for usuário pagante ativo) -->
        @if (!$latestPayment || $latestPayment->status !== 'completed')
            <div class="mb-6 bg-gradient-to-r from-green-100 to-blue-100 rounded-3xl p-6 shadow-xl border-2 border-green-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="bg-white rounded-full w-16 h-16 flex items-center justify-center shadow-lg">
                            <span class="text-3xl">🎫</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Ative seu voucher aqui</p>
                        </div>
                    </div>
                    @php
                        $macParam = request()->query('mac') ?? $user->mac_address ?? '';
                        $ipParam = request()->query('ip') ?? $user->ip_address ?? '';
                        $voucherUrl = route('voucher.activate');
                        if ($macParam && $ipParam) {
                            $voucherUrl .= '?source=mikrotik&mac=' . urlencode($macParam) . '&ip=' . urlencode($ipParam);
                        }
                    @endphp
                    <a href="{{ $voucherUrl }}" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition transform hover:scale-105 flex items-center gap-2">
                        <span>Ativar Voucher</span>
                        <span>→</span>
                    </a>
                </div>
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <div class="elegant-card rounded-3xl p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Pagamento atual</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ ($latestPayment && $latestPayment->status === 'completed') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ $latestPayment?->status ?? 'Nenhum' }}
                    </span>
                </div>

                @if ($latestPayment)
                    <p class="text-sm text-gray-500 mb-2">Valor</p>
                    <p class="text-3xl font-bold text-gray-900 mb-4">R$ {{ number_format($latestPayment->amount, 2, ',', '.') }}</p>

                    <p class="text-sm text-gray-500 mb-2">Última atualização</p>
                    <p class="text-sm text-gray-700 mb-4">{{ $latestPayment->updated_at->format('d/m/Y H:i') }}</p>

                    <div class="space-y-3">
                        <form action="{{ route('portal.dashboard.payments.regenerate') }}" method="POST">
                            @csrf
                            <input type="hidden" name="payment_id" value="{{ $latestPayment->status === 'pending' ? $latestPayment->id : '' }}">
                            <button type="submit" class="w-full connect-button flex items-center justify-center gap-2 py-3 text-white font-semibold rounded-xl">
                                {{ $latestPayment->status === 'pending' ? '🔄 Gerar Novo QR Code' : '🚀 Comprar Novamente' }}
                            </button>
                        </form>

                        <button type="button" class="w-full border border-tocantins-green rounded-xl py-3 text-tocantins-green font-semibold hover:bg-tocantins-green hover:text-white transition" data-action="show-qrcode" data-payment="{{ $latestPayment->id }}">
                            📱 Ver QR Code Atual
                        </button>
                    </div>
                @else
                    <p class="text-gray-500 text-sm">Nenhum pagamento localizado. Clique abaixo para gerar sua primeira cobrança.</p>
                    <form action="{{ route('portal.dashboard.payments.regenerate') }}" method="POST" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full connect-button flex items-center justify-center gap-2 py-3 text-white font-semibold rounded-xl">
                            🚀 Gerar QR Code
                        </button>
                    </form>
                @endif
            </div>

            <div class="elegant-card rounded-3xl p-6 shadow-2xl">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Seus dados</h2>
                <div class="space-y-4 text-sm text-gray-600">
                    <div>
                        <p class="text-gray-500 uppercase text-xs">Telefone</p>
                        <p class="font-semibold text-gray-800">{{ $user->phone ?? 'Não informado' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xs">E-mail</p>
                        <p class="font-semibold text-gray-800">{{ $user->email ?? 'Não informado' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xs">MAC address</p>
                        <p class="font-mono text-gray-800">{{ $user->mac_address ?? 'Aguardando conexão' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xs">IP interno</p>
                        <p class="font-mono text-gray-800">{{ $user->ip_address ?? 'Aguardando' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xs">Status</p>
                        <p class="font-semibold {{ $user->status === 'connected' ? 'text-green-600' : 'text-gray-600' }}">{{ ucfirst($user->status ?? 'offline') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Histórico de pagamentos</h2>
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-100 text-sm uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Data</th>
                            <th class="px-4 py-3 text-left">Valor</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-gray-700 divide-y divide-gray-100">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="px-4 py-3">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                                        @class([
                                            'bg-green-100 text-green-700' => $payment->status === 'completed',
                                            'bg-yellow-100 text-yellow-700' => $payment->status === 'pending',
                                            'bg-red-100 text-red-700' => $payment->status === 'failed' || $payment->status === 'cancelled',
                                            'bg-gray-100 text-gray-600' => $payment->status === 'offline',
                                        ])">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($payment->payment_type === 'pix')
                                        <button type="button" class="text-tocantins-green text-sm font-semibold hover:underline" data-action="show-qrcode" data-payment="{{ $payment->id }}">
                                            Ver QR Code
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Cartão</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-400 text-sm">Nenhum pagamento encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if (session('qr_code'))
    <script>
        window.__portalQrCode = @json(session('qr_code'));
        window.__portalGateway = @json(session('gateway'));
    </script>
@endif

@endsection

@push('scripts')
<script src="{{ asset('js/interval-plan.js') }}?v={{ filemtime(public_path('js/interval-plan.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.wifiPortal = {
            deviceMac: @json($user->mac_address),
            deviceIp: @json(request('ip') ?: $user->ip_address),
        };
        window.IntervalAccess.connect(window.wifiPortal);
    });
</script>
@endpush
