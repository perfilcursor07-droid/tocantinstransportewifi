<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Diagnostico de Acesso WiFi</title>
    <script src="{{ asset('js/tailwind.play.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            ink: '#10212B',
                            pine: '#0F766E',
                            mint: '#C8F2E6',
                            sand: '#F4E8C1',
                            coral: '#F27F5C',
                        },
                    },
                },
            },
        };
    </script>
    <style>
        body {
            background:
                radial-gradient(circle at top left, rgba(242, 127, 92, 0.14), transparent 30%),
                radial-gradient(circle at top right, rgba(15, 118, 110, 0.18), transparent 35%),
                linear-gradient(180deg, #f6f2ea 0%, #eef7f4 55%, #ffffff 100%);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.84);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 33, 43, 0.08);
            box-shadow: 0 16px 40px rgba(16, 33, 43, 0.08);
        }

        .status-ring {
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5), 0 10px 24px rgba(16, 33, 43, 0.08);
        }
    </style>
</head>
<body class="font-sans text-brand-ink min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-6 sm:px-6 lg:px-8">

        <main class="grid gap-6 lg:grid-cols-[380px_1fr]">
            <section class="glass-card rounded-[28px] p-5 sm:p-6 h-fit">
                <div class="flex items-center justify-between gap-3 mb-5">
                    <div>
                        <h2 class="text-lg font-extrabold">Consultar acesso</h2>
                        <p class="text-sm text-slate-500 mt-1">Telefone ajuda no cruzamento, mas nao e obrigatorio.</p>
                    </div>
                    <div id="connection-badge" class="status-ring rounded-full px-3 py-1.5 text-xs font-bold bg-amber-100 text-amber-800">
                        Aguardando consulta
                    </div>
                </div>

                <form id="diagnostic-form" class="space-y-4">
                    <div>
                        <label for="phone" class="block text-sm font-semibold mb-2">Telefone cadastrado</label>
                        <input id="phone" name="phone" type="tel" placeholder="(63) 99999-9999" maxlength="20" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 outline-none focus:border-brand-pine focus:ring-4 focus:ring-brand-pine/10">
                    </div>

                    <div>
                        <label for="ip_address" class="block text-sm font-semibold mb-2">IP detectado</label>
                        <input id="ip_address" name="ip_address" type="text" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-slate-700">
                    </div>

                    <div>
                        <label for="mac_address" class="block text-sm font-semibold mb-2">MAC detectado</label>
                        <input id="mac_address" name="mac_address" type="text" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-slate-700">
                    </div>

                    <button id="submit-button" type="submit" class="w-full rounded-2xl bg-slate-950 text-white font-extrabold py-3.5 transition hover:bg-slate-800 disabled:opacity-60 disabled:cursor-not-allowed">
                        Analisar meu acesso
                    </button>
                </form>

                <div id="helper-box" class="mt-5 rounded-2xl bg-brand-sand/60 border border-brand-sand px-4 py-4 text-sm text-slate-700 leading-6">
                    Se o MAC vier vazio, peca para o passageiro desligar os dados moveis, reconectar ao Wi-Fi e abrir esta pagina novamente.
                </div>
            </section>

            <section class="space-y-6">
                <div id="result-empty" class="glass-card rounded-[28px] p-8 sm:p-10 text-center">
                    <div class="mx-auto w-16 h-16 rounded-full bg-brand-mint flex items-center justify-center text-2xl mb-4">?</div>
                    <h2 class="text-xl font-extrabold">Nenhuma consulta executada ainda</h2>
                    <p class="mt-3 text-slate-500 max-w-xl mx-auto leading-7">
                        Assim que o passageiro consultar, esta tela mostra o telefone vinculado, o MAC/IP atuais, o ultimo pagamento conhecido e se o acesso esta realmente liberado no sistema.
                    </p>
                </div>

                <div id="result-panel" class="hidden space-y-6">
                    <div class="glass-card rounded-[28px] p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-bold">Compartilhar com suporte</p>
                                <p class="mt-1 text-sm text-slate-600">Clique em enviar relatorio para copiar um resumo pronto e colar no WhatsApp do administrador.</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span id="copy-feedback" class="hidden text-xs font-bold text-emerald-700">Relatorio copiado.</span>
                                <button id="copy-report-button" type="button" class="rounded-2xl bg-brand-pine text-white font-extrabold px-5 py-3 transition hover:bg-teal-700 disabled:opacity-60 disabled:cursor-not-allowed">
                                    Enviar relatorio
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="summary-card" class="glass-card rounded-[28px] p-6 sm:p-7"></div>
                    <div id="warning-list" class="hidden glass-card rounded-[28px] p-6"></div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <div id="user-card" class="glass-card rounded-[28px] p-6"></div>
                        <div id="device-card" class="glass-card rounded-[28px] p-6"></div>
                        <div id="payment-card" class="glass-card rounded-[28px] p-6"></div>
                        <div id="session-card" class="glass-card rounded-[28px] p-6"></div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        const initialState = {
            phone: @json($prefillPhone),
            ip: @json($prefillIp),
            mac: @json($prefillMac),
            lookupUrl: @json(route('support.diagnostics.lookup')),
            csrf: document.querySelector('meta[name="csrf-token"]').content,
        };

        const form = document.getElementById('diagnostic-form');
        const submitButton = document.getElementById('submit-button');
        const resultEmpty = document.getElementById('result-empty');
        const resultPanel = document.getElementById('result-panel');
        const summaryCard = document.getElementById('summary-card');
        const warningList = document.getElementById('warning-list');
        const userCard = document.getElementById('user-card');
        const deviceCard = document.getElementById('device-card');
        const paymentCard = document.getElementById('payment-card');
        const sessionCard = document.getElementById('session-card');
        const connectionBadge = document.getElementById('connection-badge');
        const copyReportButton = document.getElementById('copy-report-button');
        const copyFeedback = document.getElementById('copy-feedback');
        let latestDiagnosticData = null;

        document.getElementById('phone').value = formatPhone(initialState.phone || '');
        document.getElementById('ip_address').value = initialState.ip || 'Nao detectado';
        document.getElementById('mac_address').value = initialState.mac || 'Nao detectado';

        document.getElementById('phone').addEventListener('input', (event) => {
            event.target.value = formatPhone(event.target.value);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            await runLookup();
        });

        copyReportButton.addEventListener('click', async () => {
            const reportText = buildDiagnosticReportText(latestDiagnosticData);

            if (!reportText) {
                showCopyFeedback('Faça a consulta antes de enviar o relatorio.', true);
                return;
            }

            const originalText = copyReportButton.textContent;
            copyReportButton.disabled = true;
            copyReportButton.textContent = 'Copiando...';

            try {
                await copyText(reportText);
                showCopyFeedback('Relatorio copiado. Agora cole no WhatsApp do administrador.', false);
            } catch (error) {
                showCopyFeedback('Nao foi possivel copiar automaticamente. Tente novamente.', true);
            } finally {
                copyReportButton.disabled = false;
                copyReportButton.textContent = originalText;
            }
        });

        if (initialState.mac || initialState.phone) {
            runLookup();
        }

        async function runLookup() {
            const payload = {
                phone: document.getElementById('phone').value,
                ip_address: normalizeField(document.getElementById('ip_address').value),
                mac_address: normalizeField(document.getElementById('mac_address').value),
            };

            submitButton.disabled = true;
            submitButton.textContent = 'Consultando...';
            connectionBadge.className = 'status-ring rounded-full px-3 py-1.5 text-xs font-bold bg-slate-200 text-slate-700';
            connectionBadge.textContent = 'Consultando';

            try {
                const response = await fetch(initialState.lookupUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': initialState.csrf,
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Falha ao consultar o diagnostico.');
                }

                renderResult(data);
            } catch (error) {
                latestDiagnosticData = null;
                resultEmpty.classList.add('hidden');
                resultPanel.classList.remove('hidden');
                summaryCard.innerHTML = `
                    <div class="rounded-3xl bg-rose-50 border border-rose-200 p-5">
                        <p class="text-xs uppercase tracking-[0.18em] text-rose-500 font-bold">Erro</p>
                        <h3 class="mt-2 text-2xl font-extrabold text-rose-900">Nao foi possivel consultar agora</h3>
                        <p class="mt-3 text-sm text-rose-700 leading-6">${escapeHtml(error.message)}</p>
                    </div>
                `;
                warningList.classList.add('hidden');
                userCard.innerHTML = '';
                deviceCard.innerHTML = '';
                paymentCard.innerHTML = '';
                sessionCard.innerHTML = '';
                connectionBadge.className = 'status-ring rounded-full px-3 py-1.5 text-xs font-bold bg-rose-100 text-rose-800';
                connectionBadge.textContent = 'Erro na consulta';
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Analisar meu acesso';
            }
        }

        function renderResult(data) {
            latestDiagnosticData = data;
            resultEmpty.classList.add('hidden');
            resultPanel.classList.remove('hidden');

            document.getElementById('ip_address').value = data.resolved.ip_address || 'Nao detectado';
            document.getElementById('mac_address').value = data.resolved.mac_address || 'Nao detectado';
            if (data.resolved.phone_registered && !normalizePhone(document.getElementById('phone').value)) {
                document.getElementById('phone').value = formatPhone(data.resolved.phone_registered);
            }

            renderSummary(data.summary, data.resolved);
            renderWarnings(data.summary.warnings || []);
            renderUser(data.user, data.resolved);
            renderDevice(data.mikrotik_report, data.temp_bypass, data.resolved);
            renderPayments(data.payments);
            renderSession(data.session, data.user);
            renderBadge(data.summary.status);
        }

        function renderSummary(summary, resolved) {
            const styles = {
                active: 'bg-emerald-50 border-emerald-200 text-emerald-900',
                pending_payment: 'bg-amber-50 border-amber-200 text-amber-900',
                paid_without_access: 'bg-orange-50 border-orange-200 text-orange-900',
                bypass_recent: 'bg-cyan-50 border-cyan-200 text-cyan-900',
                no_payment: 'bg-slate-100 border-slate-200 text-slate-900',
                not_found: 'bg-rose-50 border-rose-200 text-rose-900',
            };

            const style = styles[summary.status] || styles.no_payment;
            const matchedBy = (resolved.matched_by || []).length
                ? resolved.matched_by.map(escapeHtml).join(', ')
                : 'nenhum criterio';

            summaryCard.innerHTML = `
                <div class="rounded-3xl border p-5 sm:p-6 ${style}">
                    <p class="text-xs uppercase tracking-[0.18em] font-bold opacity-70">Resumo do atendimento</p>
                    <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold">${escapeHtml(summary.headline)}</h2>
                    <p class="mt-3 text-sm sm:text-base leading-7 opacity-90">${escapeHtml(summary.detail)}</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3 text-sm">
                        <div class="rounded-2xl bg-white/70 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] opacity-60">Telefone</p>
                            <p class="mt-1 font-bold">${escapeHtml(formatPhone(resolved.phone_registered || resolved.phone_input || 'Nao informado'))}</p>
                        </div>
                        <div class="rounded-2xl bg-white/70 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] opacity-60">MAC atual</p>
                            <p class="mt-1 font-bold break-all">${escapeHtml(resolved.mac_address || 'Nao detectado')}</p>
                        </div>
                        <div class="rounded-2xl bg-white/70 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.16em] opacity-60">Localizado por</p>
                            <p class="mt-1 font-bold">${escapeHtml(matchedBy)}</p>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderWarnings(warnings) {
            if (!warnings.length) {
                warningList.classList.add('hidden');
                warningList.innerHTML = '';
                return;
            }

            warningList.classList.remove('hidden');
            warningList.innerHTML = `
                <p class="text-xs uppercase tracking-[0.18em] text-amber-700 font-bold mb-4">Alertas</p>
                <div class="space-y-3">
                    ${warnings.map((warning) => `
                        <div class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 leading-6">
                            ${escapeHtml(warning)}
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function renderUser(user, resolved) {
            if (!user) {
                userCard.innerHTML = emptyCard('Cadastro localizado', 'Nenhum usuario foi associado a este telefone, MAC ou IP.');
                return;
            }

            userCard.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-bold">Cadastro localizado</p>
                        <h3 class="mt-2 text-xl font-extrabold">Usuario #${escapeHtml(String(user.id))}</h3>
                    </div>
                    <span class="rounded-full px-3 py-1.5 text-xs font-bold ${user.has_active_access ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}">
                        ${user.has_active_access ? 'Liberado' : 'Sem liberacao ativa'}
                    </span>
                </div>
                <div class="grid gap-3 text-sm text-slate-700">
                    ${infoRow('Telefone cadastrado', formatPhone(user.phone || resolved.phone_input || 'Nao informado'))}
                    ${infoRow('Status atual', user.status || 'Nao informado')}
                    ${infoRow('IP salvo no cadastro', user.ip_address || 'Nao informado')}
                    ${infoRow('MAC salvo no cadastro', user.mac_address || 'Nao informado', true)}
                    ${infoRow('Conectado em', formatDate(user.connected_at))}
                    ${infoRow('Expira em', formatDate(user.expires_at))}
                    ${infoRow('Registrado em', formatDate(user.registered_at))}
                </div>
            `;
        }

        function renderDevice(mikrotikReport, tempBypass, resolved) {
            deviceCard.innerHTML = `
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-bold mb-5">Dispositivo e rede atual</p>
                <div class="grid gap-3 text-sm text-slate-700">
                    ${infoRow('IP detectado agora', resolved.ip_address || 'Nao detectado')}
                    ${infoRow('MAC detectado agora', resolved.mac_address || 'Nao detectado', true)}
                    ${infoRow('Ultimo report do MikroTik', mikrotikReport ? formatDate(mikrotikReport.reported_at) : 'Sem report recente')}
                    ${infoRow('MAC no report do MikroTik', mikrotikReport ? mikrotikReport.mac_address : 'Nao encontrado', true)}
                    ${infoRow('Identificador do MikroTik', mikrotikReport ? (mikrotikReport.mikrotik_id || 'Nao informado') : 'Nao encontrado')}
                    ${infoRow('Ultimo bypass', tempBypass ? `${tempBypass.was_denied ? 'Negado' : 'Aprovado'} #${tempBypass.bypass_number || 0}` : 'Nenhum registro')}
                    ${infoRow('Bypass expira em', tempBypass ? formatDate(tempBypass.expires_at) : 'Nao se aplica')}
                </div>
            `;
        }

        function renderPayments(payments) {
            const latestCompleted = payments.latest_completed;
            const latestPending = payments.latest_pending;
            const recent = payments.recent || [];

            paymentCard.innerHTML = `
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-bold mb-5">Pagamentos</p>
                <div class="space-y-4">
                    <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-emerald-700 font-bold">Ultimo pagamento concluido</p>
                        <p class="mt-2 text-sm text-emerald-900">${latestCompleted ? `R$ ${formatMoney(latestCompleted.amount)} em ${formatDate(latestCompleted.paid_at || latestCompleted.created_at)}` : 'Nenhum pagamento concluido encontrado.'}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
                        <p class="text-xs uppercase tracking-[0.16em] text-amber-700 font-bold">PIX pendente</p>
                        <p class="mt-2 text-sm text-amber-900">${latestPending ? `R$ ${formatMoney(latestPending.amount)} gerado em ${formatDate(latestPending.created_at)}` : 'Nenhum PIX pendente para este cadastro.'}</p>
                    </div>
                    <div>
                        <p class="text-sm font-bold mb-2">Historico recente</p>
                        <div class="space-y-2">
                            ${recent.length ? recent.map((payment) => `
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold">R$ ${formatMoney(payment.amount)} - ${escapeHtml(payment.status)}</p>
                                        <p class="text-xs text-slate-500 mt-1">${escapeHtml(payment.transaction_id || 'Sem transacao')} - ${escapeHtml(formatDate(payment.paid_at || payment.created_at))}</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ${payment.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : payment.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-700'}">${escapeHtml(payment.status)}</span>
                                </div>
                            `).join('') : '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-4 text-sm text-slate-500">Nenhum pagamento recente para exibir.</div>'}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderSession(session, user) {
            sessionCard.innerHTML = `
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-bold mb-5">Sessao e liberacao</p>
                <div class="grid gap-3 text-sm text-slate-700">
                    ${infoRow('Sessao ativa', session ? 'Sim' : 'Nao')}
                    ${infoRow('Inicio da sessao', session ? formatDate(session.started_at) : 'Sem sessao ativa')}
                    ${infoRow('Fim da sessao', session ? formatDate(session.ended_at) : 'Sem sessao encerrada')}
                    ${infoRow('Status da sessao', session ? session.status : 'Nao encontrada')}
                    ${infoRow('Dados consumidos', session && session.data_used ? session.data_used : 'Nao informado')}
                    ${infoRow('Status do cadastro', user ? user.status : 'Nao encontrado')}
                    ${infoRow('Expiracao do acesso', user ? formatDate(user.expires_at) : 'Nao encontrado')}
                </div>
            `;
        }

        function renderBadge(status) {
            const mapping = {
                active: ['bg-emerald-100 text-emerald-800', 'Acesso liberado'],
                pending_payment: ['bg-amber-100 text-amber-800', 'Pagamento pendente'],
                paid_without_access: ['bg-orange-100 text-orange-800', 'Pago sem acesso ativo'],
                bypass_recent: ['bg-cyan-100 text-cyan-800', 'Bypass recente'],
                no_payment: ['bg-slate-200 text-slate-700', 'Sem pagamento confirmado'],
                not_found: ['bg-rose-100 text-rose-800', 'Cadastro nao localizado'],
            };

            const [classes, label] = mapping[status] || mapping.no_payment;
            connectionBadge.className = `status-ring rounded-full px-3 py-1.5 text-xs font-bold ${classes}`;
            connectionBadge.textContent = label;
        }

        function emptyCard(title, message) {
            return `
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500 font-bold mb-4">${escapeHtml(title)}</p>
                <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500 leading-6">
                    ${escapeHtml(message)}
                </div>
            `;
        }

        function infoRow(label, value, mono = false) {
            return `
                <div class="rounded-2xl bg-slate-50 border border-slate-200 px-4 py-3">
                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500 font-bold">${escapeHtml(label)}</p>
                    <p class="mt-1.5 ${mono ? 'font-mono break-all' : 'font-semibold text-slate-900'}">${escapeHtml(String(value || 'Nao informado'))}</p>
                </div>
            `;
        }

        function formatPhone(value) {
            const digits = normalizePhone(value);
            if (!digits) {
                return '';
            }

            if (digits.length <= 10) {
                return digits.replace(/(\d{0,2})(\d{0,4})(\d{0,4}).*/, (_, ddd, first, second) => {
                    return [
                        ddd ? `(${ddd}` + (ddd.length === 2 ? ') ' : '') : '',
                        first,
                        second ? `-${second}` : '',
                    ].join('');
                }).trim();
            }

            return digits.replace(/(\d{0,2})(\d{0,5})(\d{0,4}).*/, (_, ddd, first, second) => {
                return [
                    ddd ? `(${ddd}` + (ddd.length === 2 ? ') ' : '') : '',
                    first,
                    second ? `-${second}` : '',
                ].join('');
            }).trim();
        }

        function normalizePhone(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function normalizeField(value) {
            const normalized = String(value || '').trim();
            return normalized && normalized !== 'Nao detectado' ? normalized : null;
        }

        function formatMoney(value) {
            const amount = Number(value || 0);
            return amount.toFixed(2).replace('.', ',');
        }

        function formatDate(value) {
            if (!value) {
                return 'Nao informado';
            }

            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString('pt-BR');
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function buildDiagnosticReportText(data) {
            if (!data || !data.summary) {
                return '';
            }

            const summary = data.summary || {};
            const resolved = data.resolved || {};
            const user = data.user || null;
            const latestCompleted = data.payments?.latest_completed || null;
            const latestPending = data.payments?.latest_pending || null;
            const mikrotikReport = data.mikrotik_report || null;
            const tempBypass = data.temp_bypass || null;
            const warnings = Array.isArray(summary.warnings) ? summary.warnings : [];
            const matchedBy = (resolved.matched_by || []).length ? resolved.matched_by.join(', ') : 'nenhum';

            const lines = [
                'RELATORIO DE DIAGNOSTICO WIFI TOCANTINS',
                '',
                `Resumo: ${summary.headline || 'Nao informado'}`,
                `Detalhe: ${summary.detail || 'Nao informado'}`,
                `Status: ${summary.status || 'Nao informado'}`,
                '',
                `Telefone informado: ${formatPhone(resolved.phone_input || '') || 'Nao informado'}`,
                `Telefone do cadastro: ${formatPhone(resolved.phone_registered || '') || 'Nao informado'}`,
                `Localizado por: ${matchedBy}`,
                `IP atual: ${resolved.ip_address || 'Nao detectado'}`,
                `MAC atual: ${resolved.mac_address || 'Nao detectado'}`,
                '',
                `Usuario ID: ${user?.id ?? 'Nao encontrado'}`,
                `Status do cadastro: ${user?.status || 'Nao encontrado'}`,
                `Expira em: ${formatDateForReport(user?.expires_at)}`,
                '',
                `Ultimo pagamento concluido: ${latestCompleted ? `R$ ${formatMoney(latestCompleted.amount)} em ${formatDateForReport(latestCompleted.paid_at || latestCompleted.created_at)}` : 'Nenhum'}`,
                `PIX pendente: ${latestPending ? `R$ ${formatMoney(latestPending.amount)} em ${formatDateForReport(latestPending.created_at)}` : 'Nenhum'}`,
                '',
                `MikroTik ID: ${mikrotikReport?.mikrotik_id || 'Nao encontrado'}`,
                `MAC no report MikroTik: ${mikrotikReport?.mac_address || 'Nao encontrado'}`,
                `Ultimo report MikroTik: ${formatDateForReport(mikrotikReport?.reported_at)}`,
                '',
                `Bypass recente: ${tempBypass ? `${tempBypass.was_denied ? 'Negado' : 'Aprovado'} #${tempBypass.bypass_number || 0}` : 'Nenhum'}`,
            ];

            if (warnings.length) {
                lines.push('', 'Alertas:');
                warnings.forEach((warning, index) => lines.push(`${index + 1}. ${warning}`));
            }

            return lines.join('\n');
        }

        async function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return;
            }

            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            const copied = document.execCommand('copy');
            document.body.removeChild(textArea);

            if (!copied) {
                throw new Error('copy_failed');
            }
        }

        function showCopyFeedback(message, isError) {
            copyFeedback.textContent = message;
            copyFeedback.classList.remove('hidden', 'text-emerald-700', 'text-rose-700');
            copyFeedback.classList.add(isError ? 'text-rose-700' : 'text-emerald-700');
        }

        function formatDateForReport(value) {
            return value ? formatDate(value) : 'Nao informado';
        }
    </script>
</body>
</html>