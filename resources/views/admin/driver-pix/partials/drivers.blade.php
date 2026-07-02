<div class="bg-white rounded-2xl border border-border shadow-card overflow-hidden">
  <div class="p-5 border-b border-border bg-surface/50 flex flex-wrap gap-4 justify-between items-center">
    <div>
      <h3 class="text-base font-bold text-ink">Motoristas</h3>
      <p class="text-xs text-muted mt-0.5">Aprove cadastros e registre pagamentos</p>
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

  @if($profiles->count())
  <div class="divide-y divide-border">
    @foreach($profiles as $profile)
    <div class="p-5 hover:bg-surface/30 transition-colors">
      <div class="flex flex-col lg:flex-row lg:items-center gap-4">
        {{-- Info principal --}}
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h4 class="font-bold text-ink text-base">{{ $profile->full_name }}</h4>
            @php
              $badge = match($profile->status) {
                'pending' => 'bg-amber-100 text-amber-800',
                'approved' => 'bg-green-pale text-green-dark',
                'rejected' => 'bg-red-pale text-red',
              };
              $label = match($profile->status) {
                'pending' => 'Pendente',
                'approved' => 'Aprovado',
                'rejected' => 'Rejeitado',
              };
            @endphp
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $badge }}">{{ $label }}</span>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Ônibus {{ $profile->bus_number }}</span>
          </div>
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted mt-2">
            @if($profile->phone)
            <span class="flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              {{ $profile->formattedPhone() }}
            </span>
            @endif
            <span>Cadastro: {{ $profile->created_at->format('d/m/Y H:i') }}</span>
          </div>
        </div>

        {{-- PIX --}}
        <div class="lg:w-48 flex-shrink-0 bg-surface rounded-xl p-3 border border-border">
          <p class="text-[10px] font-bold uppercase text-muted tracking-wider mb-1">Chave PIX</p>
          <p class="font-mono text-xs text-ink break-all">{{ $profile->maskedPixKey() }}</p>
          <p class="text-[10px] text-muted uppercase mt-0.5">{{ $profile->pix_key_type }}</p>
          @if($profile->status === 'approved')
          <button type="button" onclick="copyPixKey('{{ $profile->pix_key }}')" class="mt-2 text-[11px] font-bold text-blue hover:underline">Copiar chave</button>
          @endif
        </div>

        {{-- Total pago --}}
        <div class="lg:w-28 flex-shrink-0 text-center lg:text-right">
          <p class="text-[10px] font-bold uppercase text-muted">Total pago</p>
          <p class="text-lg font-bold text-green">R$ {{ number_format($profile->total_paid ?? 0, 2, ',', '.') }}</p>
          @if(($profile->pending_payments_count ?? 0) > 0)
          <p class="text-[10px] text-amber-600 font-semibold">{{ $profile->pending_payments_count }} pendente(s)</p>
          @endif
        </div>

        {{-- Ações --}}
        <div class="flex flex-wrap gap-2 lg:flex-col lg:w-36 flex-shrink-0">
          @if($profile->status === 'pending')
          <form action="{{ route('admin.driver-pix.approve', $profile) }}" method="POST" class="flex-1 lg:flex-none">@csrf @method('PATCH')
            <button type="submit" class="w-full px-4 py-2.5 bg-green text-white rounded-xl text-sm font-semibold hover:bg-green-dark">Aprovar</button>
          </form>
          <form action="{{ route('admin.driver-pix.reject', $profile) }}" method="POST" class="flex-1 lg:flex-none">@csrf @method('PATCH')
            <input type="hidden" name="rejected_reason" value="Dados incorretos ou incompletos">
            <button type="submit" class="w-full px-4 py-2.5 bg-white border border-red/30 text-red rounded-xl text-sm font-semibold hover:bg-red-pale">Rejeitar</button>
          </form>
          @elseif($profile->status === 'approved')
          <button type="button"
                  class="btn-register-payment w-full px-4 py-2.5 bg-blue text-white rounded-xl text-sm font-semibold hover:bg-blue-light"
                  data-id="{{ $profile->id }}"
                  data-name="{{ $profile->full_name }}"
                  data-phone="{{ $profile->formattedPhone() }}"
                  data-bus="{{ $profile->bus_number }}"
                  data-pix="{{ $profile->pix_key }}">
            Registrar pagamento
          </button>
          @endif
        </div>
      </div>
      @if($profile->status === 'rejected' && $profile->rejected_reason)
      <p class="text-xs text-red mt-3 pl-1">Motivo: {{ $profile->rejected_reason }}</p>
      @endif
    </div>
    @endforeach
  </div>
  <div class="p-4 border-t border-border">{{ $profiles->appends(request()->query())->links() }}</div>
  @else
  <div class="p-12 text-center">
    <p class="text-muted text-sm">Nenhum motorista cadastrado.</p>
    <a href="{{ route('admin.driver-pix.index', ['tab' => 'links']) }}" class="inline-block mt-3 text-sm font-semibold text-green hover:underline">Gerar link de cadastro →</a>
  </div>
  @endif
</div>

{{-- Modal registrar pagamento --}}
<div id="paymentModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closePaymentModal()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative animate-[fadeIn_0.2s_ease]">
      <div class="p-6 border-b border-border">
        <button type="button" onclick="closePaymentModal()" class="absolute top-4 right-4 text-muted hover:text-ink p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <h3 class="text-lg font-bold text-ink">Registrar pagamento</h3>
        <p class="text-sm text-muted mt-1" id="modalDriverName"></p>
      </div>
      <form id="paymentModalForm" method="POST" class="p-6 space-y-4">
        @csrf
        <div class="bg-surface rounded-xl p-4 space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-muted">Ônibus</span><span class="font-bold" id="modalBus"></span></div>
          <div class="flex justify-between"><span class="text-muted">Telefone</span><span class="font-semibold" id="modalPhone"></span></div>
          <div class="flex justify-between items-start gap-2">
            <span class="text-muted flex-shrink-0">Chave PIX</span>
            <span class="font-mono text-xs text-right break-all" id="modalPix"></span>
          </div>
          <button type="button" onclick="copyModalPix()" class="w-full mt-1 py-2 text-xs font-bold text-blue bg-blue-pale rounded-lg hover:bg-blue/10">Copiar chave PIX</button>
        </div>
        <div>
          <label class="block text-sm font-semibold text-ink mb-1.5">Valor (R$) <span class="text-red">*</span></label>
          <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0,00"
                 class="w-full px-4 py-3 border border-border rounded-xl text-sm focus:ring-2 focus:ring-green/30 focus:border-green">
        </div>
        <div>
          <label class="block text-sm font-semibold text-ink mb-1.5">Descrição <span class="text-muted font-normal">(opcional)</span></label>
          <input type="text" name="description" placeholder="Ex: Pagamento semanal"
                 class="w-full px-4 py-3 border border-border rounded-xl text-sm focus:ring-2 focus:ring-green/30 focus:border-green">
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" onclick="closePaymentModal()" class="flex-1 py-3 border border-border rounded-xl text-sm font-semibold text-ink2 hover:bg-surface">Cancelar</button>
          <button type="submit" class="flex-1 py-3 bg-green text-white rounded-xl text-sm font-bold hover:bg-green-dark">Criar pagamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let modalPixKey = '';
document.querySelectorAll('.btn-register-payment').forEach(btn => {
    btn.addEventListener('click', () => openPaymentModal(
        btn.dataset.id,
        btn.dataset.name,
        btn.dataset.phone,
        btn.dataset.bus,
        btn.dataset.pix
    ));
});
function openPaymentModal(id, name, phone, bus, pixKey) {
    modalPixKey = pixKey;
    document.getElementById('modalDriverName').textContent = name;
    document.getElementById('modalPhone').textContent = phone || '—';
    document.getElementById('modalBus').textContent = bus;
    document.getElementById('modalPix').textContent = pixKey;
    document.getElementById('paymentModalForm').action = '{{ url('admin/pagamentos-motoristas') }}/' + id + '/pagamentos';
    document.getElementById('paymentModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    document.body.style.overflow = '';
}
function copyModalPix() {
    if (modalPixKey) navigator.clipboard.writeText(modalPixKey);
}
function copyPixKey(key) {
    navigator.clipboard.writeText(key);
}
</script>
