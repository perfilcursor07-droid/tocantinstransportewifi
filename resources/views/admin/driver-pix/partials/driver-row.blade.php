<div class="p-4 hover:bg-surface/30 transition-colors {{ $compact ?? false ? 'pl-6' : '' }}">
  <div class="flex flex-col lg:flex-row lg:items-center gap-4">
    <div class="flex-1 min-w-0">
      <div class="flex flex-wrap items-center gap-2 mb-1">
        <h4 class="font-bold text-ink {{ ($compact ?? false) ? 'text-sm' : 'text-base' }}">{{ $profile->full_name }}</h4>
        @if(!($compact ?? false))
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
        @if($profile->status !== 'approved')
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">Ônibus {{ $profile->bus_number }}</span>
        @endif
        @endif

        {{-- Status pagamento --}}
        @if($profile->latestPendingPayment)
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
          ⏳ Pendente R$ {{ number_format($profile->latestPendingPayment->amount, 2, ',', '.') }}
        </span>
        @elseif($profile->latestPaidPayment)
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-pale text-green-dark">
          ✓ Pago {{ $profile->latestPaidPayment->paid_at?->format('d/m/Y') }}
        </span>
        @elseif($profile->status === 'approved')
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Sem pagamento</span>
        @endif
      </div>
      <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted mt-1">
        @if($profile->phone)
        <span class="flex items-center gap-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          {{ $profile->formattedPhone() }}
        </span>
        @endif
        @if($profile->latestPaidPayment)
        <span>Último: R$ {{ number_format($profile->latestPaidPayment->amount, 2, ',', '.') }}</span>
        @endif
        <span>Cadastro: {{ $profile->created_at->format('d/m/Y') }}</span>
      </div>
    </div>

    <div class="lg:w-44 flex-shrink-0 bg-surface rounded-xl p-3 border border-border">
      <p class="text-[10px] font-bold uppercase text-muted tracking-wider mb-1">Chave PIX</p>
      <p class="font-mono text-xs text-ink break-all">{{ $profile->maskedPixKey() }}</p>
      @if($profile->status === 'approved')
      <button type="button" onclick="copyPixKey(@json($profile->pix_key))" class="mt-2 text-[11px] font-bold text-blue hover:underline">Copiar chave</button>
      @endif
    </div>

    <div class="lg:w-24 flex-shrink-0 text-center lg:text-right">
      <p class="text-[10px] font-bold uppercase text-muted">Total pago</p>
      <p class="text-base font-bold text-green">R$ {{ number_format($profile->total_paid ?? 0, 2, ',', '.') }}</p>
    </div>

    <div class="flex flex-wrap gap-2 lg:flex-col lg:w-40 flex-shrink-0">
      @if($profile->status === 'pending')
      <form action="{{ route('admin.driver-pix.approve', $profile) }}" method="POST" class="flex-1 lg:flex-none">@csrf @method('PATCH')
        <button type="submit" class="w-full px-4 py-2 bg-green text-white rounded-xl text-sm font-semibold hover:bg-green-dark">Aprovar</button>
      </form>
      <form action="{{ route('admin.driver-pix.reject', $profile) }}" method="POST" class="flex-1 lg:flex-none">@csrf @method('PATCH')
        <input type="hidden" name="rejected_reason" value="Dados incorretos ou incompletos">
        <button type="submit" class="w-full px-4 py-2 bg-white border border-red/30 text-red rounded-xl text-sm font-semibold hover:bg-red-pale">Rejeitar</button>
      </form>
      @elseif($profile->status === 'approved')
        @if($profile->latestPendingPayment)
        <button type="button"
                class="btn-pay-pending w-full px-4 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-semibold hover:bg-amber-600"
                data-id="{{ $profile->id }}"
                data-name="{{ $profile->full_name }}"
                data-phone="{{ $profile->formattedPhone() }}"
                data-bus="{{ $profile->bus_number }}"
                data-pix="{{ $profile->pix_key }}"
                data-payment-id="{{ $profile->latestPendingPayment->id }}"
                data-amount="{{ $profile->latestPendingPayment->amount }}"
                data-description="{{ $profile->latestPendingPayment->description }}">
          Pagar R$ {{ number_format($profile->latestPendingPayment->amount, 2, ',', '.') }}
        </button>
        @else
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
      @endif
      <form action="{{ route('admin.driver-pix.destroy', $profile) }}" method="POST"
            onsubmit="return confirm('Excluir permanentemente o cadastro de {{ addslashes($profile->full_name) }}?\n\nTodos os pagamentos vinculados também serão removidos.')">
        @csrf @method('DELETE')
        <button type="submit" class="w-full px-4 py-2 text-red text-xs font-semibold hover:underline mt-1">Excluir</button>
      </form>
    </div>
  </div>
  @if($profile->status === 'rejected' && $profile->rejected_reason)
  <p class="text-xs text-red mt-2 pl-1">Motivo: {{ $profile->rejected_reason }}</p>
  @endif
</div>
