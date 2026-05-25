<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\Device;
use App\Models\Session;
use App\Models\SystemSetting;
use App\Models\TempBypassLog;
use App\Models\MikrotikMacReport;

class AdminController extends Controller
{
    /**
     * Dashboard principal
     */
    public function dashboard()
    {
        $stats = $this->getDashboardStats();
        $revenue_chart = $this->getRevenueChartData();
        $connections_chart = $this->getConnectionsChartData();
        $system_status = $this->getSystemStatus();
        $buses = \App\Models\Bus::orderByDesc('last_sync_at')->get();

        return view('admin.dashboard', compact(
            'stats',
            'revenue_chart',
            'connections_chart',
            'system_status',
            'buses'
        ));
    }

    /**
     * Obtém estatísticas do dashboard
     */
    private function getDashboardStats()
    {
        $connectedUsers = User::whereIn('status', ['connected', 'active', 'temp_bypass'])
            ->where('expires_at', '>', now())
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->count();

        $dailyRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('amount');

        $yesterdayRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', today()->subDay())
            ->sum('amount');

        $pendingCount = Payment::where('status', 'pending')
            ->whereDate('created_at', today())
            ->count();

        $pendingAmount = Payment::where('status', 'pending')
            ->whereDate('created_at', today())
            ->sum('amount');

        $weekRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('amount');

        $monthRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('amount');

        return [
            'connected_users' => $connectedUsers,
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'daily_revenue' => $dailyRevenue,
            'yesterday_revenue' => $yesterdayRevenue,
            'week_revenue' => $weekRevenue,
            'month_revenue' => $monthRevenue,
            'pending_payments' => $pendingAmount,
            'pending_payments_count' => $pendingCount,
            'total_devices' => Device::count(),
            'active_vouchers' => Voucher::where('is_active', true)
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->count(),
            'total_users' => User::count(),
            'today_payments_count' => Payment::where('status', 'completed')
                ->whereDate('created_at', today())
                ->count(),
            'yesterday_payments_count' => Payment::where('status', 'completed')
                ->whereDate('created_at', today()->subDay())
                ->count(),
            'temp_bypass_active' => User::where('status', 'temp_bypass')
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }

    /**
     * Obtém usuários conectados (inclui temp_bypass e active)
     */
    private function getConnectedUsers()
    {
        return User::whereIn('status', ['connected', 'active', 'temp_bypass'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->orderBy('connected_at', 'desc')
            ->limit(15)
            ->get();
    }

    /**
     * Status real do sistema
     */
    private function getSystemStatus()
    {
        $status = [];

        // Database - testar conexão real
        try {
            DB::connection()->getPdo();
            $status['database'] = ['online' => true, 'detail' => 'Conectado'];
        } catch (\Exception $e) {
            $status['database'] = ['online' => false, 'detail' => 'Erro de conexão'];
        }

        // MikroTik - verificar última sincronização via MAC reports
        try {
            $lastReport = MikrotikMacReport::orderBy('reported_at', 'desc')->first();
            if ($lastReport && $lastReport->reported_at) {
                $diffMinutes = now()->diffInMinutes($lastReport->reported_at);
                if ($diffMinutes <= 5) {
                    $status['mikrotik'] = ['online' => true, 'detail' => "Sync há {$diffMinutes}min"];
                } elseif ($diffMinutes <= 30) {
                    $status['mikrotik'] = ['online' => true, 'detail' => "Sync há {$diffMinutes}min", 'warning' => true];
                } else {
                    $hours = floor($diffMinutes / 60);
                    $status['mikrotik'] = ['online' => false, 'detail' => "Sem sync há {$hours}h"];
                }
            } else {
                $status['mikrotik'] = ['online' => false, 'detail' => 'Nunca sincronizou'];
            }
        } catch (\Exception $e) {
            $status['mikrotik'] = ['online' => false, 'detail' => 'Erro ao verificar'];
        }

        // Gateway de Pagamento - verificar se tem configuração
        try {
            $gateway = SystemSetting::getValue('pix_gateway', config('wifi.payment_gateways.pix.gateway', ''));

            // Verificar token do gateway ativo
            $hasKey = false;
            if ($gateway === 'pagbank') {
                $hasKey = !empty(\App\Helpers\SettingsHelper::getPagBankToken());
            } elseif ($gateway === 'woovi') {
                $hasKey = !empty(config('wifi.payment_gateways.pix.woovi_app_id'));
            } elseif ($gateway === 'santander') {
                $hasKey = !empty(config('wifi.payment_gateways.pix.client_id'));
            } else {
                // fallback: qualquer chave genérica
                $hasKey = !empty(SystemSetting::getValue('gateway_api_key', ''));
            }

            if (!empty($gateway) && $hasKey) {
                $status['pagamentos'] = ['online' => true, 'detail' => ucfirst($gateway)];
            } elseif (!empty($gateway)) {
                $status['pagamentos'] = ['online' => true, 'detail' => ucfirst($gateway), 'warning' => true];
            } else {
                $status['pagamentos'] = ['online' => false, 'detail' => 'Não configurado'];
            }
        } catch (\Exception $e) {
            $status['pagamentos'] = ['online' => false, 'detail' => 'Erro ao verificar'];
        }

        // API Sync - verificar última chamada ao check-paid-users-lite via MikrotikMacReport
        try {
            $lastReport = \App\Models\MikrotikMacReport::orderBy('reported_at', 'desc')->first();
            if ($lastReport && $lastReport->reported_at) {
                $diffMin = now()->diffInMinutes($lastReport->reported_at);
                if ($diffMin <= 10) {
                    $status['api_sync'] = ['online' => true, 'detail' => "Sync há {$diffMin}min"];
                } elseif ($diffMin <= 60) {
                    $status['api_sync'] = ['online' => true, 'detail' => "Sync há {$diffMin}min", 'warning' => true];
                } else {
                    $status['api_sync'] = ['online' => false, 'detail' => 'Sem atividade recente'];
                }
            } else {
                // Sem reports mas verificar se há usuários ativos (script pode estar rodando sem reportar MACs)
                $hasActiveUsers = \App\Models\User::whereIn('status', ['connected', 'active', 'temp_bypass'])
                    ->where('expires_at', '>', now())
                    ->where('updated_at', '>=', now()->subMinutes(15))
                    ->exists();
                $status['api_sync'] = ['online' => $hasActiveUsers, 'detail' => $hasActiveUsers ? 'Ativo' : 'Sem atividade recente'];
            }
        } catch (\Exception $e) {
            $status['api_sync'] = ['online' => false, 'detail' => 'Erro'];
        }

        return $status;
    }

    /**
     * Últimos pagamentos
     */
    private function getRecentPayments()
    {
        return Payment::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Estatísticas de bypass temporário
     */
    private function getBypassStats()
    {
        $today = now()->startOfDay();
        return [
            'total_hoje' => TempBypassLog::where('created_at', '>=', $today)->count(),
            'aprovados_hoje' => TempBypassLog::where('created_at', '>=', $today)->where('was_denied', false)->count(),
            'negados_hoje' => TempBypassLog::where('created_at', '>=', $today)->where('was_denied', true)->count(),
        ];
    }

    /**
     * Dados para gráfico de receita
     */
    private function getRevenueChartData()
    {
        $days = collect();
        $revenues = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days->push($date->format('d/m'));
            
            $revenue = Payment::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('amount');
            
            $revenues->push((float) $revenue);
        }

        return [
            'labels' => $days->toArray(),
            'data' => $revenues->toArray()
        ];
    }

    /**
     * Dados para gráfico de conexões por hora
     */
    private function getConnectionsChartData()
    {
        $hours = collect();
        $connections = collect();

        for ($i = 23; $i >= 0; $i--) {
            $hour = now()->subHours($i)->format('H:00');
            $hours->push($hour);
            
            $count = Session::where('started_at', '>=', now()->subHours($i+1))
                ->where('started_at', '<', now()->subHours($i))
                ->count();
            
            $connections->push($count);
        }

        return [
            'labels' => $hours->slice(-12)->values()->toArray(), // Últimas 12 horas
            'data' => $connections->slice(-12)->values()->toArray()
        ];
    }

    /**
     * Relatório de receitas
     */
    public function revenueReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payments = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $totalRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $paymentMethods = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('method')
            ->select('method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->get();

        return view('admin.revenue-report', compact(
            'payments',
            'totalRevenue',
            'paymentMethods',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Gerenciar vouchers
     */
    public function vouchers()
    {
        $vouchers = Voucher::orderBy('created_at', 'desc')->paginate(20);
        
        return view('admin.vouchers', compact('vouchers'));
    }

    /**
     * Criar voucher
     */
    public function createVoucher(Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
            'prefix' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'max_uses' => 'required|integer|min:1'
        ]);

        try {
            $vouchers = [];
            $prefix = $request->prefix ?? 'WIFI';

            for ($i = 0; $i < $request->quantity; $i++) {
                $code = $this->generateVoucherCode($prefix);
                
                $voucher = Voucher::create([
                    'code' => $code,
                    'description' => $request->description ?? 'Voucher gerado pelo admin',
                    'discount' => null,
                    'discount_percent' => 100, // Acesso gratuito
                    'expires_at' => $request->expires_at,
                    'max_uses' => $request->max_uses,
                    'used_count' => 0,
                    'is_active' => true
                ]);

                $vouchers[] = $voucher;
            }

            return redirect()->back()->with('success', "Criados {$request->quantity} vouchers com sucesso!");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erro ao criar vouchers: ' . $e->getMessage());
        }
    }

    /**
     * Desativar voucher
     */
    public function deactivateVoucher($id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $voucher->update(['is_active' => false]);

            // Se for uma requisição AJAX, retorna JSON
            if (request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Voucher desativado com sucesso!']);
            }

            return redirect()->back()->with('success', 'Voucher desativado com sucesso!');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Erro ao desativar voucher: ' . $e->getMessage()]);
            }
            
            return redirect()->back()->with('error', 'Erro ao desativar voucher: ' . $e->getMessage());
        }
    }

    /**
     * Dispositivos e Usuários que pagaram
     */
    public function devices(Request $request)
    {
        // Filtro de busca
        $search = $request->input('search', '');
        $statusFilter = $request->input('status', '');

        // Usuários que pagaram com MAC address - com filtros
        $paidUsersQuery = Payment::where('status', 'completed')
            ->whereHas('user', function($query) {
                $query->whereNotNull('mac_address')
                    ->where('mac_address', '!=', '');
            })
            ->with('user');

        if ($search) {
            $paidUsersQuery->whereHas('user', function($query) use ($search) {
                $query->where('mac_address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $paidUsersQuery->whereHas('user', function($query) use ($statusFilter) {
                if ($statusFilter === 'online') {
                    $query->whereIn('status', ['connected', 'active', 'temp_bypass'])
                          ->where('expires_at', '>', now());
                } elseif ($statusFilter === 'expired') {
                    $query->where(function($q) {
                        $q->where('status', 'expired')
                          ->orWhere('expires_at', '<=', now());
                    });
                }
            });
        }

        $paidUsers = $paidUsersQuery->orderBy('paid_at', 'desc')
            ->paginate(25, ['*'], 'paid_page')
            ->appends($request->query());

        // Dispositivos detectados (tabela devices) - sem relationship problemática
        $devicesQuery = Device::orderBy('last_seen', 'desc');

        if ($search) {
            $devicesQuery->where(function($q) use ($search) {
                $q->where('mac_address', 'like', "%{$search}%")
                  ->orWhere('device_name', 'like', "%{$search}%");
            });
        }

        $devices = $devicesQuery->paginate(30, ['*'], 'devices_page')
            ->appends($request->query());

        // Estatísticas dos usuários que pagaram
        $paidStats = [
            'total' => Payment::where('status', 'completed')
                ->whereHas('user', function($q) { $q->whereNotNull('mac_address')->where('mac_address', '!=', ''); })
                ->count(),
            'online' => User::whereIn('status', ['connected', 'active', 'temp_bypass'])
                ->where('expires_at', '>', now())
                ->whereNotNull('mac_address')
                ->where('mac_address', '!=', '')
                ->whereHas('payments', function($q) { $q->where('status', 'completed'); })
                ->count(),
            'active' => User::whereNotNull('mac_address')
                ->where('mac_address', '!=', '')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->whereHas('payments', function($q) { $q->where('status', 'completed'); })
                ->count(),
            'expired' => User::whereNotNull('mac_address')
                ->where('mac_address', '!=', '')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->whereHas('payments', function($q) { $q->where('status', 'completed'); })
                ->count(),
            'today_revenue' => Payment::where('status', 'completed')
                ->whereDate('paid_at', today())
                ->sum('amount'),
            'today_payments' => Payment::where('status', 'completed')
                ->whereDate('paid_at', today())
                ->count(),
        ];

        return view('admin.devices', compact('devices', 'paidUsers', 'paidStats', 'search', 'statusFilter'));
    }

    /**
     * Logs de conexão
     */
    public function connectionLogs()
    {
        $sessions = Session::with(['user', 'payment'])
            ->orderBy('started_at', 'desc')
            ->paginate(50);

        return view('admin.connection-logs', compact('sessions'));
    }

    /**
     * Configurações do sistema
     */
    public function settings()
    {
        return view('admin.settings');
    }

    /**
     * API para obter estatísticas em tempo real
     */
    public function apiStats()
    {
        return response()->json([
            'connected_users' => User::where('status', 'connected')->count(),
            'daily_revenue' => Payment::where('status', 'completed')
                ->whereDate('created_at', today())
                ->sum('amount'),
            'total_devices' => Device::count(),
            'active_sessions' => Session::where('session_status', 'active')->count()
        ]);
    }

    /**
     * Exportar relatório
     */
    public function exportReport(Request $request)
    {
        $type = $request->get('type', 'payments');
        $format = $request->get('format', 'csv');
        
        // Implementar exportação (CSV, Excel, PDF)
        
        return response()->json(['message' => 'Export em desenvolvimento']);
    }

    /**
     * Gera código único para voucher
     */
    private function generateVoucherCode($prefix = 'WIFI')
    {
        do {
            $code = $prefix . '_' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /**
     * Gerenciar usuários
     */
    public function users(Request $request)
    {
        $query = User::with('payments');

        // Busca: nome, email, telefone
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filtro: status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro: tipo de acesso (tabs)
        // Aceita: 'all' (default), 'user', 'manager', 'admin'
        $role = $request->get('role', 'all');
        if ($role === 'user') {
            // Considera "usuário comum" tudo que NÃO é admin/manager (inclui null)
            $query->where(function ($q) {
                $q->whereNull('role')
                  ->orWhereNotIn('role', ['admin', 'manager']);
            });
        } elseif (in_array($role, ['admin', 'manager'], true)) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Estatísticas para a página (sempre da base toda)
        $stats = [
            'total_users' => User::count(),
            'connected_users' => User::where('status', 'connected')->count(),
            'today_registrations' => User::whereDate('created_at', today())->count(),
            'users_with_payments' => User::whereHas('payments', function ($q) {
                $q->where('status', 'completed');
            })->count(),
        ];

        // Contagem por nível de acesso para mostrar nas abas
        $roleCounts = [
            'all' => $stats['total_users'],
            'user' => User::where(function ($q) {
                $q->whereNull('role')->orWhereNotIn('role', ['admin', 'manager']);
            })->count(),
            'manager' => User::where('role', 'manager')->count(),
            'admin' => User::where('role', 'admin')->count(),
        ];

        return view('admin.users', compact('users', 'stats', 'roleCounts', 'role'));
    }

    /**
     * Formulário para criar novo usuário
     */
    public function createUser()
    {
        return view('admin.users-create');
    }

    /**
     * Armazenar novo usuário
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:user,manager,admin',
            'status' => 'required|in:active,pending,offline',
            'mac_address' => 'nullable|string|max:17',
            'ip_address' => 'nullable|ip',
            'device_name' => 'nullable|string|max:255',
            'allowed_modules' => 'nullable|array',
            'allowed_modules.*' => 'string|in:' . implode(',', array_keys(User::AVAILABLE_MODULES)),
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'status' => $request->status,
                'mac_address' => $request->mac_address,
                'ip_address' => $request->ip_address,
                'device_name' => $request->device_name,
                'allowed_modules' => $request->role === 'manager' ? ($request->allowed_modules ?? []) : null,
                'registered_at' => now(),
                'email_verified_at' => now(),
            ]);

            return redirect()->route('admin.users')
                ->with('success', 'Usuário criado com sucesso!');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Erro ao criar usuário: ' . $e->getMessage());
        }
    }

    /**
     * Formulário para editar usuário
     */
    public function editUser($id)
    {
        $user = User::findOrFail($id);
        return view('admin.users-edit', compact('user'));
    }

    /**
     * Atualizar usuário
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:user,manager,admin',
            'status' => 'required|in:active,pending,offline,connected',
            'mac_address' => 'nullable|string|max:17',
            'ip_address' => 'nullable|ip',
            'device_name' => 'nullable|string|max:255',
            'allowed_modules' => 'nullable|array',
            'allowed_modules.*' => 'string|in:' . implode(',', array_keys(User::AVAILABLE_MODULES)),
        ];

        // Se a senha foi fornecida, adicionar validação
        if ($request->filled('password')) {
            $rules['password'] = 'required|string|min:6|confirmed';
        }

        $request->validate($rules);

        try {
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => $request->role,
                'status' => $request->status,
                'mac_address' => $request->mac_address,
                'ip_address' => $request->ip_address,
                'device_name' => $request->device_name,
                'allowed_modules' => $request->role === 'manager' ? ($request->allowed_modules ?? []) : null,
            ];

            // Atualizar senha apenas se foi fornecida
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            }

            $user->update($data);

            return redirect()->route('admin.users')
                ->with('success', 'Usuário atualizado com sucesso!');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
        }
    }

    /**
     * Obter detalhes de um usuário
     */
    public function getUserDetails($id)
    {
        $user = User::with(['payments', 'sessions'])->findOrFail($id);
        
        return response()->json($user);
    }

    /**
     * Desconectar usuário
     */
    public function disconnectUser($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Atualizar status do usuário
            $user->update([
                'status' => 'offline',
                'expires_at' => null,
                'connected_at' => null
            ]);

            // Finalizar sessões ativas
            $user->sessions()->where('session_status', 'active')->update([
                'session_status' => 'ended',
                'ended_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuário desconectado com sucesso!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desconectar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir usuário
     */
    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Verificar se é um administrador
            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível excluir usuários administradores!'
                ], 403);
            }

            // Finalizar sessões ativas antes de excluir
            $user->sessions()->where('session_status', 'active')->update([
                'session_status' => 'ended',
                'ended_at' => now()
            ]);

            // Excluir o usuário (soft delete se estiver configurado)
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuário excluído com sucesso!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiSettings()
    {
        $currentGateway = SystemSetting::getValue('pix_gateway', 'santander');

        $gateways = [
            'santander' => 'Santander (Recomendado)',
            'woovi' => 'Woovi',
            'pagbank' => 'PagBank',
        ];

        return view('admin.api-settings', compact('currentGateway', 'gateways'));
    }

    public function updateGateway(Request $request)
    {
        $request->validate([
            'pix_gateway' => 'required|in:santander,woovi,pagbank',
        ]);

        SystemSetting::setValue('pix_gateway', $request->pix_gateway);

        return redirect()->route('admin.api')->with('success', 'Gateway PIX atualizado com sucesso!');
    }
}
