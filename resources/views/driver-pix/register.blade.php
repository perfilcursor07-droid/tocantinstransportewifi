<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro PIX Motorista - WiFi Tocantins</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md">
    <div class="text-center mb-6">
        <div class="w-16 h-16 bg-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 class="text-xl font-bold text-gray-800">Cadastro PIX — Motorista</h1>
        <p class="text-sm text-gray-500 mt-1">Informe seus dados para receber pagamentos</p>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <form action="{{ route('driver-pix.store', $link->token) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" required placeholder="Seu nome completo"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Chave PIX <span class="text-red-500">*</span></label>
                <input type="text" name="pix_key" value="{{ old('pix_key') }}" required placeholder="CPF, e-mail, telefone ou chave aleatória"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <p class="text-[11px] text-gray-400 mt-1">Use a mesma chave do seu app do banco.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar chave PIX <span class="text-red-500">*</span></label>
                <input type="text" name="pix_key_confirmation" value="{{ old('pix_key_confirmation') }}" required placeholder="Digite a chave PIX novamente"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Número do ônibus <span class="text-red-500">*</span></label>
                <input type="text" name="bus_number" value="{{ old('bus_number') }}" required placeholder="Ex: 5013"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 uppercase">
            </div>
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl font-semibold text-sm transition-colors">
                Enviar cadastro
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-4">Após o envio, o administrador irá analisar e aprovar seu cadastro.</p>
</div>
<script>
document.querySelector('form')?.addEventListener('submit', function() {
    const name = this.querySelector('[name="full_name"]');
    const bus = this.querySelector('[name="bus_number"]');
    if (name) name.value = name.value.toUpperCase();
    if (bus) bus.value = bus.value.toUpperCase();
});
</script>
</body>
</html>
