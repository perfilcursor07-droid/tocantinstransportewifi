@php
  $hasDrivers = ($pendingProfiles->count() + $rejectedProfiles->count() + $approvedByBus->flatten()->count()) > 0;
@endphp

<div class="bg-white rounded-2xl border border-border shadow-card overflow-hidden">
  <div class="p-5 border-b border-border bg-surface/50 flex flex-wrap gap-4 justify-between items-center">
    <div>
      <h3 class="text-base font-bold text-ink">Motoristas</h3>
      <p class="text-xs text-muted mt-0.5">Aprovados agrupados por ônibus · pendentes no topo</p>
    </div>
    <form method="GET" class="flex flex-wrap gap-2 items-center">
      <input type="hidden" name="tab" value="drivers">
      <input type="text" name="bus" value="{{ request('bus') }}" placeholder="Ônibus 5013"
             class="px-3 py-2 border border-border rounded-xl text-sm w-32 bg-white">
      <select name="status" class="px-3 py-2 border border-border rounded-xl text-sm bg-white">
        <option value="">Todos status</option>
        <option value="pending" @selected(request('status')==='pending')>Pendentes</option>
        <option value="approved" @selected(request('status')==='approved')>Aprovados</option>
        <option value="rejected" @selected(request('status')==='rejected')>Rejeitados</option>
      </select>
      <button type="submit" class="px-4 py-2 bg-green text-white rounded-xl text-sm font-semibold hover:bg-green-dark">Filtrar</button>
    </form>
  </div>

  @if($hasDrivers)

  {{-- Pendentes de aprovação --}}
  @if($pendingProfiles->count() && (!$statusFilter || $statusFilter === 'pending'))
  <div class="border-b border-border">
    <div class="px-5 py-3 bg-amber-50 border-b border-amber-100 flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-amber-500"></span>
      <h4 class="text-sm font-bold text-amber-900">Aguardando aprovação ({{ $pendingProfiles->count() }})</h4>
    </div>
    <div class="divide-y divide-border">
      @foreach($pendingProfiles as $profile)
        @include('admin.driver-pix.partials.driver-row', ['profile' => $profile])
      @endforeach
    </div>
  </div>
  @endif

  {{-- Aprovados por ônibus --}}
  @if($approvedByBus->count() && (!$statusFilter || $statusFilter === 'approved'))
  <div class="border-b border-border">
    <div class="px-5 py-3 bg-green-pale/50 border-b border-green/10">
      <h4 class="text-sm font-bold text-green-dark">Aprovados por ônibus</h4>
    </div>
    @foreach($approvedByBus as $busNumber => $busProfiles)
    <div class="border-b border-border last:border-b-0">
      <div class="px-5 py-3 bg-surface/80 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 rounded-xl bg-green text-white flex items-center justify-center text-sm font-bold shadow-card">
            {{ $busNumber }}
          </span>
          <div>
            <p class="text-sm font-bold text-ink">Ônibus {{ $busNumber }}</p>
            <p class="text-[11px] text-muted">{{ $busProfiles->count() }} motorista(s)</p>
          </div>
        </div>
        <p class="text-xs font-semibold text-green">
          R$ {{ number_format($busProfiles->sum('total_paid'), 2, ',', '.') }} pagos
        </p>
      </div>
      <div class="divide-y divide-border/60">
        @foreach($busProfiles as $profile)
          @include('admin.driver-pix.partials.driver-row', ['profile' => $profile, 'compact' => true])
        @endforeach
      </div>
    </div>
    @endforeach
  </div>
  @endif

  {{-- Rejeitados --}}
  @if($rejectedProfiles->count() && (!$statusFilter || $statusFilter === 'rejected'))
  <div>
    <div class="px-5 py-3 bg-red-pale/50 border-b border-red/10 flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-red"></span>
      <h4 class="text-sm font-bold text-red">Rejeitados ({{ $rejectedProfiles->count() }})</h4>
    </div>
    <div class="divide-y divide-border">
      @foreach($rejectedProfiles as $profile)
        @include('admin.driver-pix.partials.driver-row', ['profile' => $profile])
      @endforeach
    </div>
  </div>
  @endif

  @else
  <div class="p-12 text-center">
    <p class="text-muted text-sm">Nenhum motorista cadastrado.</p>
    <a href="{{ route('admin.driver-pix.index', ['tab' => 'links']) }}" class="inline-block mt-3 text-sm font-semibold text-green hover:underline">Gerar link de cadastro →</a>
  </div>
  @endif
</div>

@push('modals')
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
          <div class="flex justify-between gap-2"><span class="text-muted">Ônibus</span><span class="font-bold" id="modalBus"></span></div>
          <div class="flex justify-between gap-2"><span class="text-muted">Telefone</span><span class="font-semibold" id="modalPhone"></span></div>
          <div class="flex justify-between items-start gap-2">
            <span class="text-muted flex-shrink-0">Chave PIX</span>
            <span class="font-mono text-[10px] text-right break-all" id="modalPix"></span>
          </div>
        </div>

        {{-- QR Code --}}
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

        {{-- Modo criar pagamento --}}
        <form id="paymentModalForm" method="POST" class="space-y-3">
          @csrf
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Valor (R$) <span class="text-red">*</span></label>
            <input type="number" name="amount" id="modalAmount" step="0.01" min="0.01" required placeholder="0,00"
                   class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:ring-2 focus:ring-green/30 focus:border-green">
          </div>
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Descrição <span class="text-muted font-normal">(opcional)</span></label>
            <input type="text" name="description" id="modalDescription" placeholder="Ex: Pagamento semanal"
                   class="w-full px-3 py-2.5 border border-border rounded-xl text-sm focus:ring-2 focus:ring-green/30 focus:border-green">
          </div>
          <div class="flex gap-2 pt-1">
            <button type="button" onclick="closePaymentModal()" class="flex-1 py-2.5 border border-border rounded-xl text-xs font-semibold text-ink2 hover:bg-surface">Cancelar</button>
            <button type="submit" id="modalSubmitBtn" class="flex-1 py-2.5 bg-green text-white rounded-xl text-xs font-bold hover:bg-green-dark">Criar pagamento</button>
          </div>
        </form>

        {{-- Modo pagar pendente --}}
        <div id="modalPaySection" class="hidden space-y-3">
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900">
            <p class="font-bold">Pagamento pendente</p>
            <p id="modalPendingDesc" class="mt-0.5 text-amber-800"></p>
          </div>
          <form id="modalPayForm" method="POST">
            @csrf @method('PATCH')
            <button type="submit" class="w-full py-3 bg-green text-white rounded-xl text-sm font-bold hover:bg-green-dark">
              ✓ Marcar como pago
            </button>
          </form>
        </div>
      </div>
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

    function bindButtons(selector, handler) {
        document.querySelectorAll(selector).forEach(btn => btn.addEventListener('click', handler));
    }

    bindButtons('.btn-register-payment', (e) => {
        const b = e.currentTarget;
        openCreateModal(b.dataset.id, b.dataset.name, b.dataset.phone, b.dataset.bus, b.dataset.pix);
    });

    bindButtons('.btn-pay-pending', (e) => {
        const b = e.currentTarget;
        openPayModal(b.dataset);
    });

    window.openCreateModal = function(id, name, phone, bus, pixKey, defaultAmount, defaultDescription) {
        modalProfileId = id;
        modalPixKey = pixKey;
        document.getElementById('modalTitle').textContent = 'Registrar pagamento';
        document.getElementById('modalDriverName').textContent = name;
        document.getElementById('modalPhone').textContent = phone || '—';
        document.getElementById('modalBus').textContent = bus;
        document.getElementById('modalPix').textContent = pixKey;
        document.getElementById('paymentModalForm').action = pixQrBase + '/' + id + '/pagamentos';
        document.getElementById('paymentModalForm').classList.remove('hidden');
        document.getElementById('modalPaySection').classList.add('hidden');
        document.getElementById('modalAmount').value = defaultAmount ?? '';
        document.getElementById('modalDescription').value = defaultDescription ?? '';
        document.getElementById('modalAmount').readOnly = false;
        resetQr();
        showModal();
        if (defaultAmount && parseFloat(defaultAmount) > 0) {
            updateQr(parseFloat(defaultAmount));
        }
    };

    window.openPayModal = function(data) {
        modalProfileId = data.id;
        modalPixKey = data.pix;
        const amount = parseFloat(data.amount);
        document.getElementById('modalTitle').textContent = 'Pagar motorista';
        document.getElementById('modalDriverName').textContent = data.name;
        document.getElementById('modalPhone').textContent = data.phone || '—';
        document.getElementById('modalBus').textContent = data.bus;
        document.getElementById('modalPix').textContent = data.pix;
        document.getElementById('paymentModalForm').classList.add('hidden');
        document.getElementById('modalPaySection').classList.remove('hidden');
        document.getElementById('modalPendingDesc').textContent =
            (data.description || 'Pagamento motorista') + ' — R$ ' + amount.toFixed(2).replace('.', ',');
        document.getElementById('modalPayForm').action = pixQrBase + '/pagamentos/' + data.paymentId + '/pagar';
        document.getElementById('modalQrHint').classList.add('hidden');
        updateQr(amount);
        showModal();
    };

    function showModal() {
        document.getElementById('paymentModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    window.closePaymentModal = function() {
        document.getElementById('paymentModal').classList.add('hidden');
        document.body.style.overflow = '';
        clearTimeout(qrTimer);
    };

    function resetQr() {
        document.getElementById('modalQrSection').classList.add('hidden');
        document.getElementById('modalQrLoading').classList.add('hidden');
        document.getElementById('modalQrHint').classList.remove('hidden');
        document.getElementById('modalQrImg').src = '';
    }

    async function updateQr(amount) {
        if (!modalProfileId || !amount || amount <= 0) {
            resetQr();
            return;
        }
        document.getElementById('modalQrHint').classList.add('hidden');
        document.getElementById('modalQrSection').classList.add('hidden');
        document.getElementById('modalQrLoading').classList.remove('hidden');

        try {
            const res = await fetch(pixQrBase + '/' + modalProfileId + '/pix-qr?amount=' + amount, {
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
        qrTimer = setTimeout(() => updateQr(val), 400);
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
        const profileId = @json(session('open_register_profile'));
        const amount = @json(session('open_register_amount'));
        const description = @json(session('open_register_description'));
        const btn = document.querySelector('.btn-register-payment[data-id="' + profileId + '"]');
        if (btn) {
            setTimeout(() => {
                openCreateModal(
                    btn.dataset.id,
                    btn.dataset.name,
                    btn.dataset.phone,
                    btn.dataset.bus,
                    btn.dataset.pix,
                    amount,
                    description
                );
            }, 300);
        }
    });
    @endif
})();
</script>
@endpush
