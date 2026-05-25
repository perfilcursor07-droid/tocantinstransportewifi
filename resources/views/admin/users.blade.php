@extends('layouts.admin')

@section('title', 'Usuarios')

@section('content')
<div class="max-w-8xl mx-auto px-4 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Usuarios</h1>
            <p class="text-sm text-gray-500 mt-1">Gerencie contas, status e acessos</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Usuario
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-3">
            <div class="flex-shrink-0 w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-emerald-800 text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-3">
            <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <p class="text-red-800 text-sm font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Cards de estatísticas --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-4m-6 6H6v-2a4 4 0 014-4m2-6a4 4 0 11-8 0 4 4 0 018 0m6 6a4 4 0 100-8 4 4 0 000 8z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total</p>
                    <p class="text-xl font-bold text-gray-800">{{ $stats['total_users'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-emerald-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Conectados</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $stats['connected_users'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-purple-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Cadastros Hoje</p>
                    <p class="text-xl font-bold text-purple-600">{{ $stats['today_registrations'] }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-amber-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Com Pagamentos</p>
                    <p class="text-xl font-bold text-amber-600">{{ $stats['users_with_payments'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Abas por Nível de Acesso --}}
    @php
        $tabs = [
            'all'     => ['label' => 'Todos',          'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'blue'],
            'user'    => ['label' => 'Usuarios',       'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'color' => 'gray'],
            'manager' => ['label' => 'Gestores',       'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z', 'color' => 'purple'],
            'admin'   => ['label' => 'Administradores','icon' => 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.745 3.745 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z', 'color' => 'red'],
        ];
        $colorMap = [
            'blue'   => ['active' => 'bg-blue-600 text-white shadow-sm',    'inactive' => 'text-gray-600 hover:bg-gray-100', 'badge' => 'bg-blue-100 text-blue-700'],
            'gray'   => ['active' => 'bg-gray-700 text-white shadow-sm',    'inactive' => 'text-gray-600 hover:bg-gray-100', 'badge' => 'bg-gray-200 text-gray-700'],
            'purple' => ['active' => 'bg-purple-600 text-white shadow-sm',  'inactive' => 'text-gray-600 hover:bg-gray-100', 'badge' => 'bg-purple-100 text-purple-700'],
            'red'    => ['active' => 'bg-red-600 text-white shadow-sm',     'inactive' => 'text-gray-600 hover:bg-gray-100', 'badge' => 'bg-red-100 text-red-700'],
        ];
    @endphp
    <div class="bg-white rounded-xl border shadow-sm mb-4 p-2">
        <div class="flex flex-wrap gap-1">
            @foreach($tabs as $key => $tab)
                @php
                    $isActive = ($role ?? 'all') === $key;
                    $cls = $colorMap[$tab['color']];
                    $params = request()->except('role', 'page');
                    $params['role'] = $key;
                @endphp
                <a href="{{ route('admin.users') . '?' . http_build_query($params) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition {{ $isActive ? $cls['active'] : $cls['inactive'] }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/></svg>
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $isActive ? 'bg-white/25 text-white' : $cls['badge'] }}">
                        {{ $roleCounts[$key] ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Filtros (server-side) --}}
    <div class="bg-white rounded-xl border shadow-sm mb-6 p-4">
        <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            {{-- Manter o filtro de tipo selecionado --}}
            <input type="hidden" name="role" value="{{ $role ?? 'all' }}">

            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="relative">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Buscar por nome, email ou telefone..."
                           class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos os Status</option>
                    <option value="connected" {{ request('status') === 'connected' ? 'selected' : '' }}>Conectados</option>
                    <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Ativo</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Buscar
                </button>
                <a href="{{ route('admin.users', ['role' => $role ?? 'all']) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Limpar
                </a>
            </div>
        </form>

        @if(request()->hasAny(['search', 'status']) && (request('search') || request('status')))
        <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-2 text-xs">
            <span class="font-semibold text-gray-500">Filtros aplicados:</span>
            @if(request('search'))
            <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                Busca: "{{ request('search') }}"
            </span>
            @endif
            @if(request('status'))
            <span class="px-2 py-1 bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-200">
                Status: {{ ucfirst(request('status')) }}
            </span>
            @endif
            <span class="ml-auto text-gray-500">{{ $users->total() }} resultado(s)</span>
        </div>
        @endif
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
            <div class="flex items-center justify-between">
                <h3 class="text-gray-800 font-semibold">
                    @switch($role ?? 'all')
                        @case('user')      Usuarios @break
                        @case('manager')   Gestores @break
                        @case('admin')     Administradores @break
                        @default           Lista de Usuarios
                    @endswitch
                </h3>
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">{{ $users->total() }} registros</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Usuario</th>
                        <th class="px-4 py-3 text-left">Contato</th>
                        <th class="px-4 py-3 text-left">Dispositivo</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-left">Último login</th>
                        <th class="px-4 py-3 text-left">Cadastro</th>
                        <th class="px-4 py-3 text-center">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                    @php
                        $statusConfig = [
                            'connected' => ['label' => 'Conectado', 'color' => 'bg-emerald-100 text-emerald-700', 'dot' => 'bg-emerald-500'],
                            'offline' => ['label' => 'Offline', 'color' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-400'],
                            'pending' => ['label' => 'Pendente', 'color' => 'bg-amber-100 text-amber-700', 'dot' => 'bg-amber-500'],
                            'active' => ['label' => 'Ativo', 'color' => 'bg-blue-100 text-blue-700', 'dot' => 'bg-blue-500'],
                        ];
                        $status = $statusConfig[$user->status] ?? ['label' => 'Desconhecido', 'color' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-400'];

                        $roleConfig = [
                            'admin' => ['label' => 'Admin', 'color' => 'bg-red-100 text-red-700'],
                            'manager' => ['label' => 'Gerente', 'color' => 'bg-purple-100 text-purple-700'],
                            'user' => ['label' => 'Usuario', 'color' => 'bg-blue-100 text-blue-700'],
                        ];
                        $userRole = $roleConfig[$user->role] ?? ['label' => 'Usuario', 'color' => 'bg-blue-100 text-blue-700'];

                        // Último login: prioriza last_login_at; cai para connected_at se vazio
                        $lastLogin = $user->last_login_at ?? $user->connected_at;
                        if ($lastLogin) {
                            $lastLoginDate = $lastLogin->format('d/m/Y');
                            $lastLoginTime = $lastLogin->format('H:i');
                            $lastLoginRelative = $lastLogin->diffForHumans(['short' => true]);
                        }
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-semibold">
                                    {{ $user->name ? strtoupper(substr($user->name, 0, 2)) : '??' }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $user->name ?? 'Nome nao informado' }}</p>
                                    <p class="text-xs text-gray-400">ID: {{ $user->id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm text-gray-700">{{ $user->email ?? 'Email nao informado' }}</p>
                            <p class="text-xs text-gray-400">{{ $user->phone ?? 'Telefone nao informado' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm text-gray-700">{{ $user->mac_address ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-400">{{ $user->ip_address ?? 'IP nao registrado' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $status['color'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>
                                {{ $status['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $userRole['color'] }}">{{ $userRole['label'] }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($lastLogin)
                                <p class="text-sm text-gray-700 font-medium">{{ $lastLoginDate }} <span class="text-gray-400">·</span> {{ $lastLoginTime }}</p>
                                <p class="text-xs text-gray-400">{{ $lastLoginRelative }}@if(!$user->last_login_at && $user->connected_at) <span class="italic">(conexão)</span>@endif</p>
                            @else
                                <span class="text-xs text-gray-400 italic">Nunca</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : 'N/A' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="viewUser({{ $user->id }})" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Visualizar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <button onclick="editUser({{ $user->id }})" class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-1-1v2m-6 2h12m-1 5.5l-4.5 4.5H7v-3.5l4.5-4.5a2.121 2.121 0 013 0l.5.5a2.121 2.121 0 010 3l-.5.5"/></svg>
                                </button>
                                @if($user->status === 'connected')
                                <button onclick="disconnectUser({{ $user->id }})" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Desconectar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                </button>
                                @endif
                                <button onclick="deleteUser({{ $user->id }})" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Excluir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-3h4m-4 0a1 1 0 00-1 1v1h6V5a1 1 0 00-1-1m-4 0h4"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-4m-6 6H6v-2a4 4 0 014-4m2-6a4 4 0 11-8 0 4 4 0 018 0m6 6a4 4 0 100-8 4 4 0 000 8z"/></svg>
                                </div>
                                <p class="text-gray-500 text-sm font-medium">Nenhum usuario encontrado</p>
                                <p class="text-gray-400 text-xs mt-1">Tente ajustar os filtros ou a busca</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('modals')
<div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center h-full p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-screen overflow-y-auto">
            <div class="p-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Detalhes do Usuario</h3>
                    <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
                </div>
                <div id="userModalContent"></div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function viewUser(userId) {
    // Mapeamento MikroTik serial → número do carro
    const busMap = {
        'HH50A914NK5': '3097',
        'HH50A7TMT8M': '3099',
        'HH60A2NSBE7': '5013',
        'HH50AB8F056': '5021',
        'HGD09YS6037': '5023',
        'HGK09Q76FMP': '5031',
        'HH50A2ER2JB': '5033',
        'HGJ09X2F8FD': '5035',
    };

    const formatDateTime = (iso) => {
        if (!iso) return null;
        try {
            const d = new Date(iso);
            return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch (e) { return iso; }
    };

    fetch(`/admin/users/${userId}`)
        .then(response => response.json())
        .then(data => {
            const mikrotikId = data.last_mikrotik_id || null;
            const busNumber = mikrotikId ? (busMap[mikrotikId] || 'Desconhecido') : null;
            const busDisplay = mikrotikId
                ? `<span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-blue-50 border border-blue-200"><svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 4h8m-4 4v4m-4-4h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg><span class="text-sm font-semibold text-blue-700">Carro ${busNumber}</span><span class="text-xs text-blue-500">(${mikrotikId})</span></span>`
                : '<span class="text-sm text-gray-400">Não identificado</span>';

            const lastLogin = formatDateTime(data.last_login_at) || formatDateTime(data.connected_at) || 'Nunca';

            document.getElementById('userModalContent').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <p class="mt-1 text-sm text-gray-900">${data.name || 'Nao informado'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <p class="mt-1 text-sm text-gray-900">${data.email || 'Nao informado'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Telefone</label>
                            <p class="mt-1 text-sm text-gray-900">${data.phone || 'Nao informado'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <p class="mt-1 text-sm text-gray-900">${data.status || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">MAC Address</label>
                            <p class="mt-1 text-sm text-gray-900">${data.mac_address || 'Nao registrado'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">IP Address</label>
                            <p class="mt-1 text-sm text-gray-900">${data.ip_address || 'Nao registrado'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Último Login</label>
                            <p class="mt-1 text-sm text-gray-900">${lastLogin}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">IP do Login</label>
                            <p class="mt-1 text-sm text-gray-900">${data.last_login_ip || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Último Ônibus Conectado</label>
                        ${busDisplay}
                    </div>
                </div>
            `;
            document.getElementById('userModal').classList.remove('hidden');
        })
        .catch(() => {
            alert('Erro ao carregar dados do usuario');
        });
}

function editUser(userId) {
    window.location.href = `/admin/users/${userId}/edit`;
}

function disconnectUser(userId) {
    if (!confirm('Deseja realmente desconectar este usuario?')) return;

    fetch(`/admin/users/${userId}/disconnect`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao desconectar usuario');
            }
        });
}

function deleteUser(userId) {
    if (!confirm('Deseja realmente excluir este usuario? Esta acao nao pode ser desfeita.')) return;

    fetch(`/admin/users/${userId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir usuario');
            }
        });
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
}

document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeUserModal();
});
</script>
@endpush
