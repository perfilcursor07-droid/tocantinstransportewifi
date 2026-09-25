@if($interval_plan['enabled'] ?? false)
    <div>
        <button type="button" id="interval-plan-option" data-plan-option data-plan-type="interval"
            data-plan-price="{{ $interval_plan['price_24h'] }}" data-plan-duration="24"
            data-plan-name="Plano por intervalo" data-plan-suffix="/ 1 dia"
            aria-controls="interval-plan-fields" aria-expanded="false"
            class="wifi-plan-card flex w-full items-center gap-3 border-2 border-gray-200 bg-white px-4 py-3.5 text-left hover:border-green/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-green/30">
            <span data-plan-radio class="h-5 w-5 rounded-full border-2 border-gray-300 bg-white flex-shrink-0 transition-all duration-200"></span>
            <span class="min-w-0 flex-1">
                <span class="block text-base font-extrabold text-ink leading-tight">Vários dias</span>
                <span class="block text-[13px] text-gray-600 leading-tight mt-1">24 horas por dia · escolha as datas</span>
            </span>
            <span data-plan-price-display class="text-xl font-black text-ink tracking-tight whitespace-nowrap">R${{ number_format($interval_plan['price_24h'], 2, ',', '.') }}</span>
        </button>
    </div>
    <div id="interval-plan-fields" class="hidden rounded-2xl bg-gray-50 ring-1 ring-gray-100 p-3 space-y-2.5" data-max-days="{{ $interval_plan['max_days'] }}"
        data-daily-cents="{{ (int) round($interval_plan['price_24h'] * 100) }}" data-today="{{ $interval_plan['today'] }}"
        data-timezone="{{ config('app.timezone') }}">
        <div class="grid grid-cols-2 gap-2">
            <label class="block min-w-0 text-[13px] font-bold leading-tight text-ink" for="interval-start">Primeiro dia
                <input id="interval-start" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-1 rounded-lg border border-gray-300 bg-white px-2 py-2.5 text-[15px] focus:outline-none focus:border-green focus:ring-2 focus:ring-green/20">
            </label>
            <label class="block min-w-0 text-[13px] font-bold leading-tight text-ink" for="interval-end">Último dia
                <input id="interval-end" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-1 rounded-lg border border-gray-300 bg-white px-2 py-2.5 text-[15px] focus:outline-none focus:border-green focus:ring-2 focus:ring-green/20">
            </label>
        </div>
        <p id="interval-summary" class="text-[15px] font-extrabold leading-tight text-green-dark" aria-live="polite"></p>
        <div id="interval-payment-window" class="rounded-lg border border-green/20 bg-white px-3 py-2.5 text-[12px] leading-snug text-gray-600" aria-live="polite">
            <span data-payment-window-title class="block font-semibold text-green-dark">Pagando agora, sua primeira diária:</span>
            <strong data-payment-window class="mt-0.5 block text-[14px] text-ink"></strong>
            <span data-payment-window-note class="mt-0.5 block">Começa na confirmação do PIX e dura 24 horas corridas.</span>
        </div>
        <p id="interval-error" class="text-[13px] font-semibold text-red-700 hidden" role="alert"></p>
    </div>
@endif
