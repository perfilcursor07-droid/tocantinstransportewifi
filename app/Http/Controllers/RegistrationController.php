<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\HotspotIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    /**
     * Register a new user for WiFi access
     */
    public function register(Request $request)
    {
        try {
            // Validar os dados
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'required|string|max:20',
            ], [
                'name.required' => 'Nome completo é obrigatório',
                'email.required' => 'E-mail é obrigatório',
                'email.email' => 'E-mail deve ter um formato válido',
                'email.unique' => 'Este e-mail já está cadastrado',
                'phone.required' => 'Telefone é obrigatório',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Criar o usuário
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make('default_password_'.time()), // Password temporário
                'registered_at' => now(),
                'status' => 'pending', // Status inicial
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuário cadastrado com sucesso!',
                'user_id' => $user->id,
                'redirect_to_payment' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if email already exists
     */
    public function checkEmail(Request $request)
    {
        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    /**
     * Check if user exists by email or phone and return user data
     */
    public function checkUser(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        $user = null;

        // Buscar por email ou telefone
        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->phone) {
            // Limpar formatação do telefone para busca
            $cleanPhone = preg_replace('/[^\d]/', '', $request->phone);
            $user = User::where('phone', 'LIKE', '%'.$cleanPhone.'%')->first();
        }

        if ($user) {
            return response()->json([
                'exists' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'mac_address' => $user->mac_address,  // ✅ Adicionar MAC
                    'ip_address' => $user->ip_address,    // ✅ Adicionar IP
                ],
            ]);
        }

        return response()->json([
            'exists' => false,
        ]);
    }

    private function resolveClientIp(Request $request)
    {
        $candidates = [
            $request->input('ip_address'),
            $request->input('client_ip'),
            $request->header('X-Client-IP'),
            $request->header('X-Forwarded-For'),
            $request->header('X-Real-IP'),
        ];

        foreach ($candidates as $value) {
            if (! $value) {
                continue;
            }

            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $request->ip();
    }

    private function shouldReplaceMac(?string $currentMac, string $newMac): bool
    {
        if (! $currentMac) {
            return true;
        }

        $isCurrentMock = stripos($currentMac, '02:') === 0;
        $isNewMock = stripos($newMac, '02:') === 0;

        if ($isCurrentMock && ! $isNewMock) {
            return true;
        }

        if (! $isCurrentMock && ! $isNewMock) {
            return $currentMac !== $newMac;
        }

        return false;
    }

    /**
     * Register or update existing user for payment (SIMPLIFICADO - apenas telefone)
     */
    public function registerForPayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|exists:users,id',
                'phone' => 'required|string|min:10|max:20',
                'email' => 'nullable|email|max:255',
                'mac_address' => 'nullable|string|max:17',
                'ip_address' => 'nullable|ip',
            ], [
                'phone.required' => 'Telefone é obrigatório',
                'phone.min' => 'Telefone deve ter pelo menos 10 dígitos',
                'email.email' => 'E-mail inválido',
                'mac_address.string' => 'MAC address deve ser uma string válida',
                'ip_address.ip' => 'IP inválido',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 🎯 PROCESSAR MAC E IP ADDRESS
            // PRIORIZAR IP/MAC do request body (enviado pelo JavaScript) em vez do IP público
            $ipAddress = $request->input('ip_address');
            $macAddress = HotspotIdentity::normalizeMac($request->input('mac_address'));

            // Rejeitar placeholders inválidos (00:00:..., FF:FF:...) — MACs randomizados são aceitos
            if ($macAddress && HotspotIdentity::isMockMac($macAddress)) {
                \Log::warning('⚠️ MAC mock/inválido enviado pelo frontend — tentando fallback', ['mac_recebido' => $macAddress]);
                $macAddress = null;
            }

            \Log::info('📋 DADOS RECEBIDOS DO FRONTEND', [
                'ip_enviado' => $ipAddress,
                'mac_enviado' => $macAddress,
                'ip_http_header' => $request->header('X-Real-IP'),
            ]);
            
            // Se não veio do frontend, tentar detectar
            if (!$ipAddress) {
            $ipAddress = HotspotIdentity::resolveClientIp($request);
                \Log::warning('⚠️ IP não enviado pelo frontend, usando fallback', ['ip_fallback' => $ipAddress]);
            }
            
            if (!$macAddress) {
                $macAddress = HotspotIdentity::resolveRealMac(null, $ipAddress);
                \Log::warning('⚠️ MAC não enviado pelo frontend, usando fallback', ['mac_fallback' => $macAddress]);
            }

            if (! $macAddress) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível identificar o dispositivo. Reconecte ao Wi-Fi e tente novamente.',
                ], 422);
            }

            // Limpar telefone (apenas números)
            $cleanPhone = preg_replace('/[^\d]/', '', $request->phone);
            $email = $request->input('email');
            
            // 🔧 FIX: Primeiro verificar se já existe usuário com este MAC (prioridade máxima)
            $existingUserByMac = $macAddress ? User::where('mac_address', $macAddress)->first() : null;
            
            if ($existingUserByMac) {
                $updateData = [
                    'phone' => $cleanPhone,
                    'ip_address' => $ipAddress,
                    'registered_at' => now(),
                ];
                if ($email) $updateData['email'] = $email;

                // 🔧 FIX: NÃO resetar status para 'pending' se o usuário já tem acesso ativo
                // Isso evita que um usuário que já pagou perca o acesso ao reabrir o portal
                $hasActiveAccess = in_array($existingUserByMac->status, ['connected', 'active'])
                    && $existingUserByMac->expires_at
                    && $existingUserByMac->expires_at > now();

                if (!$hasActiveAccess) {
                    $updateData['status'] = 'pending';
                }

                $existingUserByMac->update($updateData);

                \Log::info('🔄 Reutilizando usuário existente pelo MAC', [
                    'user_id' => $existingUserByMac->id,
                    'mac_address' => $macAddress,
                    'phone' => $cleanPhone,
                ]);

                if ($hasActiveAccess) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Você já tem acesso ativo! Reconectando...',
                        'user_id' => $existingUserByMac->id,
                        'existing_user' => true,
                        'already_active' => true,
                        'expires_at' => $existingUserByMac->expires_at,
                        'redirect_to_payment' => false,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Dispositivo reconhecido!',
                    'user_id' => $existingUserByMac->id,
                    'existing_user' => true,
                    'redirect_to_payment' => true,
                ]);
            }
            
            // Se tem user_id e MAC não existe em outro usuário, usar usuário existente
            if ($request->user_id) {
                $user = User::find($request->user_id);

                if (! $user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuário não encontrado',
                    ], 404);
                }

                // Atualizar dados (seguro pois já verificamos que MAC não existe em outro usuário)
                $updateData = [
                    'phone' => $cleanPhone,
                    'registered_at' => now(),
                ];

                if (HotspotIdentity::shouldReplaceMac($user->mac_address, $macAddress)) {
                    // Limpar MAC de outros registros que possam ter este mesmo MAC (constraint unique)
                    User::where('mac_address', $macAddress)
                        ->where('id', '!=', $user->id)
                        ->update(['mac_address' => null]);
                    
                    // 🗑️ Marcar MAC antigo para remoção no Mikrotik
                    HotspotIdentity::markOrphanedMac($user->mac_address);
                    
                    $updateData['mac_address'] = $macAddress;
                    
                    \Log::info('🔄 MAC ATUALIZADO para usuário existente (user_id)', [
                        'user_id' => $user->id,
                        'old_mac' => $user->mac_address,
                        'new_mac' => $macAddress,
                    ]);
                }
                if ($ipAddress && $user->ip_address !== $ipAddress) {
                    $updateData['ip_address'] = $ipAddress;
                }

                $user->update($updateData);

                // 🔧 FIX: Verificar se o usuário já tem sessão ativa
                $hasActiveAccess = in_array($user->status, ['connected', 'active'])
                    && $user->expires_at
                    && $user->expires_at > now();

                if ($hasActiveAccess) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Você já tem acesso ativo! Reconectando...',
                        'user_id' => $user->id,
                        'existing_user' => true,
                        'already_active' => true,
                        'expires_at' => $user->expires_at,
                        'redirect_to_payment' => false,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Dados atualizados com sucesso!',
                    'user_id' => $user->id,
                    'existing_user' => true,
                    'redirect_to_payment' => true,
                ]);
            }

            // Verificar se já existe usuário com este telefone
            $existingUserByPhone = User::where('phone', $cleanPhone)->first();

            if ($existingUserByPhone) {
                // Usuário já existe com este telefone - atualizar MAC/IP
                $updateData = ['phone' => $cleanPhone];
                $updateData['registered_at'] = now();
                
                // 🔧 FIX: Sempre atualizar o MAC para o MAC atual do dispositivo
                // Usuários que voltam podem ter MAC diferente (MAC aleatório ou outro dispositivo)
                // O MAC antigo no banco não serve mais - precisa ser o MAC atual para liberar no Mikrotik
                if ($macAddress && HotspotIdentity::shouldReplaceMac($existingUserByPhone->mac_address, $macAddress)) {
                    // Limpar MAC de outros registros que possam ter este mesmo MAC (constraint unique)
                    User::where('mac_address', $macAddress)
                        ->where('id', '!=', $existingUserByPhone->id)
                        ->update(['mac_address' => null]);
                    
                    // 🗑️ Marcar MAC antigo para remoção no Mikrotik
                    HotspotIdentity::markOrphanedMac($existingUserByPhone->mac_address);
                    
                    $updateData['mac_address'] = $macAddress;
                    
                    \Log::info('🔄 MAC ATUALIZADO para usuário que voltou (encontrado por telefone)', [
                        'user_id' => $existingUserByPhone->id,
                        'old_mac' => $existingUserByPhone->mac_address,
                        'new_mac' => $macAddress,
                        'phone' => $cleanPhone,
                    ]);
                }
                if ($ipAddress) {
                    $updateData['ip_address'] = $ipAddress;
                }
                
                // 🔧 FIX: Verificar se o usuário já tem sessão ativa (pagou e ainda tem tempo)
                // Se sim, não precisa pagar de novo - apenas atualizar MAC e liberar
                $hasActiveAccess = in_array($existingUserByPhone->status, ['connected', 'active'])
                    && $existingUserByPhone->expires_at
                    && $existingUserByPhone->expires_at > now();

                $existingUserByPhone->update($updateData);
                
                if ($hasActiveAccess) {
                    \Log::info('✅ Usuário com sessão ativa reconectou com MAC diferente', [
                        'user_id' => $existingUserByPhone->id,
                        'new_mac' => $macAddress,
                        'expires_at' => $existingUserByPhone->expires_at,
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Você já tem acesso ativo! Reconectando...',
                        'user_id' => $existingUserByPhone->id,
                        'existing_user' => true,
                        'already_active' => true,
                        'expires_at' => $existingUserByPhone->expires_at,
                        'redirect_to_payment' => false,
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Bem-vindo de volta!',
                    'user_id' => $existingUserByPhone->id,
                    'existing_user' => true,
                    'redirect_to_payment' => true,
                ]);
            }

            // Criar novo usuário (apenas com telefone, email, MAC e IP)
            $userData = [
                'phone' => $cleanPhone,
                'registered_at' => now(),
                'status' => 'pending',
            ];

            if ($email) $userData['email'] = $email;
            if ($macAddress) $userData['mac_address'] = $macAddress;
            if ($ipAddress) $userData['ip_address'] = $ipAddress;

            $user = User::create($userData);

            \Log::info('📱 NOVO USUÁRIO SIMPLIFICADO', [
                'user_id' => $user->id,
                'phone' => $cleanPhone,
                'mac' => $macAddress,
                'ip' => $ipAddress,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cadastro realizado!',
                'user_id' => $user->id,
                'existing_user' => false,
                'redirect_to_payment' => true,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar se usuário existe pelo MAC address
     */
    public function checkMacAddress(string $mac)
    {
        try {
            // Normalizar MAC (uppercase e remover espaços)
            $mac = strtoupper(trim($mac));
            
            // Buscar usuário pelo MAC
            $user = User::where('mac_address', $mac)->first();
            
            if ($user) {
                // 🔧 FIX: Informar se o usuário já tem sessão ativa
                $hasActiveAccess = in_array($user->status, ['connected', 'active'])
                    && $user->expires_at
                    && $user->expires_at > now();

                return response()->json([
                    'exists' => true,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'already_active' => $hasActiveAccess,
                    'expires_at' => $hasActiveAccess ? $user->expires_at : null,
                ]);
            }
            
            return response()->json([
                'exists' => false,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Erro ao verificar MAC:', ['error' => $e->getMessage()]);
            return response()->json([
                'exists' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
