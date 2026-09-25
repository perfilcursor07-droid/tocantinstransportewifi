@if($interval_plan['enabled'] ?? false)
    <div class="relative pt-2.5">
        <div class="plan-tag left-3 bg-amber-100 text-amber-800 ring-1 ring-amber-200">
            24 horas ou vários dias
        </div>
        <button type="button" id="interval-plan-option" data-plan-option data-plan-type="interval"
            data-plan-price="{{ $interval_plan['price_24h'] }}" data-plan-duration="24"
            data-plan-name="Plano por intervalo" data-plan-suffix="/ 1 dia"
            aria-controls="interval-plan-fields" aria-expanded="false"
            class="wifi-plan-card flex w-full items-center gap-3 border-2 border-gray-200 bg-white px-3.5 pt-4 pb-2.5 text-left hover:border-green/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-green/30">
            <span data-plan-radio class="h-5 w-5 rounded-full border-2 border-gray-300 bg-white flex-shrink-0 transition-all duration-200"></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-extrabold text-ink leading-tight">Plano por intervalo</span>
                <span class="block text-[11px] text-muted leading-tight mt-0.5">A partir de 1 dia · 24 horas corridas por diária</span>
            </span>
            <span data-plan-price-display class="text-lg font-black text-ink tracking-tight whitespace-nowrap">R${{ number_format($interval_plan['price_24h'], 2, ',', '.') }}</span>
        </button>
    </div>
    <div id="interval-plan-fields" class="hidden rounded-2xl bg-gray-50 ring-1 ring-gray-100 p-2.5 space-y-2" data-max-days="{{ $interval_plan['max_days'] }}"
        data-daily-cents="{{ (int) round($interval_plan['price_24h'] * 100) }}" data-today="{{ $interval_plan['today'] }}"
        data-timezone="{{ config('app.timezone') }}">
        <div class="grid grid-cols-2 gap-2">
            <label class="block min-w-0 text-[10px] font-semibold leading-tight text-ink" for="interval-start">Início
                <input id="interval-start" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-0.5 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs focus:outline-none focus:border-green focus:ring-2 focus:ring-green/20">
            </label>
            <label class="block min-w-0 text-[10px] font-semibold leading-tight text-ink" for="interval-end">Final
                <input id="interval-end" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-0.5 rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs focus:outline-none focus:border-green focus:ring-2 focus:ring-green/20">
            </label>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-green/20 bg-green-pale px-2.5 py-1.5">
            <span class="text-[10px] font-semibold text-green-dark">Duração de cada diária</span>
            <span class="text-xs font-extrabold text-green-dark">24 horas corridas</span>
        </div>
        <p id="interval-summary" class="text-xs font-bold leading-tight text-green-dark" aria-live="polite"></p>
        <div id="interval-payment-window" class="rounded-lg border border-green/20 bg-white px-2.5 py-2 text-[10px] leading-tight text-gray-600" aria-live="polite">
            <span data-payment-window-title class="block font-semibold text-green-dark">Pagando agora, sua primeira diária:</span>
            <strong data-payment-window class="mt-0.5 block text-xs text-ink"></strong>
            <span data-payment-window-note class="mt-0.5 block">Começa na confirmação do PIX e dura 24 horas corridas.</span>
        </div>
        <p id="interval-error" class="text-xs text-red-700 hidden" role="alert"></p>
    </div>
@endif
