<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Mostrar página de login
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Processar login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => 'E-mail é obrigatório',
            'email.email' => 'E-mail deve ter formato válido',
            'password.required' => 'Senha é obrigatória',
            'password.min' => 'Senha deve ter pelo menos 6 caracteres',
        ]);

        // Verificar se é um administrador/gestor
        $user = User::where('email', $request->email)
                    ->whereIn('role', ['admin', 'manager'])
                    ->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Usuário não encontrado ou não tem permissão de acesso.'
            ])->withInput();
        }

        // Verificar senha
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => 'Senha incorreta.'
            ])->withInput();
        }

        // Fazer login
        Auth::login($user, $request->filled('remember'));

        // Registrar último login (admins/gestores)
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        // Regenerar sessão por segurança
        $request->session()->regenerate();

        // Redirecionar para o primeiro modulo disponivel
        return redirect()->intended($user->getHomeRoute())
                        ->with('success', 'Login realizado com sucesso!');
    }

    /**
     * Fazer logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login')
                        ->with('success', 'Logout realizado com sucesso!');
    }

    /**
     * Criar usuário administrador (apenas para desenvolvimento)
     */
    public function createAdmin(Request $request)
    {
        // Verificar se já existe um admin
        $adminExists = User::where('role', 'admin')->exists();
        
        if ($adminExists) {
            return response()->json([
                'message' => 'Administrador já existe no sistema.'
            ], 400);
        }

        // Criar usuário admin padrão
        $admin = User::create([
            'name' => 'Administrador WiFi Tocantins',
            'email' => 'admin@wifitocantins.com.br',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'registered_at' => now(),
            'status' => 'active'
        ]);

        // Criar gestor padrão também
        $manager = User::create([
            'name' => 'Gestor WiFi Tocantins',
            'email' => 'gestor@wifitocantins.com.br',
            'password' => Hash::make('gestor123'),
            'role' => 'manager',
            'registered_at' => now(),
            'status' => 'active'
        ]);

        return response()->json([
            'message' => 'Usuários administrativos criados com sucesso!',
            'admin' => [
                'email' => 'admin@wifitocantins.com.br',
                'password' => 'admin123'
            ],
            'manager' => [
                'email' => 'gestor@wifitocantins.com.br',
                'password' => 'gestor123'
            ]
        ]);
    }
}
