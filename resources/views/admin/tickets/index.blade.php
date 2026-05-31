@extends('layouts.admin')

@section('title', 'Chamados de Atendimento')

@section('content')
<div class="max-w-8xl mx-auto px-4 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🔧 Chamados de Atendimento</h1>
            <p class="text-sm text-gray-500 mt-1">Gerencie problemas e manutenções dos ônibus</p>
        </div>
        <a href="{{ route('admin.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Chamado
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-emerald-800 text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Abas: Chamados | Anotações -->
    <div class="flex border-b border-gray-200 mb-6">
        <a href="{{ route('admin.tickets.index', ['tab' => 'tickets', 'status' => $status]) }}"
           class="px-5 py-3 text-sm font-semibold border-b-2 transition {{ $tab === 'tickets' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            🔧 Chamados
            @if($openCount > 0)
                <span class="ml-1.5 px-1.5 py-0.5 bg-red-500 text-white text-[10px] rounded-full font-bold">{{ $openCount }}</span>
            @endif
        </a>
        <a href="{{ route('admin.tickets.index', ['tab' => 'notes']) }}"
           class="px-5 py-3 text-sm font-semibold border-b-2 transition {{ $tab === 'notes' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            📝 Anotações
        </a>
    </div>

    @if($tab === 'notes')
    <!-- Aba Anotações -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <form method="POST" action="{{ route('admin.tickets.save-notes') }}">
            @csrf
            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-2">Bloco de notas livre — senhas, configurações, lembretes, etc.</p>
                <textarea name="notes" rows="16"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono leading-relaxed"
                    placeholder="Ex:&#10;Starlink Ônibus 5013 - Senha: MinhaS3nha123&#10;MikroTik admin: KAO4E84OVY&#10;&#10;Notas gerais...">{{ $notes }}</textarea>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700 transition shadow-md">
                💾 Salvar Anotações
            </button>
        </form>
    </div>
    @else
    <!-- Aba Chamados -->

    <!-- Filtros -->
    <div class="flex gap-2 mb-6">
        <a href="{{ route('admin.tickets.index', ['status' => 'open']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $status === 'open' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            🟡 Abertos ({{ $openCount }})
        </a>
        <a href="{{ route('admin.tickets.index', ['status' => 'closed']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $status === 'closed' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            ✅ Encerrados ({{ $closedCount }})
        </a>
        <a href="{{ route('admin.tickets.index', ['status' => 'all']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $status === 'all' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            Todos
        </a>
    </div>

    <!-- Lista de Chamados -->
    <div class="space-y-3">
        @forelse($tickets as $ticket)
        <div class="bg-white rounded-xl border {{ $ticket->status === 'open' ? 'border-amber-200' : 'border-gray-200' }} p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        @if($ticket->status === 'open')
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse flex-shrink-0"></span>
                        @else
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                        @endif
                        <h3 class="text-sm font-bold text-gray-800 truncate">{{ $ticket->title }}</h3>
                    </div>
                    <p class="text-xs text-gray-500 line-clamp-2 mb-2">{{ $ticket->description }}</p>
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-400">
                        @if($ticket->bus_number)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 rounded font-medium">
                                🚌 Carro {{ $ticket->bus_number }} ({{ $ticket->mikrotik_id }})
                            </span>
                        @endif
                        @if($ticket->scheduled_date)
                            <span class="inline-flex items-center gap-1">
                                📅 {{ $ticket->scheduled_date->format('d/m/Y') }}
                            </span>
                        @endif
                        <span>Criado {{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                    @if($ticket->resolution)
                        <div class="mt-2 p-2 bg-emerald-50 rounded-lg border border-emerald-100">
                            <p class="text-xs text-emerald-700"><strong>Resolução:</strong> {{ $ticket->resolution }}</p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-1 flex-shrink-0">
                    <a href="{{ route('admin.tickets.edit', $ticket) }}" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100 transition text-center">
                        ✏️ Editar
                    </a>
                    @if($ticket->status === 'open')
                        <form method="POST" action="{{ route('admin.tickets.close', $ticket) }}" onsubmit="return openCloseModal(event, {{ $ticket->id }})">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-medium hover:bg-emerald-200 transition">
                                ✅ Encerrar
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.tickets.reopen', $ticket) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 bg-amber-100 text-amber-700 rounded-lg text-xs font-medium hover:bg-amber-200 transition">
                                🔄 Reabrir
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.tickets.destroy', $ticket) }}" onsubmit="return confirm('Excluir este chamado?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 transition w-full">
                            🗑️ Excluir
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-12 bg-white rounded-xl border border-gray-200">
            <p class="text-gray-400 text-sm">Nenhum chamado {{ $status === 'open' ? 'aberto' : ($status === 'closed' ? 'encerrado' : '') }}.</p>
        </div>
        @endforelse
    </div>

    {{ $tickets->links() }}
    @endif
</div>

<!-- Modal Encerrar Chamado -->
<div id="closeModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h3 class="text-lg font-bold text-gray-800 mb-3">Encerrar Chamado</h3>
        <p class="text-sm text-gray-500 mb-4">Descreva o que foi feito para resolver (opcional):</p>
        <form id="closeForm" method="POST">
            @csrf
            <textarea name="resolution" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Ex: Troquei o cabo ethernet, reiniciei o MikroTik..."></textarea>
            <div class="flex gap-2 mt-4">
                <button type="button" onclick="document.getElementById('closeModal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-200">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700">Encerrar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openCloseModal(event, ticketId) {
    event.preventDefault();
    const modal = document.getElementById('closeModal');
    const form = document.getElementById('closeForm');
    form.action = `/admin/chamados/${ticketId}/fechar`;
    modal.classList.remove('hidden');
    return false;
}
</script>
@endpush
@endsection
