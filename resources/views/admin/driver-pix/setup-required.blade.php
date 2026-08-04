@extends('layouts.admin')

@section('title', 'Pagamentos Motoristas')

@section('breadcrumb')
    <span class="mx-2">/</span>
    <span class="text-green font-medium">Pagamentos Motoristas</span>
@endsection

@section('page-title', 'Pagamentos Motoristas')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl border border-amber-200 shadow-card p-6 sm:p-8">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink">Atualização do banco pendente</h2>
                <p class="text-sm text-muted mt-2 leading-relaxed">
                    O módulo de pagamentos dos motoristas precisa da tabela de competências
                    (<code class="text-xs bg-surface px-1.5 py-0.5 rounded border border-border">driver_pix_profile_months</code>
                    e das colunas novas. Rode as migrations no servidor e limpe o cache.
                </p>
                <pre class="mt-4 p-4 rounded-xl bg-slate-900 text-slate-100 text-xs overflow-x-auto leading-relaxed">cd /home/tocantinstransportewifi/htdocs/tocantinstransportewifi.com.br
git pull origin main --no-rebase
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache</pre>
            </div>
        </div>
    </div>
</div>
@endsection
