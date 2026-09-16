@extends('layouts.admin')

@section('title', 'Pedidos de Motoristas')

@section('breadcrumb')
    <span class="mx-2">/</span>
    <a href="{{ route('admin.vouchers.index') }}" class="hover:text-tocantins-green transition-colors">Vouchers</a>
    <span class="mx-2">/</span>
    <span class="text-tocantins-green font-medium">Pedidos de Motoristas</span>
@endsection

@section('page-title', 'Pedidos de Motoristas')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-2xl">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-2xl">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Link de cadastro para motoristas</h2>
                <p class="text-sm text-gray-500">Envie este link para o motorista preencher o cadastro.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input type="text" id="driverLink" value="{{ route('driver-request.create') }}" readonly class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm font-mono text-gray-700">
            <button onclick="navigator.clipboard.writeText(document.getElementById('driverLink').value); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',2000)" class="px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700 transition-colors">Copiar</button>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-gray-800">Pedidos recebidos</h2>
                <span class="text-xs text-gray-500">{{ $requests->total() }} registro(s)</span>
            </div>
            <form id="bulkDeleteForm" method="POST" action="{{ route('admin.driver-requests.bulk-destroy') }}" onsubmit="return confirmBulkDelete()">
                @csrf
                @method('DELETE')
                <button id="bulkDeleteButton" type="submit" disabled class="px-3 py-2 bg-red-100 text-red-700 rounded-xl text-xs font-semibold opacity-50 cursor-not-allowed transition-colors">
                    Excluir selecionados (0)
                </button>
            </form>
        </div>

        @if($requests->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-center w-10">
                            <input type="checkbox" id="selectAllRequests" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" onchange="toggleAllRequests(this)">
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Motorista</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Telefone</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">CPF</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Onibus</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Obs</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Data</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($requests as $req)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" class="request-checkbox rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" value="{{ $req->id }}" onchange="updateBulkDeleteState()">
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $req->name }}</td>
                        <td class="px-4 py-3 font-mono text-gray-700 text-xs">{{ $req->phone }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $req->document }}</td>
                        <td class="px-4 py-3 text-gray-800 text-xs font-semibold">{{ $req->bus_number }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs max-w-[150px] truncate">{{ $req->observation ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match($req->status) {
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'approved' => 'bg-green-100 text-green-700',
                                    'rejected' => 'bg-red-100 text-red-700',
                                };
                                $label = match($req->status) {
                                    'pending' => 'Pendente',
                                    'approved' => 'Aprovado',
                                    'rejected' => 'Rejeitado',
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ $label }}</span>
                            @if($req->voucher)
                            <div class="text-[10px] text-gray-400 mt-1">Voucher: {{ $req->voucher->code }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="relative inline-flex justify-center">
                                <button type="button" onclick="toggleRequestActions('request-actions-{{ $req->id }}')" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-semibold transition-colors">
                                    Ações
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div id="request-actions-{{ $req->id }}" class="request-actions-menu hidden absolute right-0 top-full z-30 mt-1 w-40 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                                    @if($req->status === 'pending')
                                    <button type="button" onclick="openApproveModal({{ $req->id }}, '{{ addslashes($req->name) }}')" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Aprovar</button>
                                    <form method="POST" action="{{ route('admin.driver-requests.reject', $req) }}" onsubmit="return confirm('Rejeitar pedido de {{ addslashes($req->name) }}?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-red-700 hover:bg-red-50">Rejeitar</button>
                                    </form>
                                    @else
                                    <div class="px-3 py-2 text-left text-[11px] text-gray-400">Processado por {{ $req->approver?->name ?? '-' }}</div>
                                    @endif
                                    <form method="POST" action="{{ route('admin.driver-requests.destroy', $req) }}" onsubmit="return confirm('Excluir pedido de {{ addslashes($req->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-red-700 hover:bg-red-50">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-6 border-t border-gray-100">{{ $requests->links() }}</div>
        @else
        <div class="p-12 text-center text-gray-500">
            <span class="block text-4xl mb-3">📋</span>
            <p>Nenhum pedido de motorista recebido.</p>
        </div>
        @endif
    </div>
</div>

{{-- Modal de aprovacao --}}
<div id="approveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Aprovar motorista</h3>
            <button type="button" onclick="closeApproveModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-4">Criar voucher para: <span id="approveDriverName" class="font-semibold text-gray-800"></span></p>
        <form id="approveForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de voucher</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 p-3 border-2 border-emerald-500 bg-emerald-50 rounded-xl cursor-pointer" id="labelLimited">
                            <input type="radio" name="voucher_type" value="limited" checked onchange="toggleType()" class="sr-only">
                            <span class="text-sm font-semibold text-gray-800">Limitado</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 border-2 border-gray-200 rounded-xl cursor-pointer" id="labelUnlimited">
                            <input type="radio" name="voucher_type" value="unlimited" onchange="toggleType()" class="sr-only">
                            <span class="text-sm font-semibold text-gray-800">Ilimitado</span>
                        </label>
                    </div>
                </div>
                <div id="hoursContainer">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Horas por dia</label>
                    <input type="number" name="daily_hours" value="2" min="0.01" max="24" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    <p class="text-xs text-gray-400 mt-1">Ex: 2.5 = 2h30min</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Intervalo entre ativacoes (horas)</label>
                    <input type="number" name="activation_interval_hours" value="24" min="0.01" max="168" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiracao (opcional)</label>
                    <input type="date" name="expires_at" min="{{ date('Y-m-d', strtotime('+1 day')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                </div>
            </div>
            <div class="mt-6 flex gap-3 justify-end">
                <button type="button" onclick="closeApproveModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-medium">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700 transition-colors">Aprovar e criar voucher</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeRequestActionMenus(exceptId = null) {
    document.querySelectorAll('.request-actions-menu').forEach(menu => {
        if (menu.id !== exceptId) menu.classList.add('hidden');
    });
}

function toggleRequestActions(menuId) {
    const menu = document.getElementById(menuId);
    if (!menu) return;
    const willOpen = menu.classList.contains('hidden');
    closeRequestActionMenus(menuId);
    menu.classList.toggle('hidden', !willOpen);
}

function openApproveModal(id, name) {
    closeRequestActionMenus();
    document.getElementById('approveForm').action = '/admin/pedidos-motoristas/' + id + '/aprovar';
    document.getElementById('approveDriverName').textContent = name;
    document.getElementById('approveModal').classList.remove('hidden');
    document.getElementById('approveModal').classList.add('flex');
}
function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('approveModal').classList.remove('flex');
}
document.getElementById('approveModal').addEventListener('click', function(e) { if (e.target === this) closeApproveModal(); });

function updateBulkDeleteState() {
    const selected = Array.from(document.querySelectorAll('.request-checkbox:checked'));
    const form = document.getElementById('bulkDeleteForm');
    const button = document.getElementById('bulkDeleteButton');
    const selectAll = document.getElementById('selectAllRequests');
    const all = Array.from(document.querySelectorAll('.request-checkbox'));

    form.querySelectorAll('input[name="request_ids[]"]').forEach(input => input.remove());
    selected.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'request_ids[]';
        input.value = checkbox.value;
        form.appendChild(input);
    });

    button.textContent = `Excluir selecionados (${selected.length})`;
    button.disabled = selected.length === 0;
    button.classList.toggle('opacity-50', selected.length === 0);
    button.classList.toggle('cursor-not-allowed', selected.length === 0);
    button.classList.toggle('hover:bg-red-200', selected.length > 0);

    if (selectAll) {
        selectAll.checked = all.length > 0 && selected.length === all.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
    }
}

function toggleAllRequests(source) {
    document.querySelectorAll('.request-checkbox').forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    updateBulkDeleteState();
}

function confirmBulkDelete() {
    const count = document.querySelectorAll('.request-checkbox:checked').length;
    if (count === 0) return false;
    return confirm(`Excluir ${count} pedido(s) selecionado(s)?`);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.request-actions-menu') && !e.target.closest('button[onclick^="toggleRequestActions"]')) {
        closeRequestActionMenus();
    }
});

function toggleType() {
    const type = document.querySelector('input[name="voucher_type"]:checked').value;
    const hc = document.getElementById('hoursContainer');
    const ll = document.getElementById('labelLimited');
    const lu = document.getElementById('labelUnlimited');
    if (type === 'unlimited') {
        hc.classList.add('opacity-40', 'pointer-events-none');
        ll.className = ll.className.replace('border-emerald-500 bg-emerald-50', 'border-gray-200');
        lu.className = lu.className.replace('border-gray-200', 'border-blue-500 bg-blue-50');
    } else {
        hc.classList.remove('opacity-40', 'pointer-events-none');
        lu.className = lu.className.replace('border-blue-500 bg-blue-50', 'border-gray-200');
        ll.className = ll.className.replace('border-gray-200', 'border-emerald-500 bg-emerald-50');
    }
}
</script>
@endsection
