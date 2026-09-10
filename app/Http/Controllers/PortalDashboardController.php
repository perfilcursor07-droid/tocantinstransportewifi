<?php

namespace App\Http\Controllers;

use App\Services\PixPaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalDashboardController extends Controller
{
    public function __construct(private readonly PixPaymentManager $pixPaymentManager)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $payments = $user->payments()
            ->latest()
            ->take(10)
            ->get();

        $latestPayment = $payments->firstWhere(fn ($payment) => $payment->status === 'pending')
            ?? $payments->first();

        return view('portal.dashboard', [
            'user' => $user,
            'payments' => $payments,
            'latestPayment' => $latestPayment,
            'defaultPrice' => \App\Helpers\SettingsHelper::getWifiPrice(),
        ]);
    }

    public function regeneratePix(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_id' => 'nullable|exists:payments,id',
        ]);

        $user = $request->user();

        $amount = \App\Helpers\SettingsHelper::getWifiPrice();
        if ($request->filled('payment_id')) {
            $payment = $user->payments()
                ->where('id', $request->payment_id)
                ->firstOrFail();
            if (\App\Services\IntervalPlanService::isInterval($payment)) {
                return redirect('/?'.http_build_query([
                    'choose_plan' => 1, 'mac' => $user->mac_address, 'ip' => $user->ip_address,
                ]))->with('info', 'Selecione as datas do intervalo para gerar um novo PIX.');
            }
            $amount = $payment->amount;
        }

        $result = $this->pixPaymentManager->createPixPayment(
            $user,
            $amount,
            [
                'mac_address' => $user->mac_address,
                'ip_address' => $user->ip_address,
                'cancel_previous' => true,
                'source' => 'dashboard-regenerate',
            ]
        );

        return redirect()->route('portal.dashboard')
            ->with('success', 'Novo QR Code gerado com sucesso!')
            ->with('qr_code', $result['qr_code'])
            ->with('gateway', $result['gateway']);
    }
}
