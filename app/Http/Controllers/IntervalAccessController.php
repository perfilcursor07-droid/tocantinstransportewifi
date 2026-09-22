<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\MikrotikMacReport;
use App\Models\User;
use App\Services\IntervalPlanService;
use Illuminate\Http\Request;

class IntervalAccessController extends Controller
{
    public function connect(Request $request, IntervalPlanService $plans)
    {
        $data = $request->validate([
            'mac_address' => ['required', 'regex:/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i'],
            'ip_address' => 'nullable|ip',
        ]);
        $mac = strtoupper($data['mac_address']);
        $user = User::where('mac_address', $mac)->first();
        if (! $user) {
            return response()->json(['state' => 'none']);
        }

        // The foreground request must come through a recently synced bus.
        $serials = Bus::where('last_public_ip', $request->ip())
            ->where('last_sync_at', '>=', now()->subMinutes(3))->pluck('mikrotik_serial');
        $report = $serials->isNotEmpty() ? MikrotikMacReport::where('mac_address', $mac)
            ->whereIn('mikrotik_id', $serials)->where('last_seen', '>=', now()->subMinutes(3))
            ->when(! empty($data['ip_address']), fn ($q) => $q->where('ip_address', $data['ip_address']))
            ->latest('last_seen')->first() : null;
        // Some routers sync paid users without reporting every DHCP lease. In that
        // case use the MAC/IP already registered on this same bus, never the public
        // IP alone. A fresh conflicting report takes precedence over saved identity.
        $knownDeviceOnBus = $serials->contains($user->last_mikrotik_id)
            && ! empty($data['ip_address'])
            && $user->ip_address === $data['ip_address']
            && ! MikrotikMacReport::where('mac_address', $mac)
                ->where('last_seen', '>=', now()->subMinutes(3))->exists();
        $onBus = $report !== null || $knownDeviceOnBus;
        if ($report && ($user->ip_address !== $report->ip_address || $user->last_mikrotik_id !== $report->mikrotik_id)) {
            $user->update(['ip_address' => $report->ip_address, 'last_mikrotik_id' => $report->mikrotik_id]);
        }
        $result = $plans->access($user, $onBus);
        if ($result['state'] === 'ready' && ! $onBus) {
            $result['message'] = 'Conecte ao Wi-Fi do ônibus e aguarde a identificação do aparelho para iniciar sua diária.';
        }

        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
