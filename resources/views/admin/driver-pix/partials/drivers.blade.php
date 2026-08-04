<div class="space-y-4">

  {{-- Barra de competência + filtros --}}
  <div class="bg-white rounded-2xl border border-border shadow-card p-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
      <input type="hidden" name="tab" value="drivers">

      <div class="min-w-[190px]">
        <label class="block text-[10px] font-bold uppercase text-muted tracking-wider mb-1">Competência (mês)</label>
        <select name="month" onchange="this.form.submit()"
                class="w-full px-3 py-2.5 border border-border rounded-xl text-sm font-semibold bg-white focus:border-green">
          @foreach($monthOptions as $value => $label)
          <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="flex-1 min-w-[180px]">
        <label class="block text-[10px] font-bold uppercase text-muted tracking-wider mb-1">Buscar motorista</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Nome, CPF ou telefone"
               class="w-full px-3 py-2.5 border border-border rounded-xl text-sm bg-white focus:border-green">
      </div>

      <div class="w-28">
        <label class="block text-[10px] font-bold uppercase text-muted tracking-wider mb-1">Ônibus</label>
        <input type="text" name="bus" value="{{ request('bus') }}" placeholder="5013"
               class="w-full px-3 py-2.5 border border-border rounded-xl text-sm bg-white focus:border-green">
      </div>

      <div class="w-40">
        <label class="block text-[10px] font-bold uppercase text-muted tracking-wider mb-1">Status cadastro</label>
        <select name="status" class="w-full px-3 py-2.5 border border-border rounded-xl text-sm bg-white focus:border-green">
          <option value="">Todos</option>
          <option value="pending" @selected(request('status')==='pending')>Pendentes</option>
          <option value="approved" @selected(request('status')==='approved')>Aprovados</option>
          <option value="rejected" @selected(request('status')==='rejected')>Rejeitados</option>
        </select>
      </div>

      <button type="submit" class="px-5 py-2.5 bg-green text-white rounded-xl text-sm font-bold hover:bg-green-dark">Filtrar</button>

      @if(request('q') || request('bus') || request('status'))
      <a href="{{ route('admin.driver-pix.index', ['tab' => 'drivers', 'month' => $month]) }}"
         class="px-4 py-2.5 text-sm font-semibold text-muted hover:text-ink">Limpar</a>
      @endif
    </form>

    {{-- Resumo do mês --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mt-4 pt-4 border-t border-border">
      <div class="bg-green-pale/60 rounded-xl px-3 py-2">
        <p class="text-[10px] font-bold uppercase text-green-dark tracking-wider">Confirmados</p>
        <p class="text-lg font-bold text-green-dark">{{ $monthTotals['ready'] }}</p>
      </div>
      <div class="bg-slate-100 rounded-xl px-3 py-2">
        <p class="text-[10px] font-bold uppercase text-slate-600 tracking-wider">Não confirmaram</p>
        <p class="text-lg font-bold text-slate-700">{{ $monthTotals['missing'] }}</p>
      </div>
      <div class="bg-blue-pale rounded-xl px-3 py-2">
        <p class="text-[10px] font-bold uppercase text-blue tracking-wider">Alterações</p>
        <p class="text-lg font-bold text-blue">{{ $monthTotals['updates'] }}</p>
      </div>
      <div class="bg-amber-50 rounded-xl px-3 py-2">
        <p class="text-[10px] font-bold uppercase text-amber-700 tracking-wider">Novos cadastros</p>
        <p class="text-lg font-bold text-amber-700">{{ $monthTotals['new'] }}</p>
      </div>
      <div class="bg-surface rounded-xl px-3 py-2">
        <p class="text-[10px] font-bold uppercase text-muted tracking-wider">A pagar no mês</p>
        <p class="text-lg font-bold text-ink">R$ {{ number_format($monthTotals['pending'], 2, ',', '.') }}</p>
      </div>
      <div class="bg-surface rounded-xl px-3 py-2">
        <p class="text-[10px] font-bold uppercase text-muted tracking-wider">Pago no mês</p>
        <p class="text-lg font-bold text-green">R$ {{ number_format($monthTotals['paid'], 2, ',', '.') }}</p>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-border shadow-card overflow-hidden">
    <div class="p-5 border-b border-border bg-surface/50">
      <h3 class="text-base font-bold text-ink">Motoristas — {{ $monthLabel }}</h3>
      <p class="text-xs text-muted mt-0.5">
        Cada linha é um motorista em um carro do mês. Quem trocou de carro aparece uma vez por carro.
      </p>
    </div>

    @if($hasDrivers)

      {{-- 1. Novos cadastros --}}
      @if($newProfiles->count())
      <div class="border-b border-border">
        <div class="px-5 py-3 bg-amber-50 border-b border-amber-100 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          <h4 class="text-sm font-bold text-amber-900">Novos cadastros aguardando aprovação ({{ $newProfiles->count() }})</h4>
        </div>
        <div class="divide-y divide-border">
          @foreach($newProfiles as $card)
            @include('admin.driver-pix.partials.driver-row', ['card' => $card])
          @endforeach
        </div>
      </div>
      @endif

      {{-- 2. Alterações do mês --}}
      @if($updates->count())
      <div class="border-b border-border">
        <div class="px-5 py-3 bg-blue-pale border-b border-blue/10 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-blue"></span>
          <h4 class="text-sm font-bold text-blue">Alterações enviadas em {{ $monthLabel }} ({{ $updates->count() }})</h4>
        </div>
        <div class="divide-y divide-border">
          @foreach($updates as $card)
            @include('admin.driver-pix.partials.driver-row', ['card' => $card])
          @endforeach
        </div>
      </div>
      @endif

      {{-- 3. Confirmados por ônibus (prontos para pagar) --}}
      @if($readyByBus->count())
      <div class="border-b border-border">
        <div class="px-5 py-3 bg-green-pale/50 border-b border-green/10">
          <h4 class="text-sm font-bold text-green-dark">Confirmados em {{ $monthLabel }} — prontos para pagar</h4>
        </div>
        @foreach($readyByBus as $busNumber => $busCards)
        <div class="border-b border-border last:border-b-0">
          <div class="px-5 py-3 bg-surface/80 border-b border-border flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="w-10 h-10 rounded-xl bg-green text-white flex items-center justify-center text-sm font-bold shadow-card">
                {{ $busNumber }}
              </span>
              <div>
                <p class="text-sm font-bold text-ink">Ônibus {{ $busNumber }}</p>
                <p class="text-[11px] text-muted">{{ $busCards->count() }} motorista(s) no mês</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-xs font-semibold text-green">
                R$ {{ number_format($busCards->sum('paid_total'), 2, ',', '.') }} pagos
              </p>
              @php $busPending = $busCards->sum(fn ($c) => $c['pending_payment']?->amount ?? 0); @endphp
              @if($busPending > 0)
              <p class="text-[11px] text-amber-700">R$ {{ number_format($busPending, 2, ',', '.') }} pendentes</p>
              @endif
            </div>
          </div>
          <div class="divide-y divide-border/60">
            @foreach($busCards as $card)
              @include('admin.driver-pix.partials.driver-row', ['card' => $card, 'compact' => true])
            @endforeach
          </div>
        </div>
        @endforeach
      </div>
      @endif

      {{-- 4. Não confirmaram o mês --}}
      @if($missing->count())
      <div class="border-b border-border">
        <div class="px-5 py-3 bg-slate-100 border-b border-border flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
            <h4 class="text-sm font-bold text-slate-700">Sem cadastro em {{ $monthLabel }} ({{ $missing->count() }})</h4>
          </div>
          <a href="{{ route('admin.driver-pix.index', ['tab' => 'links']) }}" class="text-[11px] font-bold text-green hover:underline">
            Enviar link de atualização →
          </a>
        </div>
        <div class="divide-y divide-border">
          @foreach($missing as $card)
            @include('admin.driver-pix.partials.driver-row', ['card' => $card])
          @endforeach
        </div>
      </div>
      @endif

      {{-- 5. Rejeitados --}}
      @if($rejectedCards->count())
      <div>
        <div class="px-5 py-3 bg-red-pale/50 border-b border-red/10 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red"></span>
          <h4 class="text-sm font-bold text-red">Rejeitados ({{ $rejectedCards->count() }})</h4>
        </div>
        <div class="divide-y divide-border">
          @foreach($rejectedCards as $card)
            @include('admin.driver-pix.partials.driver-row', ['card' => $card])
          @endforeach
        </div>
      </div>
      @endif

    @else
      <div class="p-12 text-center">
        <p class="text-muted text-sm">Nenhum motorista encontrado para {{ $monthLabel }}.</p>
        <a href="{{ route('admin.driver-pix.index', ['tab' => 'links']) }}" class="inline-block mt-3 text-sm font-semibold text-green hover:underline">Gerar link de cadastro →</a>
      </div>
    @endif
  </div>
</div>

@push('modals')
{{-- Modal de pagamento --}}
<div id="paymentModal" class="fixed inset-0 z-[200] hidden" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closePaymentModal()"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="pointer-events-auto bg-white rounded-2xl shadow-modal w-full max-w-sm max-h-[90vh] overflow-y-auto relative">
      <div class="sticky top-0 bg-white z-10 px-5 py-4 border-b border-border rounded-t-2xl">
        <button type="button" onclick="closePaymentModal()" class="absolute top-3 right-3 text-muted hover:text-ink p-1 rounded-lg hover:bg-surface">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <h3 class="text-base font-bold text-ink pr-8" id="modalTitle">Registrar pagamento</h3>
        <p class="text-xs text-muted mt-0.5 truncate" id="modalDriverName"></p>
      </div>

      <div class="p-5 space-y-4">
        <div class="bg-surface rounded-xl p-3 space-y-1.5 text-xs">
          <div class="flex justify-between gap-2"><span class="text-muted">Competência</span><span class="font-bold" id="modalMonth">{{ $monthLabel }}</span></div>
          <div class="flex justify-between gap-2"><span class="text-muted">Ônibus</span><span class="font-bold" id="modalBus"></span></div>
          <div class="flex justify-between gap-2"><span class="text-muted">Telefone</span><span class="font-semibold" id="modalPhone"></span></div>
          <div class="flex justify-between items-start gap-2">
            <span class="text-muted flex-shrink-0">Chave PIX</span>
            <span class="font-mono text-[10px] text-right break-all" id="modalPix"></span>
          </div>
        </div>

        <div id="modalQrSection" class="hidden">
          <div class="bg-white border-2 border-green/20 rounded-xl p-3 text-center">
            <p class="text-[10px] font-bold uppercase text-green tracking-wider mb-2">Escaneie para pagar</p>
            <img id="modalQrImg" src="" alt="QR Code PIX" class="w-44 h-44 mx-auto rounded-lg border border-border bg-white object-contain">
            <p class="text-lg font-bold text-ink mt-2" id="modalQrAmount"></p>
            <button type="button" onclick="copyModalEmv()" class="mt-2 w-full py-2 text-[11px] font-bold text-blue bg-blue-pale rounded-lg hover:bg-blue/10">Copiar PIX copia e cola</button>
          </div>
        </div>
        <div id="modalQrLoading" class="hidden text-center py-4 text-xs text-muted">Gerando QR Code...</div>
        <div id="modalQrHint" class="text-[11px] text-muted text-center">Informe o valor abaixo para gerar o QR Code PIX</div>

        {{-- Criar pagamento --}}
        <form id="paymentModalForm" method="POST" class="space-y-3">
          @csrf
          @include('admin.driver-pix.partials.context-inputs')
          <input type="hidden" name="reference_month" value="{{ $month }}">
          <input type="hidden" name="month_entry_id" id="modalEntryId" value="">
          <input type="hidden" name="bus_number" id="modalBusInput" value="">
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Valor (R$) <span class="text-red">*</span></label>
            <input type="number" name="amount" id="modalAmount" step="0.01" min="0.01" required placeholder="0,00"
                   class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:ring-2 focus:ring-green/30 focus:border-green">
          </div>
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Descrição <span class="text-muted font-normal">(opcional)</span></label>
            <input type="text" name="description" id="modalDescription" placeholder="Ex: Pagamento {{ $monthLabel }}"
                   class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:ring-2 focus:ring-green/30 focus:border-green">
          </div>
          <div class="flex gap-2 pt-1">
            <button type="button" onclick="closePaymentModal()" class="flex-1 py-2.5 border border-border rounded-xl text-xs font-semibold text-ink2 hover:bg-surface">Cancelar</button>
            <button type="submit" id="modalSubmitBtn" class="flex-1 py-2.5 bg-green text-white rounded-xl text-xs font-bold hover:bg-green-dark">Criar pagamento</button>
          </div>
        </form>

        {{-- Pagar pendente --}}
        <div id="modalPaySection" class="hidden space-y-3">
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900">
            <p class="font-bold">Pagamento pendente</p>
            <p id="modalPendingDesc" class="mt-0.5 text-amber-800"></p>
          </div>
          <form id="modalPayForm" method="POST">
            @csrf @method('PATCH')
            @include('admin.driver-pix.partials.context-inputs')
            <button type="submit" class="w-full py-3 bg-green text-white rounded-xl text-sm font-bold hover:bg-green-dark">
              ✓ Marcar como pago
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Modal de edição de dados do motorista --}}
<div id="editDriverModal" class="fixed inset-0 z-[200] hidden" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closeEditModal()"></div>
  <div class="fixed inset-0 flex items-center justify-center p-4 pointer-events-none">
    <div class="pointer-events-auto bg-white rounded-2xl shadow-modal w-full max-w-sm max-h-[90vh] overflow-y-auto relative">
      <div class="sticky top-0 bg-white z-10 px-5 py-4 border-b border-border rounded-t-2xl">
        <button type="button" onclick="closeEditModal()" class="absolute top-3 right-3 text-muted hover:text-ink p-1 rounded-lg hover:bg-surface">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <h3 class="text-base font-bold text-ink pr-8">Editar dados do motorista</h3>
        <p class="text-xs text-muted mt-0.5">Use quando o motorista informar os dados por telefone</p>
      </div>
      <form id="editDriverForm" method="POST" class="p-5 space-y-3">
        @csrf @method('PUT')
        @include('admin.driver-pix.partials.context-inputs')
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">Nome completo <span class="text-red">*</span></label>
          <input type="text" name="full_name" id="editName" required maxlength="120"
                 class="w-full px-3 py-2.5 border border-border rounded-xl text-sm uppercase focus:border-green">
        </div>
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">CPF</label>
          <input type="text" name="cpf" id="editCpf" maxlength="14" placeholder="000.000.000-00"
                 class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:border-green">
          <p class="text-[10px] text-muted mt-1">Com CPF o motorista consegue localizar o cadastro sozinho.</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">Telefone <span class="text-red">*</span></label>
          <input type="text" name="phone" id="editPhone" required maxlength="20"
                 class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:border-green">
        </div>
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">Ônibus atual <span class="text-red">*</span></label>
          <input type="text" name="bus_number" id="editBus" required maxlength="20"
                 class="w-full px-3 py-2.5 border border-border rounded-xl text-sm uppercase focus:border-green">
        </div>
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">Nova chave PIX <span class="text-muted font-normal">(opcional)</span></label>
          <input type="text" name="pix_key" id="editPix" maxlength="120" placeholder="Deixe vazio para manter a atual"
                 class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:border-green">
        </div>
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">Anotações internas</label>
          <textarea name="admin_notes" id="editNotes" rows="2" maxlength="500"
                    class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:border-green"></textarea>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="button" onclick="closeEditModal()" class="flex-1 py-2.5 border border-border rounded-xl text-xs font-semibold text-ink2 hover:bg-surface">Cancelar</button>
          <button type="submit" class="flex-1 py-2.5 bg-green text-white rounded-xl text-xs font-bold hover:bg-green-dark">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endpush

@push('scripts')
<script>
(function() {
    let modalPixKey = '';
    let modalProfileId = null;
    let qrTimer = null;
    const pixQrBase = @json(url('admin/pagamentos-motoristas'));
    const monthLabel = @json($monthLabel);

    function bindButtons(selector, handler) {
        document.querySelectorAll(selector).forEach(btn => btn.addEventListener('click', handler));
    }

    bindButtons('.btn-register-payment', (e) => openCreateModal(e.currentTarget.dataset));
    bindButtons('.btn-pay-pending', (e) => openPayModal(e.currentTarget.dataset));
    bindButtons('.btn-edit-driver', (e) => openEditModal(e.currentTarget.dataset));

    window.openCreateModal = function(data, defaultAmount, defaultDescription) {
        modalProfileId = data.id;
        modalPixKey = data.pix;
        document.getElementById('modalTitle').textContent = 'Registrar pagamento';
        document.getElementById('modalDriverName').textContent = data.name;
        document.getElementById('modalPhone').textContent = data.phone || '—';
        document.getElementById('modalBus').textContent = data.bus || '—';
        document.getElementById('modalMonth').textContent = data.month || monthLabel;
        document.getElementById('modalPix').textContent = data.pix;
        document.getElementById('modalEntryId').value = data.entry || '';
        document.getElementById('modalBusInput').value = data.bus || '';
        const form = document.getElementById('paymentModalForm');
        form.action = pixQrBase + '/' + data.id + '/pagamentos';
        form.classList.remove('hidden');
        document.getElementById('modalPaySection').classList.add('hidden');
        document.getElementById('modalAmount').value = defaultAmount ?? '';
        document.getElementById('modalDescription').value = defaultDescription ?? '';
        resetQr();
        showModal('paymentModal');
        if (defaultAmount && parseFloat(defaultAmount) > 0) {
            updateQr(parseFloat(defaultAmount), data.bus);
        }
    };

    window.openPayModal = function(data) {
        modalProfileId = data.id;
        modalPixKey = data.pix;
        const amount = parseFloat(data.amount);
        document.getElementById('modalTitle').textContent = 'Pagar motorista';
        document.getElementById('modalDriverName').textContent = data.name;
        document.getElementById('modalPhone').textContent = data.phone || '—';
        document.getElementById('modalBus').textContent = data.bus || '—';
        document.getElementById('modalMonth').textContent = data.month || monthLabel;
        document.getElementById('modalPix').textContent = data.pix;
        document.getElementById('paymentModalForm').classList.add('hidden');
        document.getElementById('modalPaySection').classList.remove('hidden');
        document.getElementById('modalPendingDesc').textContent =
            (data.description || 'Pagamento motorista') + ' — R$ ' + amount.toFixed(2).replace('.', ',');
        document.getElementById('modalPayForm').action = pixQrBase + '/pagamentos/' + data.paymentId + '/pagar';
        document.getElementById('modalQrHint').classList.add('hidden');
        updateQr(amount, data.bus);
        showModal('paymentModal');
    };

    window.openEditModal = function(data) {
        document.getElementById('editDriverForm').action = pixQrBase + '/' + data.id;
        document.getElementById('editName').value = data.name || '';
        document.getElementById('editCpf').value = data.cpf || '';
        document.getElementById('editPhone').value = data.phone || '';
        document.getElementById('editBus').value = data.bus || '';
        document.getElementById('editPix').value = '';
        document.getElementById('editNotes').value = data.notes || '';
        showModal('editDriverModal');
    };

    function showModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    window.closePaymentModal = function() {
        document.getElementById('paymentModal').classList.add('hidden');
        document.body.style.overflow = '';
        clearTimeout(qrTimer);
    };

    window.closeEditModal = function() {
        document.getElementById('editDriverModal').classList.add('hidden');
        document.body.style.overflow = '';
    };

    function resetQr() {
        document.getElementById('modalQrSection').classList.add('hidden');
        document.getElementById('modalQrLoading').classList.add('hidden');
        document.getElementById('modalQrHint').classList.remove('hidden');
        document.getElementById('modalQrImg').src = '';
    }

    async function updateQr(amount, bus) {
        if (!modalProfileId || !amount || amount <= 0) {
            resetQr();
            return;
        }
        document.getElementById('modalQrHint').classList.add('hidden');
        document.getElementById('modalQrSection').classList.add('hidden');
        document.getElementById('modalQrLoading').classList.remove('hidden');

        try {
            const url = pixQrBase + '/' + modalProfileId + '/pix-qr?amount=' + amount +
                (bus ? '&bus=' + encodeURIComponent(bus) : '');
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Erro ao gerar QR');

            document.getElementById('modalQrLoading').classList.add('hidden');
            document.getElementById('modalQrSection').classList.remove('hidden');
            document.getElementById('modalQrImg').src = data.qr_url;
            document.getElementById('modalQrImg').dataset.emv = data.emv;
            document.getElementById('modalQrAmount').textContent = 'R$ ' + amount.toFixed(2).replace('.', ',');
        } catch (err) {
            document.getElementById('modalQrLoading').classList.add('hidden');
            document.getElementById('modalQrHint').textContent = 'Não foi possível gerar o QR Code.';
            document.getElementById('modalQrHint').classList.remove('hidden');
        }
    }

    document.getElementById('modalAmount')?.addEventListener('input', function() {
        clearTimeout(qrTimer);
        const val = parseFloat(this.value);
        const bus = document.getElementById('modalBusInput').value;
        qrTimer = setTimeout(() => updateQr(val, bus), 400);
    });

    window.copyModalEmv = function() {
        const emv = document.getElementById('modalQrImg')?.dataset.emv;
        if (emv) navigator.clipboard.writeText(emv);
    };

    window.copyPixKey = function(key) {
        navigator.clipboard.writeText(key);
    };

    @if(session('open_register_profile'))
    document.addEventListener('DOMContentLoaded', function() {
        const entryId = @json(session('open_register_entry'));
        const profileId = @json(session('open_register_profile'));
        const amount = @json(session('open_register_amount'));
        const description = @json(session('open_register_description'));
        const btn = (entryId ? document.querySelector('.btn-register-payment[data-entry="' + entryId + '"]') : null)
            || document.querySelector('.btn-register-payment[data-id="' + profileId + '"]');
        if (btn) {
            setTimeout(() => openCreateModal(btn.dataset, amount, description), 300);
        }
    });
    @endif
})();
</script>
@endpush
