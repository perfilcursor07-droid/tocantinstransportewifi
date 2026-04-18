<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Teste de Conexão</title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .step-pending { opacity: 0.4; }
        .step-running { opacity: 1; }
        .step-done    { opacity: 1; }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-500 to-teal-600 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6">

        <div class="text-center mb-6">
            <div class="w-20 h-20 mx-auto bg-emerald-100 rounded-full flex items-center justify-center mb-3">
                <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Teste de Conexão</h1>
            <p class="text-sm text-gray-500 mt-1">Vamos verificar sua internet em ~15 segundos.</p>
        </div>

        {{-- Tela inicial com botão --}}
        <div id="screen-start">
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 mb-5 text-sm text-emerald-800">
                <strong>ℹ️ O que vai acontecer:</strong>
                <ul class="mt-1 list-disc list-inside text-xs space-y-0.5">
                    <li>Vamos verificar se o DNS funciona</li>
                    <li>Testamos acesso a sites externos</li>
                    <li>Medimos velocidade de download</li>
                    <li>Medimos a latência (ping)</li>
                </ul>
                <p class="text-xs mt-2 text-emerald-700">Seu atendente vai receber o resultado automaticamente.</p>
            </div>
            <button id="btn-start" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-4 rounded-2xl font-bold text-lg shadow-lg transition">
                ▶ Iniciar teste
            </button>
        </div>

        {{-- Tela de progresso --}}
        <div id="screen-running" class="hidden">
            <div class="space-y-3" id="steps-list">
                <div class="step-pending flex items-center gap-3 p-3 rounded-xl bg-gray-50" data-step="laravel">
                    <div class="step-icon w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Servidor alcançável</span>
                </div>
                <div class="step-pending flex items-center gap-3 p-3 rounded-xl bg-gray-50" data-step="dns">
                    <div class="step-icon w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">DNS resolvendo</span>
                </div>
                <div class="step-pending flex items-center gap-3 p-3 rounded-xl bg-gray-50" data-step="google">
                    <div class="step-icon w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Internet externa (Google)</span>
                </div>
                <div class="step-pending flex items-center gap-3 p-3 rounded-xl bg-gray-50" data-step="download">
                    <div class="step-icon w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Velocidade de download</span>
                </div>
                <div class="step-pending flex items-center gap-3 p-3 rounded-xl bg-gray-50" data-step="latency">
                    <div class="step-icon w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Latência (ping)</span>
                </div>
            </div>
        </div>

        {{-- Tela final --}}
        <div id="screen-done" class="hidden text-center">
            <div id="verdict-icon" class="w-20 h-20 mx-auto rounded-full flex items-center justify-center mb-3 text-4xl"></div>
            <h2 id="verdict-title" class="text-xl font-bold text-gray-900"></h2>
            <p id="verdict-subtitle" class="text-sm text-gray-500 mt-1 mb-5"></p>
            <div id="verdict-details" class="bg-gray-50 rounded-xl p-4 text-left text-sm space-y-1.5 mb-4"></div>
            <p class="text-xs text-gray-400">Seu atendente recebeu o resultado.</p>
            <p id="close-countdown" class="text-xs text-emerald-600 font-semibold mt-2"></p>
            <button onclick="window.close()" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl font-bold text-sm shadow transition">
                ← Voltar ao chat
            </button>
        </div>

        {{-- Tela de erro irrecuperável --}}
        <div id="screen-error" class="hidden text-center">
            <div class="w-20 h-20 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3 text-4xl">❌</div>
            <h2 class="text-xl font-bold text-gray-900">Não foi possível completar o teste</h2>
            <p class="text-sm text-gray-500 mt-1" id="error-detail">Verifique sua conexão e tente novamente.</p>
            <button onclick="location.reload()" class="mt-4 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-semibold">Tentar de novo</button>
        </div>

    </div>

<script>
    const TOKEN = @json($probe->token);
    const REPORT_URL = @json(route('diagnostico.report', ['token' => $probe->token]));
    const PING_URL = @json(route('diagnostico.ping'));
    const DOWNLOAD_URL = @json(route('diagnostico.download'));

    const elStart = document.getElementById('screen-start');
    const elRun = document.getElementById('screen-running');
    const elDone = document.getElementById('screen-done');
    const elError = document.getElementById('screen-error');

    function setStep(stepKey, state, subtext) {
        const el = document.querySelector(`[data-step="${stepKey}"]`);
        if (!el) return;
        el.classList.remove('step-pending', 'step-running', 'step-done');
        el.classList.add('step-' + state);
        const icon = el.querySelector('.step-icon');
        if (state === 'running') {
            icon.className = 'step-icon w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0';
            icon.innerHTML = '<svg class="w-4 h-4 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke="currentColor" stroke-dasharray="40 100"/></svg>';
        } else if (state === 'done') {
            icon.className = 'step-icon w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0';
            icon.innerHTML = '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
        } else if (state === 'failed') {
            icon.className = 'step-icon w-8 h-8 rounded-full bg-red-500 flex items-center justify-center flex-shrink-0';
            icon.innerHTML = '<svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>';
        }
        if (subtext) {
            let sub = el.querySelector('[data-subtext]');
            if (!sub) {
                sub = document.createElement('span');
                sub.dataset.subtext = '1';
                sub.className = 'ml-auto text-xs font-mono text-gray-500';
                el.appendChild(sub);
            }
            sub.textContent = subtext;
        }
    }

    // Teste 1: Laravel alcançável (prova que é cliente do nosso sistema)
    async function testLaravel() {
        setStep('laravel', 'running');
        try {
            const res = await fetch(PING_URL, { cache: 'no-store' });
            const ok = res.ok;
            setStep('laravel', ok ? 'done' : 'failed', ok ? 'OK' : 'falhou');
            return ok;
        } catch (e) {
            setStep('laravel', 'failed', 'timeout');
            return false;
        }
    }

    // Teste 2: DNS resolvendo (fetch direto a IP público conhecido sem CORS)
    async function testDns() {
        setStep('dns', 'running');
        // Tenta carregar uma imagem 1x1 do Cloudflare — se resolve DNS e conecta, sucesso.
        // Usamos Image pois não sofre CORS (só precisamos saber se carregou)
        const ok = await new Promise(resolve => {
            const img = new Image();
            const timer = setTimeout(() => { img.src = ''; resolve(false); }, 5000);
            img.onload = () => { clearTimeout(timer); resolve(true); };
            img.onerror = () => { clearTimeout(timer); resolve(false); };
            img.src = 'https://1.1.1.1/cdn-cgi/trace?_=' + Date.now();
        });
        setStep('dns', ok ? 'done' : 'failed', ok ? 'OK' : 'falhou');
        return ok;
    }

    // Teste 3: Google (prova internet externa além do Cloudflare)
    async function testGoogle() {
        setStep('google', 'running');
        const ok = await new Promise(resolve => {
            const img = new Image();
            const timer = setTimeout(() => { img.src = ''; resolve(false); }, 5000);
            img.onload = () => { clearTimeout(timer); resolve(true); };
            img.onerror = () => { clearTimeout(timer); resolve(false); };
            img.src = 'https://www.google.com/favicon.ico?_=' + Date.now();
        });
        setStep('google', ok ? 'done' : 'failed', ok ? 'OK' : 'falhou');
        return ok;
    }

    // Teste 4: Download (512KB do servidor)
    async function testDownload() {
        setStep('download', 'running');
        try {
            const start = performance.now();
            const res = await fetch(DOWNLOAD_URL + '?_=' + Date.now(), { cache: 'no-store' });
            const blob = await res.blob();
            const ms = performance.now() - start;
            const bytes = blob.size;
            const mbps = (bytes * 8) / (ms / 1000) / 1_000_000;
            setStep('download', 'done', mbps.toFixed(1) + ' Mbps');
            return { mbps, ms };
        } catch (e) {
            setStep('download', 'failed', 'falhou');
            return null;
        }
    }

    // Teste 5: Latência (5 pings, média)
    async function testLatency() {
        setStep('latency', 'running');
        const samples = [];
        for (let i = 0; i < 5; i++) {
            try {
                const start = performance.now();
                await fetch(PING_URL + '?_=' + Date.now() + '_' + i, { cache: 'no-store' });
                samples.push(performance.now() - start);
            } catch (e) {
                samples.push(null);
            }
        }
        const valid = samples.filter(s => s !== null);
        if (valid.length === 0) {
            setStep('latency', 'failed', 'falhou');
            return { avg: null, samples };
        }
        const avg = valid.reduce((a, b) => a + b, 0) / valid.length;
        setStep('latency', 'done', Math.round(avg) + ' ms');
        return { avg, samples: valid };
    }

    async function runAll() {
        elStart.classList.add('hidden');
        elRun.classList.remove('hidden');

        const laravel_ok = await testLaravel();

        // Se nem o Laravel responde, o próprio teste não consegue ser submetido — aborta
        if (!laravel_ok) {
            elRun.classList.add('hidden');
            elError.classList.remove('hidden');
            document.getElementById('error-detail').textContent = 'Sem acesso ao servidor. Seu atendente já vai saber.';
            // Tenta submeter o que dá (pode falhar silenciosamente)
            try { await submitResults({ laravel_ok: false, dns_ok: false, google_ok: false }); } catch (e) {}
            return;
        }

        const dns_ok = await testDns();
        const google_ok = await testGoogle();
        const download = await testDownload();
        const latency = await testLatency();

        const payload = {
            laravel_ok: true,
            dns_ok,
            google_ok,
            download_mbps: download ? download.mbps : null,
            download_ms: download ? download.ms : null,
            latency_ms: latency.avg,
            latency_samples: latency.samples,
            client_ts: Date.now(),
            screen: `${screen.width}x${screen.height}`,
            connection_type: (navigator.connection?.effectiveType) || null,
        };

        await submitResults(payload);
        showFinalScreen(payload);
    }

    async function submitResults(payload) {
        try {
            await fetch(REPORT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });
        } catch (e) {
            console.error('Erro submetendo resultado', e);
        }
    }

    function showFinalScreen(p) {
        elRun.classList.add('hidden');
        elDone.classList.remove('hidden');

        const downloadOk = p.download_mbps && p.download_mbps > 0;
        const allGood = p.dns_ok && p.google_ok && downloadOk;
        const excellent = allGood && p.download_mbps >= 10 && p.latency_ms < 200;

        const iconEl = document.getElementById('verdict-icon');
        const title = document.getElementById('verdict-title');
        const sub = document.getElementById('verdict-subtitle');

        if (excellent) {
            iconEl.className = 'w-20 h-20 mx-auto bg-emerald-100 rounded-full flex items-center justify-center mb-3 text-4xl';
            iconEl.textContent = '🚀';
            title.textContent = 'Tudo funcionando!';
            title.className = 'text-xl font-bold text-emerald-700';
            sub.textContent = 'Sua conexão está excelente.';
        } else if (allGood) {
            iconEl.className = 'w-20 h-20 mx-auto bg-emerald-100 rounded-full flex items-center justify-center mb-3 text-4xl';
            iconEl.textContent = '✅';
            title.textContent = 'Internet OK';
            title.className = 'text-xl font-bold text-emerald-700';
            sub.textContent = 'Conexão funcionando, talvez com velocidade limitada.';
        } else {
            iconEl.className = 'w-20 h-20 mx-auto bg-red-100 rounded-full flex items-center justify-center mb-3 text-4xl';
            iconEl.textContent = '❌';
            title.textContent = 'Problema detectado';
            title.className = 'text-xl font-bold text-red-700';
            sub.textContent = 'Seu atendente vai te ajudar.';
        }

        const details = document.getElementById('verdict-details');
        details.innerHTML = `
            <div class="flex justify-between"><span class="text-gray-500">DNS:</span><span class="font-mono ${p.dns_ok ? 'text-emerald-600' : 'text-red-600'}">${p.dns_ok ? '✅ OK' : '❌ falhou'}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Google:</span><span class="font-mono ${p.google_ok ? 'text-emerald-600' : 'text-red-600'}">${p.google_ok ? '✅ OK' : '❌ falhou'}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Download:</span><span class="font-mono ${downloadOk ? 'text-emerald-600' : 'text-red-600'}">${downloadOk ? p.download_mbps.toFixed(1) + ' Mbps' : '❌ falhou'}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Latência:</span><span class="font-mono ${p.latency_ms !== null ? 'text-emerald-600' : 'text-red-600'}">${p.latency_ms !== null ? Math.round(p.latency_ms) + ' ms' : '❌ falhou'}</span></div>
        `;

        // Auto-fechar aba após 5 segundos (volta pro chat)
        let countdown = 5;
        const countdownEl = document.getElementById('close-countdown');
        countdownEl.textContent = `Fechando em ${countdown}s...`;
        const timer = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(timer);
                countdownEl.textContent = 'Fechando...';
                window.close();
                // Fallback: se window.close() não funcionar (restrição do navegador),
                // redirecionar para o portal
                setTimeout(() => {
                    window.location.href = '/';
                }, 500);
            } else {
                countdownEl.textContent = `Fechando em ${countdown}s...`;
            }
        }, 1000);
    }

    document.getElementById('btn-start').addEventListener('click', runAll);
</script>

</body>
</html>
