<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    /**
     * Lista todos os vouchers
     */
    public function index(Request $request)
    {
        $query = Voucher::driverVouchers();

        // Filtro de busca (nome, código, telefone)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('driver_name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('driver_phone', 'like', "%{$search}%");
            });
        }

        // Filtro de status
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true)
                      ->where(function ($q) {
                          $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                      });
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<=', now());
            }
        }

        // Filtro de tipo
        if ($type = $request->input('type')) {
            $query->where('voucher_type', $type);
        }

        $vouchers = $query->orderBy('created_at', 'desc')->paginate(20)->appends($request->query());

        // Estatísticas gerais (sem filtros)
        $allVouchers = Voucher::driverVouchers();
        $stats = [
            'total' => $allVouchers->count(),
            'active' => Voucher::driverVouchers()->where('is_active', true)
                ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                ->count(),
            'inactive' => Voucher::driverVouchers()->where('is_active', false)->count(),
            'unlimited' => Voucher::driverVouchers()->where('voucher_type', 'unlimited')->count(),
            'expired' => Voucher::driverVouchers()->whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
        ];

        return view('admin.vouchers.index', compact('vouchers', 'stats'));
    }

    /**
     * Formulário de criação
     */
    public function create()
    {
        return view('admin.vouchers.create');
    }

    /**
     * Armazena novo voucher
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:191',
            'driver_document' => 'nullable|string|max:191',
            'driver_phone' => 'required|string|max:20',
            'daily_hours' => 'nullable|numeric|min:0.01|max:24',
            'activation_interval_hours' => 'required|numeric|min:0.01|max:168',
            'expires_at' => 'nullable|date|after:today',
            'voucher_type' => 'required|in:limited,unlimited',
            'description' => 'nullable|string|max:191',
        ]);

        // Para vouchers ilimitados, define um valor padrão para daily_hours
        if ($validated['voucher_type'] === 'unlimited') {
            $validated['daily_hours'] = 24; // Valor simbólico para ilimitado
        } elseif (empty($validated['daily_hours'])) {
            return back()->withErrors(['daily_hours' => 'O tempo diário é obrigatório para vouchers limitados.'])->withInput();
        }

        // Limpar telefone (apenas números)
        $driverPhone = preg_replace('/\D/', '', $validated['driver_phone']);

        // Gera código único
        do {
            $code = $this->generateVoucherCode();
        } while (Voucher::where('code', $code)->exists());

        $voucher = Voucher::create([
            'code' => $code,
            'driver_name' => $validated['driver_name'],
            'driver_document' => $validated['driver_document'] ?? null,
            'driver_phone' => $driverPhone,
            'daily_hours' => $validated['daily_hours'],
            'activation_interval_hours' => $validated['activation_interval_hours'],
            'expires_at' => $validated['expires_at'] ?? null,
            'voucher_type' => $validated['voucher_type'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'daily_hours_used' => 0,
        ]);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Voucher criado com sucesso! Código: {$voucher->code} | Telefone: {$driverPhone}");
    }

    /**
     * Formulário de edição
     */
    public function edit(Voucher $voucher)
    {
        return view('admin.vouchers.edit', compact('voucher'));
    }

    /**
     * Atualiza voucher
     */
    public function update(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:191',
            'driver_document' => 'nullable|string|max:191',
            'driver_phone' => 'required|string|max:20',
            'daily_hours' => 'nullable|numeric|min:0.01|max:24',
            'activation_interval_hours' => 'required|numeric|min:0.01|max:168',
            'expires_at' => 'nullable|date',
            'voucher_type' => 'required|in:limited,unlimited',
            'description' => 'nullable|string|max:191',
            'is_active' => 'boolean',
        ]);

        // Para vouchers ilimitados, define um valor padrão para daily_hours
        if ($validated['voucher_type'] === 'unlimited') {
            $validated['daily_hours'] = 24; // Valor simbólico para ilimitado
        } elseif (empty($validated['daily_hours'])) {
            return back()->withErrors(['daily_hours' => 'O tempo diário é obrigatório para vouchers limitados.'])->withInput();
        }

        // Limpar telefone (apenas números)
        if (isset($validated['driver_phone'])) {
            $validated['driver_phone'] = preg_replace('/\D/', '', $validated['driver_phone']);
        }

        $voucher->update($validated);

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', 'Voucher atualizado com sucesso!');
    }

    /**
     * Ativa/Desativa voucher
     */
    public function toggleStatus(Voucher $voucher)
    {
        $voucher->update(['is_active' => !$voucher->is_active]);

        $status = $voucher->is_active ? 'ativado' : 'desativado';
        
        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "Voucher {$status} com sucesso!");
    }

    /**
     * Reseta uso diário
     */
    public function resetDaily(Voucher $voucher)
    {
        $voucher->resetDailyUsage();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', 'Uso diário resetado com sucesso!');
    }

    /**
     * Deleta voucher
     */
    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', 'Voucher excluído com sucesso!');
    }

    /**
     * Deleta vouchers selecionados em lote
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'voucher_ids' => ['required', 'array', 'min:1'],
            'voucher_ids.*' => ['integer', 'exists:vouchers,id'],
        ]);

        $count = Voucher::whereIn('id', collect($validated['voucher_ids'])->unique())->delete();

        return redirect()
            ->route('admin.vouchers.index')
            ->with('success', "{$count} voucher(s) excluído(s) com sucesso!");
    }

    /**
     * Gera código único de voucher
     */
    private function generateVoucherCode(): string
    {
        // Formato: WIFI-XXXX-XXXX (ex: WIFI-A3B7-K9M2)
        $part1 = strtoupper(Str::random(4));
        $part2 = strtoupper(Str::random(4));
        
        return "WIFI-{$part1}-{$part2}";
    }
}
