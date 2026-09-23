@if($interval_plan['enabled'] ?? false)
    <div>
        <div class="inline-block rounded-t-lg border-x-2 border-t-2 border-gray-200 bg-amber-50 px-3 py-1 text-[10px] font-extrabold leading-tight text-amber-800">
            24 horas ou vários dias
        </div>
        <button type="button" id="interval-plan-option" data-plan-option data-plan-type="interval"
            data-plan-price="{{ $interval_plan['price_24h'] }}" data-plan-duration="24"
            data-plan-name="Plano por intervalo" data-plan-suffix="/ 1 dia"
            aria-controls="interval-plan-fields" aria-expanded="false"
            class="wifi-plan-card -mt-px flex w-full items-center gap-2.5 rounded-lg rounded-tl-none border-2 border-gray-200 bg-white px-3 py-3 text-left">
            <span data-plan-radio class="h-4 w-4 rounded-full border-2 border-gray-300 bg-white flex-shrink-0"></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-ink">Plano por intervalo</span>
                <span class="block text-xs text-muted">A partir de 1 dia · 24 horas corridas por diária</span>
            </span>
            <span data-plan-price-display class="text-base font-extrabold text-ink whitespace-nowrap">R${{ number_format($interval_plan['price_24h'], 2, ',', '.') }}</span>
        </button>
    </div>
    <div id="interval-plan-fields" class="hidden py-1.5 space-y-1.5" data-max-days="{{ $interval_plan['max_days'] }}"
        data-daily-cents="{{ (int) round($interval_plan['price_24h'] * 100) }}" data-today="{{ $interval_plan['today'] }}"
        data-timezone="{{ config('app.timezone') }}">
        <div class="grid grid-cols-2 gap-1.5">
            <label class="block min-w-0 text-[10px] font-semibold leading-tight text-ink" for="interval-start">Início
                <input id="interval-start" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-0.5 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs">
            </label>
            <label class="block min-w-0 text-[10px] font-semibold leading-tight text-ink" for="interval-end">Final
                <input id="interval-end" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-0.5 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs">
            </label>
        </div>
        <div class="flex items-center justify-between rounded-md border border-green/20 bg-green-pale px-2.5 py-1.5">
            <span class="text-[10px] font-semibold text-green-dark">Duração de cada diária</span>
            <span class="text-xs font-extrabold text-green-dark">24 horas corridas</span>
        </div>
        <p id="interval-summary" class="text-xs font-bold leading-tight text-green-dark" aria-live="polite"></p>
        <div id="interval-payment-window" class="rounded-md border border-green/20 bg-white px-2.5 py-2 text-[10px] leading-tight text-gray-600" aria-live="polite">
            <span data-payment-window-title class="block font-semibold text-green-dark">Pagando agora, sua primeira diária:</span>
            <strong data-payment-window class="mt-0.5 block text-xs text-ink"></strong>
            <span data-payment-window-note class="mt-0.5 block">Começa na confirmação do PIX e dura 24 horas corridas.</span>
        </div>
        <p id="interval-error" class="text-xs text-red-700 hidden" role="alert"></p>
    </div>
@endif
