@extends('layouts.admin')

@section('title', 'Editar Chamado')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Voltar
        </a>
        <h1 class="text-2xl font-bold text-gray-800 mt-2">✏️ Editar Chamado #{{ $ticket->id }}</h1>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="title" class="block text-sm font-bold text-gray-700 mb-1">Título do problema</label>
                <input type="text" name="title" id="title" value="{{ old('title', $ticket->title) }}" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-bold text-gray-700 mb-1">Descrição do problema</label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('description', $ticket->description) }}</textarea>
                @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mikrotik_id" class="block text-sm font-bold text-gray-700 mb-1">Ônibus / MikroTik</label>
                    <select name="mikrotik_id" id="mikrotik_id"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <option value="">— Selecione o ônibus —</option>
                        @foreach($busMap as $serial => $number)
                            <option value="{{ $serial }}" {{ old('mikrotik_id', $ticket->mikrotik_id) === $serial ? 'selected' : '' }}>
                                {{ $number }} - {{ $serial }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="scheduled_date" class="block text-sm font-bold text-gray-700 mb-1">Data do atendimento</label>
                    <input type="date" name="scheduled_date" id="scheduled_date" value="{{ old('scheduled_date', $ticket->scheduled_date?->format('Y-m-d')) }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                </div>
            </div>

            <div class="pt-3 flex gap-3">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-xl text-sm hover:bg-blue-700 transition shadow-md">
                    Salvar Alterações
                </button>
                <a href="{{ route('admin.tickets.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl text-sm hover:bg-gray-200 transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
