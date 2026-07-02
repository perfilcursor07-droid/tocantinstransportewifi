<div class="bg-white rounded-2xl border border-border shadow-card overflow-hidden">
  <div class="p-4 border-b border-border flex flex-wrap gap-3 justify-between items-center">
    <h3 class="text-sm font-bold text-ink">Motoristas cadastrados</h3>
    <form method="GET" class="flex gap-2">
      <input type="hidden" name="tab" value="drivers">
      <input type="text" name="bus" value="{{ request('bus') }}" placeholder="Ônibus ex: 5013" class="px-3 py-1.5 border border-border rounded-lg text-xs w-28">
      <select name="status" class="px-3 py-1.5 border border-border rounded-lg text-xs">
        <option value="">Todos</option>
        <option value="pending" @selected(request('status')==='pending')>Pendentes</option>
        <option value="approved" @selected(request('status')==='approved')>Aprovados</option>
        <option value="rejected" @selected(request('status')==='rejected')>Rejeitados</option>
      </select>
      <button class="px-3 py-1.5 bg-surface rounded-lg text-xs font-semibold">Filtrar</button>
    </form>
  </div>

  @if($profiles->count())
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-surface">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Motorista</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Ônibus</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Chave PIX</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Status</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Total pago</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-muted">Ações</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border">
        @foreach($profiles as $profile)
        <tr class="hover:bg-surface/50 align-top">
          <td class="px-4 py-3">
            <p class="font-semibold text-ink">{{ $profile->full_name }}</p>
            <p class="text-[10px] text-muted">{{ $profile->created_at->format('d/m/Y H:i') }}</p>
          </td>
          <td class="px-4 py-3 font-bold text-ink">{{ $profile->bus_number }}</td>
          <td class="px-4 py-3">
            <p class="font-mono text-xs text-ink2" title="{{ $profile->pix_key }}">{{ $profile->maskedPixKey() }}</p>
            <p class="text-[10px] text-muted uppercase">{{ $profile->pix_key_type }}</p>
            @if($profile->status === 'approved')
            <button type="button" onclick="navigator.clipboard.writeText('{{ $profile->pix_key }}'); alert('Chave PIX copiada!')" class="text-[10px] text-blue font-semibold mt-1">Copiar chave</button>
            @endif
          </td>
          <td class="px-4 py-3">
            @php
              $badge = match($profile->status) {
                'pending' => 'bg-amber-100 text-amber-700',
                'approved' => 'bg-green-pale text-green',
                'rejected' => 'bg-red-pale text-red',
              };
              $label = match($profile->status) {
                'pending' => 'Pendente',
                'approved' => 'Aprovado',
                'rejected' => 'Rejeitado',
              };
            @endphp
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge }}">{{ $label }}</span>
            @if($profile->status === 'rejected' && $profile->rejected_reason)
            <p class="text-[10px] text-red mt-1">{{ $profile->rejected_reason }}</p>
            @endif
          </td>
          <td class="px-4 py-3 text-xs">
            R$ {{ number_format($profile->total_paid ?? 0, 2, ',', '.') }}
            @if(($profile->pending_payments_count ?? 0) > 0)
            <p class="text-[10px] text-amber-600">{{ $profile->pending_payments_count }} pendente(s)</p>
            @endif
          </td>
          <td class="px-4 py-3">
            <div class="flex flex-col gap-2 items-center min-w-[140px]">
              @if($profile->status === 'pending')
              <form action="{{ route('admin.driver-pix.approve', $profile) }}" method="POST">@csrf @method('PATCH')
                <button class="w-full px-3 py-1.5 bg-green text-white rounded-lg text-xs font-semibold">Aprovar</button>
              </form>
              <form action="{{ route('admin.driver-pix.reject', $profile) }}" method="POST" class="w-full">@csrf @method('PATCH')
                <input type="hidden" name="rejected_reason" value="Dados incorretos ou incompletos">
                <button class="w-full px-3 py-1.5 bg-red-pale text-red rounded-lg text-xs font-semibold">Rejeitar</button>
              </form>
              @elseif($profile->status === 'approved')
              <details class="w-full">
                <summary class="cursor-pointer text-xs font-semibold text-blue text-center">Registrar pagamento</summary>
                <form action="{{ route('admin.driver-pix.payments.store', $profile) }}" method="POST" class="mt-2 space-y-2 p-2 bg-surface rounded-lg">
                  @csrf
                  <input type="number" name="amount" step="0.01" min="0.01" required placeholder="Valor R$" class="w-full px-2 py-1.5 border border-border rounded-lg text-xs">
                  <input type="text" name="description" placeholder="Descrição" class="w-full px-2 py-1.5 border border-border rounded-lg text-xs">
                  <button type="submit" class="w-full py-1.5 bg-blue text-white rounded-lg text-xs font-semibold">Criar pendente</button>
                </form>
              </details>
              @endif
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="p-4">{{ $profiles->appends(request()->query())->links() }}</div>
  @else
  <p class="p-6 text-sm text-muted text-center">Nenhum motorista cadastrado ainda. Gere um link na aba "Links de cadastro".</p>
  @endif
</div>
