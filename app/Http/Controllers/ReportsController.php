<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\User;
use App\Models\Session;
use Carbon\Carbon;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        // Helper: normaliza data (aceita 'Y-m-d', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s')
        // Retorna sempre formato 'Y-m-d H:i:s' usando defaults se não vier hora
        $parseDate = function (?string $value, string $defaultTime = '00:00:00') {
            if (!$value) return null;
            try {
                // datetime-local manda 'Y-m-d\TH:i' -> Carbon::parse aceita
                $carbon = Carbon::parse($value);
                // Se a string original NÃO continha hora (formato puro Y-m-d), aplica o default
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    [$h, $m, $s] = explode(':', $defaultTime);
                    $carbon->setTime((int)$h, (int)$m, (int)$s);
                }
                return $carbon;
            } catch (\Throwable) {
                return null;
            }
        };

        // Filtros padrão
        $startCarbon = $parseDate($request->get('start_date'), '00:00:00') ?? Carbon::now()->startOfMonth();
        $endCarbon   = $parseDate($request->get('end_date'),   '23:59:59') ?? Carbon::now()->endOfDay();

        // Strings completas para uso nas queries (com hora)
        $startDateTime = $startCarbon->format('Y-m-d H:i:s');
        $endDateTime   = $endCarbon->format('Y-m-d H:i:s');

        // Para os inputs do formulário (datetime-local exige 'Y-m-d\TH:i')
        $startDate = $startCarbon->format('Y-m-d\TH:i');
        $endDate   = $endCarbon->format('Y-m-d\TH:i');

        $paymentStatus = $request->get('payment_status', 'all');
        $userStatus = $request->get('user_status', 'all');
        $busFilter = $request->get('bus', 'all');
        $canViewUsersTab = auth()->user()?->role === 'admin';
        
        // Estatísticas gerais
        $stats = $this->getGeneralStats($startDateTime, $endDateTime, $paymentStatus, $userStatus, $busFilter);
        
        // Dados dos pagamentos
        $payments = $this->getPaymentsData($startDateTime, $endDateTime, $paymentStatus, $busFilter);
        
        // Dados dos usuários
        $users = $canViewUsersTab ? $this->getUsersData($startDateTime, $endDateTime, $userStatus) : null;
        
        // Dados para gráficos
        $charts = $this->getChartsData($startDateTime, $endDateTime);

        // Receita por ônibus
        $revenueByBus = $this->getRevenueByBus($startDateTime, $endDateTime);

        // Lista de ônibus para o filtro
        $busList = \App\Models\Bus::orderBy('name')->get();
        
        return view('admin.reports', compact(
            'stats', 
            'payments', 
            'users', 
            'charts',
            'startDate',
            'endDate',
            'paymentStatus',
            'userStatus',
            'busFilter',
            'canViewUsersTab',
            'revenueByBus',
            'busList'
        ));
    }
    
    private function getGeneralStats($startDateTime, $endDateTime, $paymentStatus, $userStatus, $busFilter = 'all')
    {
        $dateRange = [$startDateTime, $endDateTime];

        // Helper: aplica filtro de ônibus a uma query de Payment
        $applyBus = function ($query) use ($busFilter) {
            if ($busFilter !== 'all') {
                $query->whereHas('user', fn($q) => $q->where('last_mikrotik_id', $busFilter));
            }
            return $query;
        };

        // Receita total (pagos)
        $totalRevenue = $applyBus(Payment::where('status', 'completed')->whereBetween('created_at', $dateRange))->sum('amount');

        // Total de pagamentos (respeitando filtro de status)
        $totalQuery = Payment::whereBetween('created_at', $dateRange);
        if ($paymentStatus !== 'all') $totalQuery->where('status', $paymentStatus);
        $totalPayments = $applyBus($totalQuery)->count();

        // Contagem por status
        $completedPayments = $applyBus(Payment::where('status', 'completed')->whereBetween('created_at', $dateRange))->count();
        $pendingPaymentsCount = $applyBus(Payment::where('status', 'pending')->whereBetween('created_at', $dateRange))->count();
        $failedPaymentsCount = $applyBus(Payment::where('status', 'failed')->whereBetween('created_at', $dateRange))->count();
        $pendingPayments = $applyBus(Payment::where('status', 'pending')->whereBetween('created_at', $dateRange))->sum('amount');

        // Usuários
        $userQuery = User::whereBetween('created_at', $dateRange);
        if ($busFilter !== 'all') $userQuery->where('last_mikrotik_id', $busFilter);
        $totalUsers = $userQuery->count();

        $connectedQuery = User::where('status', 'connected');
        if ($busFilter !== 'all') $connectedQuery->where('last_mikrotik_id', $busFilter);
        $connectedUsers = $connectedQuery->count();

        // Sessões ativas
        $sessionsQuery = DB::table('wifi_sessions')
            ->whereBetween('started_at', $dateRange)
            ->where('session_status', 'active');
        if ($busFilter !== 'all') {
            $sessionsQuery->whereIn('user_id', User::where('last_mikrotik_id', $busFilter)->pluck('id'));
        }
        $activeSessions = $sessionsQuery->count();

        return [
            'total_revenue' => $totalRevenue,
            'pending_payments' => $pendingPayments,
            'pending_payments_count' => $pendingPaymentsCount,
            'completed_payments_count' => $completedPayments,
            'failed_payments_count' => $failedPaymentsCount,
            'total_payments' => $totalPayments,
            'total_users' => $totalUsers,
            'connected_users' => $connectedUsers,
            'active_sessions' => $activeSessions,
            'avg_payment' => $completedPayments > 0 ? $totalRevenue / $completedPayments : 0,
        ];
    }
    
    private function getPaymentsData($startDateTime, $endDateTime, $paymentStatus, $busFilter = 'all')
    {
        $query = Payment::with(['user'])
            ->whereBetween('created_at', [$startDateTime, $endDateTime]);
        
        if ($paymentStatus !== 'all') {
            $query->where('status', $paymentStatus);
        }

        if ($busFilter !== 'all') {
            $query->whereHas('user', fn($q) => $q->where('last_mikrotik_id', $busFilter));
        }
        
        return $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
    }
    
    private function getUsersData($startDateTime, $endDateTime, $userStatus)
    {
        $query = User::whereBetween('created_at', [$startDateTime, $endDateTime]);
        
        if ($userStatus !== 'all') {
            $query->where('status', $userStatus);
        }
        
        return $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();
    }
    
    private function getChartsData($startDateTime, $endDateTime)
    {
        // Receita por dia
        $revenueByDay = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Pagamentos por status
        $paymentsByStatus = Payment::whereBetween('created_at', [$startDateTime, $endDateTime])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        
        // Usuários por dia
        $usersByDay = User::whereBetween('created_at', [$startDateTime, $endDateTime])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Conexões por hora (últimas 24h)
        $connectionsByHour = User::where('connected_at', '>=', Carbon::now()->subDay())
            ->select(
                DB::raw('HOUR(connected_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
        
        return [
            'revenue_by_day' => $revenueByDay,
            'payments_by_status' => $paymentsByStatus,
            'users_by_day' => $usersByDay,
            'connections_by_hour' => $connectionsByHour,
        ];
    }

    /**
     * Receita agrupada por ônibus (mikrotik_id)
     */
    private function getRevenueByBus($startDateTime, $endDateTime)
    {
        $dateRange = [$startDateTime, $endDateTime];
        $busNames = \App\Models\Bus::getSerialNameMap();

        $data = Payment::where('payments.status', 'completed')
            ->whereBetween('payments.created_at', $dateRange)
            ->join('users', 'payments.user_id', '=', 'users.id')
            ->select(
                DB::raw("COALESCE(users.last_mikrotik_id, 'desconhecido') as bus_id"),
                DB::raw('SUM(payments.amount) as total'),
                DB::raw('COUNT(payments.id) as count')
            )
            ->groupBy('bus_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($busNames) {
                $row->bus_name = $busNames[$row->bus_id] ?? $row->bus_id;
                return $row;
            });

        return $data;
    }
    
    public function export(Request $request)
    {
        // Mesma normalização do index
        $parseDate = function (?string $value, string $defaultTime = '00:00:00') {
            if (!$value) return null;
            try {
                $carbon = Carbon::parse($value);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    [$h, $m, $s] = explode(':', $defaultTime);
                    $carbon->setTime((int)$h, (int)$m, (int)$s);
                }
                return $carbon;
            } catch (\Throwable) {
                return null;
            }
        };

        $type = $request->get('type', 'payments');
        $format = $request->get('format', 'csv');
        $startCarbon = $parseDate($request->get('start_date'), '00:00:00') ?? Carbon::now()->startOfMonth();
        $endCarbon   = $parseDate($request->get('end_date'),   '23:59:59') ?? Carbon::now()->endOfDay();
        $startDateTime = $startCarbon->format('Y-m-d H:i:s');
        $endDateTime   = $endCarbon->format('Y-m-d H:i:s');

        if ($type === 'users' && auth()->user()?->role !== 'admin') {
            return back()->with('error', 'A aba e a exportação de usuários estão disponíveis apenas para administradores.');
        }
        
        if ($type === 'payments') {
            return $this->exportPayments($startDateTime, $endDateTime, $format, $startCarbon, $endCarbon);
        } elseif ($type === 'users') {
            return $this->exportUsers($startDateTime, $endDateTime, $format, $startCarbon, $endCarbon);
        }
        
        return back()->with('error', 'Tipo de exportação inválido');
    }

    public function destroyPaymentRecord(Payment $payment)
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return back()->with('error', 'Apenas administradores podem excluir registros de pagamento.');
        }

        try {
            DB::transaction(function () use ($payment) {
                $user = $payment->user;

                // Evita remoção acidental de contas administrativas.
                if ($user && in_array($user->role, ['admin', 'manager'])) {
                    throw new \RuntimeException('Não é permitido excluir pagamentos de usuários administrativos por esta tela.');
                }

                // Deletar avaliações vinculadas ao usuário deste pagamento
                if ($user) {
                    \App\Models\ServiceReview::where('user_id', $user->id)->delete();
                }

                // Deletar apenas o pagamento selecionado (NÃO o usuário inteiro).
                // Antes: deletava o usuário, o que por CASCADE removia TODOS os pagamentos
                // e sessões dele — inclusive de meses anteriores.
                $payment->delete();
            });

            return back()->with('success', 'Pagamento removido com sucesso.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Não foi possível excluir o registro neste momento.');
        }
    }

    public function destroyPaymentRecords(Request $request)
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return back()->with('error', 'Apenas administradores podem excluir registros de pagamento.');
        }

        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer', 'exists:payments,id'],
        ]);

        $paymentIds = collect($validated['payment_ids'])->unique()->values();
        $payments = Payment::with('user')->whereIn('id', $paymentIds)->get();

        if ($payments->isEmpty()) {
            return back()->with('error', 'Nenhum pagamento válido foi selecionado para exclusão.');
        }

        $deletedUsers = 0;
        $deletedPayments = 0;
        $deletedReviews = 0;
        $blockedRecords = 0;

        try {
            DB::transaction(function () use ($payments, &$deletedUsers, &$deletedPayments, &$deletedReviews, &$blockedRecords) {
                $deletedUserIds = [];

                foreach ($payments as $payment) {
                    $user = $payment->user;

                    if ($user && in_array($user->role, ['admin', 'manager'])) {
                        $blockedRecords++;
                        continue;
                    }

                    // Deletar avaliações vinculadas ao usuário (se ainda não deletou)
                    if ($user && !in_array($user->id, $deletedUserIds)) {
                        $deletedReviews += \App\Models\ServiceReview::where('user_id', $user->id)->delete();
                        $deletedUserIds[] = $user->id;
                    }

                    // Deletar apenas o pagamento (NÃO o usuário inteiro).
                    // Antes: deletava o usuário, o que por CASCADE removia TODOS os
                    // pagamentos e sessões dele — inclusive de meses anteriores.
                    $payment->delete();
                    $deletedPayments++;
                }
            });

            if ($deletedUsers === 0 && $deletedPayments === 0 && $blockedRecords > 0) {
                return back()->with('error', 'Nenhum registro foi removido. Existem itens vinculados a usuários administrativos.');
            }

            $message = "Exclusão concluída. Usuários removidos: {$deletedUsers}. Pagamentos removidos: {$deletedPayments}. Avaliações removidas: {$deletedReviews}.";
            if ($blockedRecords > 0) {
                $message .= " Itens bloqueados por segurança: {$blockedRecords}.";
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Não foi possível excluir os registros selecionados neste momento.');
        }
    }
    
    private function exportPayments($startDateTime, $endDateTime, $format, $startCarbon = null, $endCarbon = null)
    {
        $payments = Payment::with(['user'])
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'desc')
            ->get();

        $startSlug = ($startCarbon ?? Carbon::parse($startDateTime))->format('Y-m-d_Hi');
        $endSlug   = ($endCarbon   ?? Carbon::parse($endDateTime))->format('Y-m-d_Hi');
        $filename = 'pagamentos_' . $startSlug . '_a_' . $endSlug . '.' . $format;
        
        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            
            $callback = function() use ($payments) {
                $file = fopen('php://output', 'w');
                // BOM UTF-8 para Excel reconhecer acentos
                fwrite($file, "\xEF\xBB\xBF");
                // Excel-PT usa ; como separador. Forçamos isso e tambem dica sep=;
                fwrite($file, "sep=;\n");
                fputcsv($file, ['ID', 'Usuario', 'Email', 'Valor', 'Tipo', 'Status', 'Data Pagamento', 'Data Criacao'], ';');
                
                foreach ($payments as $payment) {
                    fputcsv($file, [
                        $payment->id,
                        $payment->user->name ?? 'N/A',
                        $payment->user->email ?? 'N/A',
                        'R$ ' . number_format($payment->amount, 2, ',', '.'),
                        ucfirst($payment->payment_type),
                        ucfirst($payment->status),
                        $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i:s') : 'N/A',
                        $payment->created_at->format('d/m/Y H:i:s'),
                    ], ';');
                }
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        }
        
        return back()->with('error', 'Formato de exportação não suportado');
    }
    
    private function exportUsers($startDateTime, $endDateTime, $format, $startCarbon = null, $endCarbon = null)
    {
        $users = User::whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'desc')
            ->get();

        $startSlug = ($startCarbon ?? Carbon::parse($startDateTime))->format('Y-m-d_Hi');
        $endSlug   = ($endCarbon   ?? Carbon::parse($endDateTime))->format('Y-m-d_Hi');
        $filename = 'usuarios_' . $startSlug . '_a_' . $endSlug . '.' . $format;
        
        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];
            
            $callback = function() use ($users) {
                $file = fopen('php://output', 'w');
                // BOM UTF-8 para Excel reconhecer acentos
                fwrite($file, "\xEF\xBB\xBF");
                // Excel-PT usa ; como separador
                fwrite($file, "sep=;\n");
                fputcsv($file, ['ID', 'Nome', 'Email', 'Telefone', 'MAC Address', 'IP Address', 'Status', 'Conectado em', 'Expira em', 'Data Cadastro'], ';');
                
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->name ?? 'N/A',
                        $user->email ?? 'N/A',
                        $user->phone ?? 'N/A',
                        $user->mac_address ?? 'N/A',
                        $user->ip_address ?? 'N/A',
                        ucfirst($user->status),
                        $user->connected_at ? $user->connected_at->format('d/m/Y H:i:s') : 'N/A',
                        $user->expires_at ? $user->expires_at->format('d/m/Y H:i:s') : 'N/A',
                        $user->created_at->format('d/m/Y H:i:s'),
                    ], ';');
                }
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        }
        
        return back()->with('error', 'Formato de exportação não suportado');
    }
}

