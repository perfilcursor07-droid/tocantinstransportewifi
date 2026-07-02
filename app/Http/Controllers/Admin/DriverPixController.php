<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverPixPayment;
use App\Models\DriverPixProfile;
use App\Models\DriverPixRegistrationLink;
use App\Services\PixQRCodeService;
use Illuminate\Http\Request;

class DriverPixController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'drivers');

        $links = DriverPixRegistrationLink::with('creator')
            ->withCount('profiles')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'links_page');

        $profilesQuery = $this->driverProfilesQuery();

        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'], true)) {
            $profilesQuery->where('status', $request->status);
        }

        if ($request->filled('bus')) {
            $profilesQuery->where('bus_number', 'like', '%' . strtoupper(trim($request->bus)) . '%');
        }

        $statusFilter = $request->get('status');
        $allProfiles = (clone $profilesQuery)
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderBy('bus_number')
            ->orderBy('full_name')
            ->get();

        $pendingProfiles = $allProfiles->where('status', 'pending')->values();
        $rejectedProfiles = $allProfiles->where('status', 'rejected')->values();
        $approvedByBus = $allProfiles->where('status', 'approved')
            ->sortBy('full_name')
            ->groupBy('bus_number')
            ->sortKeys();

        $profiles = $allProfiles; // compatibilidade

        $payments = DriverPixPayment::with(['profile', 'payer'])
            ->orderByRaw("FIELD(status, 'pending', 'paid', 'cancelled')")
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'payments_page');

        $stats = [
            'pending_profiles' => DriverPixProfile::where('status', 'pending')->count(),
            'approved_profiles' => DriverPixProfile::where('status', 'approved')->count(),
            'pending_payments' => DriverPixPayment::where('status', 'pending')->count(),
            'total_paid' => DriverPixPayment::where('status', 'paid')->sum('amount'),
        ];

        return view('admin.driver-pix.index', compact(
            'tab', 'links', 'profiles', 'pendingProfiles', 'rejectedProfiles', 'approvedByBus', 'payments', 'stats', 'statusFilter'
        ));
    }

    private function driverProfilesQuery()
    {
        return DriverPixProfile::with([
            'approver',
            'registrationLink',
            'latestPendingPayment',
            'latestPaidPayment',
        ])
            ->withSum(['payments as total_paid' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->withCount(['payments as pending_payments_count' => fn ($q) => $q->where('status', 'pending')]);
    }

    public function pixQr(Request $request, DriverPixProfile $profile, PixQRCodeService $pixQRCodeService)
    {
        if (! $profile->isApproved()) {
            return response()->json(['error' => 'Motorista não aprovado.'], 422);
        }

        $amount = $request->query('amount');
        $parsedAmount = is_numeric($amount) && (float) $amount > 0 ? (float) $amount : null;

        $emv = $pixQRCodeService->generateStaticPixEmv(
            $profile->pix_key,
            $profile->full_name,
            $parsedAmount,
            reference: 'BUS' . $profile->bus_number
        );

        return response()->json([
            'emv' => $emv,
            'qr_url' => $pixQRCodeService->generateQRCodeImageUrl($emv),
            'amount' => $parsedAmount,
        ]);
    }

    public function storeLink(Request $request)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:80',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $link = DriverPixRegistrationLink::create([
            'token' => DriverPixRegistrationLink::generateToken(),
            'label' => $validated['label'] ?? null,
            'is_active' => true,
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Link gerado com sucesso!')->with('new_link_url', $link->publicUrl());
    }

    public function toggleLink(DriverPixRegistrationLink $link)
    {
        $link->update(['is_active' => ! $link->is_active]);

        return back()->with('success', $link->is_active ? 'Link ativado.' : 'Link desativado.');
    }

    public function approve(DriverPixProfile $profile)
    {
        if ($profile->status !== 'pending') {
            return back()->with('error', 'Este cadastro já foi processado.');
        }

        $profile->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejected_reason' => null,
        ]);

        return back()->with('success', "Cadastro de {$profile->full_name} aprovado!");
    }

    public function reject(Request $request, DriverPixProfile $profile)
    {
        if ($profile->status !== 'pending') {
            return back()->with('error', 'Este cadastro já foi processado.');
        }

        $validated = $request->validate([
            'rejected_reason' => 'nullable|string|max:255',
        ]);

        $profile->update([
            'status' => 'rejected',
            'rejected_reason' => $validated['rejected_reason'] ?? 'Cadastro rejeitado pelo administrador.',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', "Cadastro de {$profile->full_name} rejeitado.");
    }

    public function storePayment(Request $request, DriverPixProfile $profile)
    {
        if (! $profile->isApproved()) {
            return back()->with('error', 'Só é possível pagar motoristas com cadastro aprovado.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999.99',
            'description' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);

        DriverPixPayment::create([
            'driver_pix_profile_id' => $profile->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? 'Pagamento motorista ônibus ' . $profile->bus_number,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('admin.driver-pix.index', ['tab' => 'drivers'])
            ->with('success', 'Pagamento registrado! Clique em "Pagar" para escanear o QR Code e marcar como pago.');
    }

    public function markPaymentPaid(DriverPixPayment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Este pagamento já foi processado.');
        }

        $payment->update([
            'status' => 'paid',
            'paid_by' => auth()->id(),
            'paid_at' => now(),
        ]);

        return redirect()
            ->route('admin.driver-pix.index', ['tab' => 'drivers'])
            ->with('success', 'Pagamento marcado como realizado.');
    }

    public function cancelPayment(DriverPixPayment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Só pagamentos pendentes podem ser cancelados.');
        }

        $payment->update(['status' => 'cancelled']);

        return back()->with('success', 'Pagamento cancelado.');
    }

    public function destroyProfile(DriverPixProfile $profile)
    {
        $name = $profile->full_name;
        $profile->delete();

        return redirect()
            ->route('admin.driver-pix.index', ['tab' => 'drivers'])
            ->with('success', "Cadastro de {$name} excluído permanentemente.");
    }

    public function destroyLink(DriverPixRegistrationLink $link)
    {
        $label = $link->label ?: 'Link sem nome';
        $link->delete();

        return redirect()
            ->route('admin.driver-pix.index', ['tab' => 'links'])
            ->with('success', "Link \"{$label}\" excluído.");
    }

    public function destroyPayment(DriverPixPayment $payment)
    {
        $amount = number_format($payment->amount, 2, ',', '.');
        $payment->delete();

        return redirect()
            ->route('admin.driver-pix.index', ['tab' => 'payments'])
            ->with('success', "Pagamento de R$ {$amount} excluído.");
    }
}
