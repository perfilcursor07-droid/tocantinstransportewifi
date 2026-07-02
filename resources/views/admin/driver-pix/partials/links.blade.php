<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-border shadow-card p-6">
        <h2 class="text-sm font-bold text-ink mb-1">Gerar link de cadastro</h2>
        <p class="text-xs text-muted mb-4">Crie um link e envie ao motorista para ele cadastrar nome, chave PIX e número do ônibus.</p>
        <form action="{{ route('admin.driver-pix.links.store') }}" method="POST" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-muted mb-1">Identificação (opcional)</label>
                <input type="text" name="label" placeholder="Ex: Lote ônibus 5013" class="w-full px-3 py-2 border border-border rounded-xl text-sm">
            </div>
            <div class="min-w-[180px]">
                <label class="block text-xs font-medium text-muted mb-1">Expira em (opcional)</label>
                <input type="datetime-local" name="expires_at" class="w-full px-3 py-2 border border-border rounded-xl text-sm">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-green text-white rounded-xl text-sm font-semibold hover:bg-green-dark">Gerar link</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-border shadow-card overflow-hidden">
        <div class="p-4 border-b border-border flex justify-between items-center">
            <h3 class="text-sm font-bold text-ink">Links gerados</h3>
            <span class="text-xs text-muted">{{ $links->total() }} link(s)</span>
        </div>
        @if($links->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Link</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Identificação</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Usos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-muted">Criado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-muted">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($links as $link)
                    <tr class="hover:bg-surface/50">
                        <td class="px-4 py-3">
                            <input type="text" id="link-{{ $link->id }}" value="{{ $link->publicUrl() }}" readonly class="w-full max-w-xs px-2 py-1 text-[10px] font-mono bg-surface border border-border rounded-lg">
                            <button type="button" onclick="copyText('link-{{ $link->id }}')" class="text-[10px] text-blue font-semibold mt-1">Copiar</button>
                        </td>
                        <td class="px-4 py-3 text-ink2">{{ $link->label ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $link->uses_count }} / {{ $link->profiles_count }}</td>
                        <td class="px-4 py-3">
                            @if($link->isUsable())
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-pale text-green">Ativo</span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-pale text-red">Inativo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $link->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">
                            <form action="{{ route('admin.driver-pix.links.toggle', $link) }}" method="POST" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs font-semibold {{ $link->is_active ? 'text-red' : 'text-green' }}">
                                    {{ $link->is_active ? 'Desativar' : 'Ativar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $links->appends(['tab' => 'links'])->links() }}</div>
        @else
        <p class="p-6 text-sm text-muted text-center">Nenhum link gerado ainda.</p>
        @endif
    </div>
</div>
