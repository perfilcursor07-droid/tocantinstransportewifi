<?php

namespace App\Http\Controllers;

use App\Models\DriverPixProfile;
use App\Models\DriverPixRegistrationLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DriverPixRegistrationController extends Controller
{
    public function show(string $token)
    {
        if (! Schema::hasTable('driver_pix_registration_links')) {
            return view('driver-pix.invalid', [
                'reason' => 'Cadastro temporariamente indisponível. O administrador precisa atualizar o sistema. Tente novamente em alguns minutos.',
            ]);
        }

        $link = DriverPixRegistrationLink::where('token', $token)->firstOrFail();

        if (! $link->isUsable()) {
            return view('driver-pix.invalid', ['reason' => 'Este link de cadastro expirou ou foi desativado. Peça um novo link ao administrador.']);
        }

        return view('driver-pix.register', compact('link'));
    }

    public function store(Request $request, string $token)
    {
        if (! Schema::hasTable('driver_pix_registration_links') || ! Schema::hasTable('driver_pix_profiles')) {
            return back()->withErrors(['link' => 'Cadastro temporariamente indisponível. Tente novamente em alguns minutos.']);
        }

        if (! Schema::hasColumn('driver_pix_profiles', 'phone')) {
            return back()->withErrors(['link' => 'Sistema desatualizado. Avise o administrador para rodar as migrations no servidor.']);
        }

        $link = DriverPixRegistrationLink::where('token', $token)->firstOrFail();

        if (! $link->isUsable()) {
            return back()->withErrors(['link' => 'Este link de cadastro não está mais disponível.']);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|min:3|max:120',
            'phone' => 'required|string|min:10|max:20',
            'pix_key' => 'required|string|min:5|max:120',
            'pix_key_confirmation' => 'required|same:pix_key',
            'bus_number' => 'required|string|max:20',
        ], [
            'pix_key_confirmation.same' => 'A confirmação da chave PIX deve ser igual à chave informada.',
            'phone.required' => 'Informe seu telefone com DDD.',
        ]);

        $busNumber = strtoupper(trim($validated['bus_number']));
        $phone = preg_replace('/\D/', '', $validated['phone']);
        $pixKeyType = DriverPixProfile::detectPixKeyType($validated['pix_key'], $phone);
        $normalizedPixKey = DriverPixProfile::normalizePixKey($validated['pix_key'], $pixKeyType);

        // Evita cadastro duplicado pendente/aprovado com mesma chave PIX
        $duplicate = DriverPixProfile::where('pix_key', $normalizedPixKey)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['pix_key' => 'Esta chave PIX já está cadastrada. Aguarde aprovação ou fale com o administrador.']);
        }

        DriverPixProfile::create([
            'registration_link_id' => $link->id,
            'full_name' => mb_strtoupper(trim($validated['full_name']), 'UTF-8'),
            'phone' => $phone,
            'pix_key' => $normalizedPixKey,
            'pix_key_type' => $pixKeyType,
            'bus_number' => $busNumber,
            'status' => 'pending',
        ]);

        $link->increment('uses_count');

        return redirect()->route('driver-pix.success', $token);
    }

    public function success(string $token)
    {
        DriverPixRegistrationLink::where('token', $token)->firstOrFail();

        return view('driver-pix.success');
    }
}
