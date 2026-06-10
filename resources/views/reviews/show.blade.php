<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisa de Opiniao - Tocantins Transporte</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/tailwind.play.js') }}"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(11,116,85,0.18),_transparent_35%),linear-gradient(180deg,#f6f8ef_0%,#fffdf8_55%,#ffffff_100%)] text-slate-800">
    <div class="max-w-md mx-auto px-4 py-4 sm:py-6">
        <div class="mb-5 flex items-center justify-between">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/90 backdrop-blur-md px-3.5 py-1.5 shadow-[0_2px_10px_rgba(0,0,0,0.03)] border border-white text-[10px] font-bold uppercase tracking-wider text-emerald-900">
                <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Avaliação de Serviço</span>
            </div>
            <div class="rounded-full bg-amber-50 text-amber-700 px-3 py-1 text-[11px] font-bold border border-amber-100/50">
                ⏱️ 15 seg
            </div>
        </div>

        <div class="bg-white/92 backdrop-blur rounded-[28px] shadow-[0_18px_50px_rgba(15,23,42,0.08)] border border-white overflow-hidden">
            <div class="px-5 py-5 bg-[linear-gradient(135deg,#064e3b_0%,#065f46_100%)] text-white relative overflow-hidden">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/5 rounded-full blur-2xl"></div>
                <div class="flex items-center justify-between gap-4 relative z-10">
                    <div class="min-w-0">
                        <p class="text-[9px] uppercase tracking-[0.2em] font-bold text-white/50 mb-1 flex items-center gap-1.5">
                            <span class="w-1 h-3 rounded-full bg-emerald-400/60"></span>
                            Feedback Rápido
                        </p>
                        <h1 class="text-base sm:text-lg font-bold tracking-tight leading-snug">
                            Descreva sua experiência durante a viagem e, se necessário, explique o motivo da sua nota.
                        </h1>
                        <p class="mt-1.5 text-[11px] text-white/70 font-medium">Atendimento, internet e suporte geral.</p>
                    </div>
                    <div class="w-11 h-11 shrink-0 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 flex items-center justify-center text-2xl shadow-inner shadow-white/5">
                        🚌
                    </div>
                </div>
            </div>

            <div class="px-5 py-4">
                <div class="mb-5 rounded-[20px] bg-slate-50/50 border border-slate-100 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold">
                                {{ substr($review->user?->name ?: 'P', 0, 1) }}
                            </div>
                            <div>
                                <p class="text-[9px] uppercase tracking-widest text-slate-400 font-bold">Passageiro</p>
                                <p class="text-sm font-bold text-slate-800 tracking-tight">{{ $review->user?->name ?: 'Visitante' }}</p>
                            </div>
                        </div>
                        <div class="text-right border-l border-slate-200 pl-4">
                            <p class="text-[9px] uppercase tracking-widest text-slate-400 font-bold">Horário</p>
                            <p class="text-sm font-semibold text-slate-600">{{ $review->registration_at?->format('H:i') ?: '--:--' }}</p>
                        </div>
                    </div>
                </div>

                @if(session('success'))
                <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
                @endif

                @if(session('info'))
                <div class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    {{ session('info') }}
                </div>
                @endif

                @if($review->submitted_at)
                <div class="text-center py-4">
                    <div class="text-4xl leading-none text-amber-400">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300">{{ str_repeat('☆', 5 - $review->rating) }}</span></div>
                    <p class="mt-3 text-lg font-extrabold text-slate-800">Resposta recebida</p>
                    <p class="mt-1 text-sm text-slate-600">Sua nota foi {{ $review->rating }}/5 em {{ $review->submitted_at->format('d/m/Y H:i') }}.</p>
                    @if($review->reason)
                    <div class="mt-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 text-left">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">O que foi informado</p>
                        <p class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{{ $review->reason }}</p>
                    </div>
                    @endif
                </div>
                @else
                <form method="POST" action="{{ route('reviews.store', $review->token) }}" class="space-y-4" id="review-form">
                    @csrf

                    <div>
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <label class="block text-sm font-semibold text-slate-700">Sua nota geral</label>
                            <p id="rating-hint" class="text-xs font-semibold text-slate-500">Toque nas estrelas</p>
                        </div>

                        <div class="grid grid-cols-5 gap-2">
                            @for($rating = 1; $rating <= 5; $rating++)
                            <div class="star-option">
                                <input
                                    type="radio"
                                    name="rating"
                                    id="rating-{{ $rating }}"
                                    value="{{ $rating }}"
                                    class="sr-only"
                                    {{ (int) old('rating') === $rating ? 'checked' : '' }}
                                >
                                <label for="rating-{{ $rating }}" data-rating="{{ $rating }}" class="rating-card flex flex-col items-center justify-center rounded-[20px] border border-slate-100 bg-white px-1 py-4 text-center transition-all duration-200 cursor-pointer active:scale-[0.95] shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
                                    <span class="rating-star text-2xl text-slate-200 transition-colors">★</span>
                                    <span class="mt-1 text-[13px] font-bold text-slate-500">{{ $rating }}</span>
                                </label>
                            </div>
                            @endfor
                        </div>

                        <div class="mt-3 grid grid-cols-5 gap-2 text-[10px] text-center font-medium text-slate-500">
                            <span>Muito ruim</span>
                            <span>Ruim</span>
                            <span>Regular</span>
                            <span>Bom</span>
                            <span>Excelente</span>
                        </div>

                        @error('rating')
                            <p class="mt-3 text-sm text-red-600 text-center">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="reason-box" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <label for="reason" class="block text-sm font-semibold text-slate-700 mb-1">O que podemos melhorar?</label>
                        <p class="text-xs text-slate-600 mb-3">Para notas de 1 a 3 estrelas, esse campo e obrigatorio.</p>
                        <textarea id="reason" name="reason" rows="4" class="w-full px-4 py-3 border border-amber-200 rounded-2xl focus:ring-2 focus:ring-amber-400 focus:border-transparent text-sm resize-none" placeholder="Ex.: atendimento demorado, organizacao, conforto, informacoes da viagem...">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 text-center">
                        <p class="text-[11px] text-slate-500 font-medium">
                            <span class="inline-block w-1 h-1 rounded-full bg-emerald-400 mr-1.5 mb-0.5"></span>
                            Sua opinião é anônima e ajuda a melhorar nossos serviços.
                        </p>
                    </div>

                    <button type="submit" class="w-full py-4 rounded-[20px] bg-[linear-gradient(135deg,#059669_0%,#047857_100%)] text-white font-bold text-base shadow-[0_10px_25px_-5px_rgba(5,150,105,0.4)] hover:shadow-[0_15px_30px_-5px_rgba(5,150,105,0.5)] active:scale-[0.98] transition-all duration-200">
                        Enviar Avaliação
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <script>
        const ratingInputs = document.querySelectorAll('input[name="rating"]');
        const reasonBox = document.getElementById('reason-box');
        const reasonInput = document.getElementById('reason');
        const ratingCards = document.querySelectorAll('.rating-card');
        const ratingHint = document.getElementById('rating-hint');
        const ratingMap = {
            1: '1 estrela • Muito ruim',
            2: '2 estrelas • Ruim',
            3: '3 estrelas • Regular',
            4: '4 estrelas • Bom',
            5: '5 estrelas • Excelente',
        };

        function updateReasonVisibility() {
            const selected = document.querySelector('input[name="rating"]:checked');
            const value = selected ? Number(selected.value) : null;

            ratingCards.forEach((label) => {
                const labelValue = Number(label.dataset.rating);
                const active = value !== null && labelValue <= value;
                const star = label.querySelector('.rating-star');

                label.classList.toggle('bg-amber-50/50', active);
                label.classList.toggle('border-amber-200', active);
                label.classList.toggle('shadow-md', active);
                label.classList.toggle('shadow-amber-200/20', active);
                label.classList.toggle('bg-white', !active);
                label.classList.toggle('border-slate-100', !active);

                if (star) {
                    star.classList.toggle('text-amber-400', active);
                    star.classList.toggle('text-slate-200', !active);
                }
            });

            if (ratingHint) {
                ratingHint.textContent = value !== null ? ratingMap[value] : 'Toque nas estrelas';
                ratingHint.classList.toggle('text-amber-600', value !== null);
                ratingHint.classList.toggle('text-slate-500', value === null);
            }

            if (value !== null && value <= 3) {
                reasonBox?.classList.remove('hidden');
                reasonInput?.setAttribute('required', 'required');
                return;
            }

            reasonBox?.classList.add('hidden');
            reasonInput?.removeAttribute('required');
        }

        ratingInputs.forEach((input) => {
            input.addEventListener('change', updateReasonVisibility);
        });

        updateReasonVisibility();
    </script>
</body>
</html>