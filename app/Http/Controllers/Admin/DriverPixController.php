<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverPixPayment;
use App\Models\DriverPixProfile;
use App\Models\DriverPixRegistrationLink;
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

        $profilesQuery = DriverPixProfile::with(['approver', 'registrationLink'])
            ->withSum(['payments as total_paid' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->withCount(['payments as pending_payments_count' => fn ($q) => $q->where('status', 'pending')]);

        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'], true)) {
            $profilesQuery->where('status', $request->status);
        }

        if ($request->filled('bus')) {
            $profilesQuery->where('bus_number', 'like', '%' . strtoupper(trim($request->bus)) . '%');
        }

        $profiles = $profilesQuery
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'profiles_page');

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
            'tab', 'links', 'profiles', 'payments', 'stats'
        ));
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

        return back()->with('success', 'Pagamento registrado como pendente. Marque como pago após enviar o PIX.');
    }

    public function markPaymentPaid(Request $request, DriverPixPayment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Este pagamento já foi processado.');
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:120',
        ]);

        $payment->update([
            'status' => 'paid',
            'payment_reference' => $validated['payment_reference'] ?? null,
            'paid_by' => auth()->id(),
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Pagamento marcado como realizado.');
    }

    public function cancelPayment(DriverPixPayment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Só pagamentos pendentes podem ser cancelados.');
        }

        $payment->update(['status' => 'cancelled']);

        return back()->with('success', 'Pagamento cancelado.');
    }
}
