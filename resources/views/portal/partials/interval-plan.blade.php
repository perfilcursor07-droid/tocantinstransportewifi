@if($interval_plan['enabled'] ?? false)
    <div>
        <div class="inline-block rounded-t-lg border-x-2 border-t-2 border-gray-200 bg-amber-50 px-3 py-1 text-[10px] font-extrabold leading-tight text-amber-800">
            Turismo e vários dias
        </div>
        <button type="button" id="interval-plan-option" data-plan-option data-plan-type="interval"
            data-plan-price="{{ $interval_plan['price_12h'] * 2 }}" data-plan-duration="12"
            data-plan-name="Plano por intervalo" data-plan-suffix="/ 2 dias"
            aria-controls="interval-plan-fields" aria-expanded="false"
            class="wifi-plan-card -mt-px flex w-full items-center gap-2.5 rounded-lg rounded-tl-none border-2 border-gray-200 bg-white px-3 py-3 text-left">
            <span data-plan-radio class="h-4 w-4 rounded-full border-2 border-gray-300 bg-white flex-shrink-0"></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-ink">Plano por intervalo</span>
                <span class="block text-xs text-muted">A partir de 2 dias · 12h ou 24h por dia</span>
            </span>
            <span data-plan-price-display class="text-base font-extrabold text-ink whitespace-nowrap">R${{ number_format($interval_plan['price_12h'] * 2, 2, ',', '.') }}</span>
        </button>
    </div>
    <div id="interval-plan-fields" class="hidden py-1.5 space-y-1.5" data-max-days="{{ $interval_plan['max_days'] }}"
        data-base-cents="{{ (int) round($interval_plan['price_12h'] * 100) }}" data-today="{{ $interval_plan['today'] }}">
        <div class="grid grid-cols-2 gap-1.5">
            <label class="block min-w-0 text-[10px] font-semibold leading-tight text-ink" for="interval-start">Início
                <input id="interval-start" type="date" required min="{{ $interval_plan['today'] }}" value="{{ $interval_plan['today'] }}"
                    class="block w-full min-w-0 mt-0.5 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs">
            </label>
            <label class="block min-w-0 text-[10px] font-semibold leading-tight text-ink" for="interval-end">Final
                <input id="interval-end" type="date" required min="{{ $interval_plan['tomorrow'] }}" value="{{ $interval_plan['tomorrow'] }}"
                    class="block w-full min-w-0 mt-0.5 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs">
            </label>
        </div>
        <fieldset>
            <legend class="text-[10px] font-semibold leading-tight text-ink mb-0.5">Por dia</legend>
            <div class="grid grid-cols-2 gap-1.5">
                <label class="flex items-center justify-center gap-1.5 border border-gray-300 rounded-md px-2 py-1 text-xs">
                    <input type="radio" name="interval-hours" value="12" checked class="accent-green-600"> 12h
                </label>
                <label class="flex items-center justify-center gap-1.5 border border-gray-300 rounded-md px-2 py-1 text-xs">
                    <input type="radio" name="interval-hours" value="24" class="accent-green-600"> 24h
                </label>
            </div>
        </fieldset>
        <p id="interval-summary" class="text-xs font-bold leading-tight text-green-dark" aria-live="polite"></p>
        <p id="interval-error" class="text-xs text-red-700 hidden" role="alert"></p>
    </div>
@endif
