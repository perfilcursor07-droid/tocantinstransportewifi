<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro PIX Motorista - WiFi Tocantins</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,system-ui,sans-serif}</style>
</head>
<body class="bg-gradient-to-br from-emerald-50 via-white to-slate-100 min-h-screen py-8 px-4">
<div class="w-full max-w-lg mx-auto">
    <div class="text-center mb-8">
        <div class="w-14 h-14 bg-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-200">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800">Cadastro PIX — Motorista</h1>
        <p class="text-sm text-slate-500 mt-2 max-w-xs mx-auto">Preencha seus dados para receber pagamentos da empresa</p>
    </div>

    @if($errors->any())
    <div class="mb-5 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm space-y-1">
        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
    @endif

    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
        <form action="{{ route('driver-pix.store', $link->token) }}" method="POST">
            @csrf

            <div class="px-6 pt-6 pb-2">
                <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600 mb-4">Dados pessoais</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nome completo <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required placeholder="Seu nome completo"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white transition uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Telefone (WhatsApp) <span class="text-red-500">*</span></label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required placeholder="(63) 99999-9999"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Número do ônibus <span class="text-red-500">*</span></label>
                        <input type="text" name="bus_number" value="{{ old('bus_number') }}" required placeholder="Ex: 5013"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 focus:bg-white transition uppercase font-semibold tracking-wide">
                    </div>
                </div>
            </div>

            <div class="px-6 py-5 mt-2 bg-slate-50/80 border-t border-slate-100">
                <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600 mb-4">Chave PIX para receber</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Chave PIX <span class="text-red-500">*</span></label>
                        <input type="text" name="pix_key" value="{{ old('pix_key') }}" required placeholder="CPF, e-mail, telefone ou chave aleatória"
                               class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                        <p class="text-[11px] text-slate-400 mt-1.5">Use exatamente a mesma chave cadastrada no seu banco.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirmar chave PIX <span class="text-red-500">*</span></label>
                        <input type="text" name="pix_key_confirmation" value="{{ old('pix_key_confirmation') }}" required placeholder="Digite a chave PIX novamente"
                               class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                    </div>
                </div>
            </div>

            <div class="px-6 pb-6 pt-2">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white py-3.5 rounded-xl font-bold text-sm transition-all shadow-lg shadow-emerald-200/50 mt-4">
                    Enviar cadastro
                </button>
            </div>
        </form>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6 leading-relaxed">
        Após o envio, o administrador analisa e aprova seu cadastro.<br>Você será contatado pelo telefone informado.
    </p>
</div>
<script>
document.getElementById('phone')?.addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
    else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
    e.target.value = v;
});
document.querySelector('form')?.addEventListener('submit', function() {
    const name = this.querySelector('[name="full_name"]');
    const bus = this.querySelector('[name="bus_number"]');
    if (name) name.value = name.value.toUpperCase();
    if (bus) bus.value = bus.value.toUpperCase();
});
</script>
</body>
</html>
