<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverRequest;
use App\Models\Voucher;
use Illuminate\Http\Request;

class DriverRequestController extends Controller
{
    public function index()
    {
        $requests = DriverRequest::with(['voucher', 'approver'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.driver-requests.index', compact('requests'));
    }

    public function approve(Request $request, DriverRequest $driverRequest)
    {
        if ($driverRequest->status !== 'pending') {
            return back()->with('error', 'Este pedido ja foi processado.');
        }

        $validated = $request->validate([
            'voucher_type' => 'required|in:limited,unlimited',
            'daily_hours' => 'nullable|numeric|min:0.01|max:24',
            'activation_interval_hours' => 'required|numeric|min:0.01|max:168',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validated['voucher_type'] === 'limited' && empty($validated['daily_hours'])) {
            return back()->withErrors(['daily_hours' => 'Tempo diario obrigatorio para voucher limitado.'])->withInput();
        }

        if ($validated['voucher_type'] === 'unlimited') {
            $validated['daily_hours'] = 24;
        }

        // Gera codigo unico
        do {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (Voucher::where('code', $code)->exists());

        $voucher = Voucher::create([
            'code' => $code,
            'driver_name' => $driverRequest->name,
            'driver_document' => $driverRequest->document,
            'driver_phone' => $driverRequest->phone,
            'daily_hours' => $validated['daily_hours'],
            'activation_interval_hours' => $validated['activation_interval_hours'],
            'expires_at' => $validated['expires_at'] ?? null,
            'voucher_type' => $validated['voucher_type'],
            'description' => $driverRequest->observation,
            'is_active' => true,
            'daily_hours_used' => 0,
        ]);

        $driverRequest->update([
            'status' => 'approved',
            'voucher_id' => $voucher->id,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "Voucher {$voucher->code} criado para {$driverRequest->name}!");
    }

    public function reject(DriverRequest $driverRequest)
    {
        if ($driverRequest->status !== 'pending') {
            return back()->with('error', 'Este pedido ja foi processado.');
        }

        $driverRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "Pedido de {$driverRequest->name} rejeitado.");
    }

    public function destroy(DriverRequest $driverRequest)
    {
        $name = $driverRequest->name;
        $driverRequest->delete();

        return back()->with('success', "Pedido de {$name} excluido com sucesso.");
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer', 'exists:driver_requests,id'],
        ]);

        $count = DriverRequest::whereIn('id', collect($validated['request_ids'])->unique())->delete();

        return back()->with('success', "{$count} pedido(s) excluido(s) com sucesso.");
    }
}
