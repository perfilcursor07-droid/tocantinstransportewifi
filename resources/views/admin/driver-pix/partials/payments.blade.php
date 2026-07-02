<div class="bg-white rounded-2xl border border-border shadow-card overflow-hidden">
  <div class="p-4 border-b border-border">
    <h3 class="text-sm font-bold text-ink">Controle de pagamentos</h3>
    <p class="text-xs text-muted mt-1">Registre pagamentos pendentes e marque como pagos após enviar o PIX manualmente.</p>
  </div>

  @if($payments->count())
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-surface">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Motorista</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Ônibus</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Valor</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Descrição</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Status</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Data</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-muted">Ações</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border">
        @foreach($payments as $payment)
        <tr class="hover:bg-surface/50 align-top">
          <td class="px-4 py-3 font-semibold text-ink">{{ $payment->profile?->full_name ?? '—' }}</td>
          <td class="px-4 py-3 font-bold">{{ $payment->profile?->bus_number ?? '—' }}</td>
          <td class="px-4 py-3 font-bold text-green">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
          <td class="px-4 py-3 text-xs text-ink2">{{ $payment->description ?: '—' }}</td>
          <td class="px-4 py-3">
            @php
              $pbadge = match($payment->status) {
                'pending' => 'bg-amber-100 text-amber-700',
                'paid' => 'bg-green-pale text-green',
                'cancelled' => 'bg-red-pale text-red',
              };
              $plabel = match($payment->status) {
                'pending' => 'Pendente',
                'paid' => 'Pago',
                'cancelled' => 'Cancelado',
              };
            @endphp
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $pbadge }}">{{ $plabel }}</span>
            @if($payment->status === 'paid' && $payment->payment_reference)
            <p class="text-[10px] text-muted mt-1">Ref: {{ $payment->payment_reference }}</p>
            @endif
          </td>
          <td class="px-4 py-3 text-xs text-muted">
            {{ $payment->created_at->format('d/m/Y H:i') }}
            @if($payment->paid_at)
            <p class="text-[10px] text-green">Pago {{ $payment->paid_at->format('d/m H:i') }}</p>
            @endif
          </td>
          <td class="px-4 py-3 text-center">
            @if($payment->status === 'pending')
            <div class="flex flex-col gap-1 items-center">
              @if($payment->profile)
              <button type="button" onclick="navigator.clipboard.writeText('{{ $payment->profile->pix_key }}'); alert('Chave PIX copiada!')" class="text-[10px] text-blue font-semibold">Copiar PIX</button>
              @endif
              <form action="{{ route('admin.driver-pix.payments.paid', $payment) }}" method="POST" class="flex gap-1 items-center">
                @csrf @method('PATCH')
                <input type="text" name="payment_reference" placeholder="Comprovante" class="w-24 px-1 py-1 border border-border rounded text-[10px]">
                <button type="submit" class="px-2 py-1 bg-green text-white rounded text-[10px] font-semibold">Marcar pago</button>
              </form>
              <form action="{{ route('admin.driver-pix.payments.cancel', $payment) }}" method="POST">@csrf @method('PATCH')
                <button type="submit" class="text-[10px] text-red font-semibold">Cancelar</button>
              </form>
            </div>
            @else
            <span class="text-[10px] text-muted">—</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="p-4">{{ $payments->appends(['tab' => 'payments'])->links() }}</div>
  @else
  <p class="p-6 text-sm text-muted text-center">Nenhum pagamento registrado.</p>
  @endif
</div>
