<!-- Sidebar -->
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-40 transform transition-all duration-300 ease-in-out
              -translate-x-full lg:translate-x-0 flex flex-col bg-white border-r border-border shadow-card
              w-64 sidebar-expanded">

    <!-- Brand -->
    <div class="flex items-center justify-between h-14 px-3 border-b border-border flex-shrink-0">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 min-w-0">
            <div class="w-9 h-9 bg-gradient-to-br from-green-dark to-green rounded-xl flex items-center justify-center shadow-card flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                </svg>
            </div>
            <div class="min-w-0 sidebar-label">
                <p class="text-[9px] font-bold uppercase tracking-widest text-green leading-none">Starlink</p>
                <h1 class="text-sm font-bold text-ink leading-tight truncate">Tocantins</h1>
            </div>
        </a>
        <div class="flex items-center gap-1">
            <!-- Collapse toggle (desktop) -->
            <button onclick="collapseSidebar()" id="collapseBtn"
                    class="hidden lg:flex text-muted hover:text-ink p-1.5 hover:bg-surface rounded-lg transition-colors"
                    title="Recolher menu">
                <svg id="collapseIcon" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
            <!-- Close (mobile) -->
            <button onclick="toggleSidebar()" class="lg:hidden text-muted hover:text-ink p-1.5 hover:bg-surface rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-2 py-3 overflow-y-auto space-y-4">

        <!-- Principal -->
        <div>
            <p class="px-2 mb-1 text-[9px] font-bold text-muted uppercase tracking-widest sidebar-label">Principal</p>

            @php
                $navItems = [
                    ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard', 'color' => 'green', 'module' => 'dashboard',
                     'icon' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z'],
                    ['route' => 'admin.reports', 'match' => 'admin.reports*', 'label' => 'Relatórios', 'color' => 'green', 'module' => 'reports',
                     'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ];
            @endphp
            @foreach($navItems as $item)
                @if(Auth::user()->hasModule($item['module']))
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" onclick="closeSidebarOnMobile()" title="{{ $item['label'] }}"
                   class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                          {{ $active ? 'bg-'.$item['color'].' text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                    <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                        </svg>
                    </span>
                    <span class="sidebar-label truncate">{{ $item['label'] }}</span>
                </a>
                @endif
            @endforeach
        </div>

        <!-- Gestão -->
        <div>
            <p class="px-2 mb-1 text-[9px] font-bold text-muted uppercase tracking-widest sidebar-label">Gestão</p>

            @if(Auth::user()->role === 'admin')
            @php $active = request()->routeIs('admin.users*'); @endphp
            <a href="{{ route('admin.users') }}" onclick="closeSidebarOnMobile()" title="Usuários"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all
                      {{ $active ? 'bg-blue text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Usuários</span>
            </a>
            @endif

            @if(Auth::user()->hasModule('vouchers'))
            @php $active = request()->routeIs('admin.vouchers*'); @endphp
            <a href="{{ route('admin.vouchers.index') }}" onclick="closeSidebarOnMobile()" title="Vouchers"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-gold text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Vouchers</span>
            </a>
            @endif

            @if(Auth::user()->role === 'admin')
            @php $active = request()->routeIs('admin.devices*'); @endphp
            <a href="{{ route('admin.devices') }}" onclick="closeSidebarOnMobile()" title="Dispositivos"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-blue text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Dispositivos</span>
            </a>
            @endif
        </div>

        <!-- Comunicação -->
        <div>
            <p class="px-2 mb-1 text-[9px] font-bold text-muted uppercase tracking-widest sidebar-label">Comunicação</p>

            @if(Auth::user()->hasModule('chat') && Auth::user()->role === 'admin')
            @php $active = request()->routeIs('admin.chat*'); @endphp
            <a href="{{ route('admin.chat.index') }}" onclick="closeSidebarOnMobile()" title="Chat"
               class="sidebar-link relative flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all
                      {{ $active ? 'bg-green text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Chat</span>
                <span id="chat-unread-badge" class="hidden ml-auto min-w-[18px] h-[18px] px-1 bg-red text-white text-[9px] font-bold rounded-full flex items-center justify-center"></span>
            </a>
            @endif

            @if(Auth::user()->role === 'admin')
            @php $active = request()->routeIs('admin.whatsapp*'); @endphp
            <a href="{{ route('admin.whatsapp.index') }}" onclick="closeSidebarOnMobile()" title="WhatsApp"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-green text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">WhatsApp</span>
            </a>
            @endif

            @if(Auth::user()->hasModule('reviews'))
            @php $active = request()->routeIs('admin.reviews*'); @endphp
            <a href="{{ route('admin.reviews.index') }}" onclick="closeSidebarOnMobile()" title="Avaliações"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-gold text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.124 3.457a1 1 0 00.95.69h3.636c.969 0 1.371 1.24.588 1.81l-2.942 2.137a1 1 0 00-.364 1.118l1.124 3.457c.3.921-.755 1.688-1.539 1.118l-2.942-2.137a1 1 0 00-1.176 0l-2.942 2.137c-.783.57-1.838-.197-1.539-1.118l1.124-3.457a1 1 0 00-.364-1.118L2.75 8.884c-.783-.57-.38-1.81.588-1.81h3.636a1 1 0 00.95-.69l1.124-3.457z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Avaliações</span>
            </a>
            @endif
        </div>

        <!-- Sistema -->
        @if(Auth::user()->role === 'admin')
        <div>
            <p class="px-2 mb-1 text-[9px] font-bold text-muted uppercase tracking-widest sidebar-label">Sistema</p>

            @php $active = request()->routeIs('admin.mikrotik.health'); @endphp
            <a href="{{ route('admin.mikrotik.health') }}" onclick="closeSidebarOnMobile()" title="Saúde dos MikroTiks"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all
                      {{ $active ? 'bg-green text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Saúde</span>
            </a>

            @php $active = request()->routeIs('admin.mikrotik.remote*'); @endphp
            <a href="{{ route('admin.mikrotik.remote.index') }}" onclick="closeSidebarOnMobile()" title="MikroTik"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-red text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">MikroTik</span>
            </a>

            @php
                $active = request()->routeIs('admin.tickets*');
                $openTickets = \App\Models\ServiceTicket::openCount();
            @endphp
            <a href="{{ route('admin.tickets.index') }}" onclick="closeSidebarOnMobile()" title="Chamados"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-amber-500 text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Chamados</span>
                @if($openTickets > 0)
                    <span class="ml-auto w-5 h-5 flex items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold animate-pulse sidebar-label">{{ $openTickets }}</span>
                @endif
            </a>

            @php $active = request()->routeIs('admin.settings*'); @endphp
            <a href="{{ route('admin.settings.index') }}" onclick="closeSidebarOnMobile()" title="Configurações"
               class="sidebar-link flex items-center gap-3 px-2.5 py-2 rounded-xl text-sm font-semibold transition-all mt-0.5
                      {{ $active ? 'bg-ink text-white shadow-card' : 'text-ink2 hover:bg-surface hover:text-ink' }}">
                <span class="w-7 h-7 flex items-center justify-center rounded-lg flex-shrink-0 {{ $active ? 'bg-white/20' : 'bg-surface' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
                <span class="sidebar-label truncate">Configurações</span>
            </a>
        </div>
        @endif
    </nav>

    <!-- User -->
    <div class="p-2 border-t border-border flex-shrink-0">
        <div class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-surface transition-colors cursor-pointer" onclick="toggleDropdown()">
            <div class="w-8 h-8 bg-gradient-to-br from-green-dark to-green rounded-lg flex items-center justify-center flex-shrink-0 shadow-card">
                <span class="text-white text-xs font-bold">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
            </div>
            <div class="flex-1 min-w-0 sidebar-label">
                <p class="text-xs font-bold text-ink truncate">{{ Auth::user()->name }}</p>
                <p class="text-[10px] text-muted">{{ Auth::user()->role === 'admin' ? 'Admin' : 'Gestor' }}</p>
            </div>
            <svg class="w-4 h-4 text-muted flex-shrink-0 sidebar-label" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
            </svg>
        </div>
        <div id="userDropdown" class="hidden mt-1 p-1.5 rounded-xl bg-surface border border-border">
            <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-ink2 hover:text-ink hover:bg-white rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="sidebar-label">Meu Perfil</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-0.5">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-red hover:bg-red-pale rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span class="sidebar-label">Sair</span>
                </button>
            </form>
        </div>
    </div>
</aside>

<!-- Expand button (visible only when collapsed on desktop) -->
<button onclick="collapseSidebar()" id="expandBtn"
        class="hidden fixed top-4 left-4 z-50 w-10 h-10 bg-white border border-border text-ink rounded-xl shadow-hover items-center justify-center hover:bg-surface transition-colors"
        title="Expandir menu">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
    </svg>
</button>

<!-- Mobile toggle -->
<button onclick="toggleSidebar()" id="menuToggleBtn"
        class="lg:hidden fixed top-4 left-4 z-50 w-10 h-10 bg-white border border-border text-ink rounded-xl shadow-hover flex items-center justify-center hover:bg-surface transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Overlay mobile -->
<div id="sidebarOverlay" class="lg:hidden fixed inset-0 bg-black/40 backdrop-blur-sm z-30 hidden" onclick="toggleSidebar()"></div>

<style>
    .sidebar-collapsed { width: 4rem !important; }
    .sidebar-collapsed .sidebar-label { display: none !important; }
    .sidebar-collapsed .sidebar-link { justify-content: center; padding-left: 0; padding-right: 0; }
    .sidebar-collapsed .sidebar-link span:first-child { margin: 0; }
    .sidebar-collapsed nav > div > p.sidebar-label { display: none !important; }
    .sidebar-collapsed #userDropdown { display: none !important; }
    .sidebar-collapsed #collapseBtn { display: none !important; }
</style>

<script>
    const SIDEBAR_KEY = 'sidebarCollapsed';

    function collapseSidebar() {
        const sidebar = document.getElementById('sidebar');
        const main = document.querySelector('.lg\\:ml-64, .lg\\:ml-16');
        const expandBtn = document.getElementById('expandBtn');
        const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
        sidebar.classList.toggle('sidebar-expanded', !isCollapsed);

        if (main) {
            main.classList.toggle('lg:ml-64', !isCollapsed);
            main.classList.toggle('lg:ml-16', isCollapsed);
        }
        if (expandBtn) {
            expandBtn.classList.toggle('hidden', !isCollapsed);
            expandBtn.classList.toggle('lg:flex', isCollapsed);
        }
        localStorage.setItem(SIDEBAR_KEY, isCollapsed ? '1' : '0');
    }

    // Restore state on load
    (function() {
        if (localStorage.getItem(SIDEBAR_KEY) === '1') {
            const sidebar = document.getElementById('sidebar');
            const main = document.querySelector('.lg\\:ml-64');
            const expandBtn = document.getElementById('expandBtn');
            if (sidebar) {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.classList.remove('sidebar-expanded');
            }
            if (main) {
                main.classList.remove('lg:ml-64');
                main.classList.add('lg:ml-16');
            }
            if (expandBtn) {
                expandBtn.classList.remove('hidden');
                expandBtn.classList.add('lg:flex');
            }
        }
    })();

    function toggleDropdown() {
        document.getElementById('userDropdown')?.classList.toggle('hidden');
    }
    function toggleSidebar() {
        document.getElementById('sidebar')?.classList.toggle('-translate-x-full');
        document.getElementById('sidebarOverlay')?.classList.toggle('hidden');
    }
    function closeSidebarOnMobile() {
        if (window.innerWidth < 1024) {
            document.getElementById('sidebar')?.classList.add('-translate-x-full');
            document.getElementById('sidebarOverlay')?.classList.add('hidden');
        }
    }
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && !e.target.closest('[onclick="toggleDropdown()"]') && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
    @if(Auth::user()->hasModule('chat') && Auth::user()->role === 'admin')
    function checkUnreadMessages() {
        fetch('{{ route("admin.chat.unread") }}')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('chat-unread-badge');
                if (!badge) return;
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.classList.remove('hidden');
                } else { badge.classList.add('hidden'); }
            }).catch(() => {});
    }
    setInterval(checkUnreadMessages, 30000);
    checkUnreadMessages();
    @endif
</script>
