<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceReview;
use App\Models\User;
use App\Models\WhatsappOptOut;
use App\Models\WhatsappSetting;
use App\Services\ServiceReviewWhatsappService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceReviewController extends Controller
{
    public function __construct(
        protected ServiceReviewWhatsappService $reviewWhatsappService
    ) {
    }

    public function index(Request $request)
    {
        // Função base que aplica todos os filtros — reutilizada para listagem e estatísticas
        $applyFilters = function ($query) use ($request) {
            if ($request->filled('status')) {
                match ($request->status) {
                    'answered' => $query->whereNotNull('submitted_at'),
                    'pending' => $query->whereNull('submitted_at')->where('whatsapp_status', 'sent'),
                    'failed' => $query->where('whatsapp_status', 'failed'),
                    'not_sent' => $query->whereIn('whatsapp_status', ['pending', 'skipped']),
                    default => null,
                };
            }

            if ($request->filled('rating')) {
                $query->where('rating', (int) $request->rating);
            }

            if ($request->filled('phone')) {
                $query->where('phone', 'like', '%' . $request->phone . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('batch_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('batch_date', '<=', $request->date_to);
            }

            if ($request->filled('answered_from')) {
                $answeredFrom = Carbon::parse($request->answered_from);
                $query->where('submitted_at', '>=', $answeredFrom);
            }

            if ($request->filled('answered_to')) {
                $answeredToInput = (string) $request->answered_to;
                $answeredTo = Carbon::parse($answeredToInput);

                if (! str_contains($answeredToInput, 'T') && ! str_contains($answeredToInput, ':')) {
                    $answeredTo->endOfDay();
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}$/', $answeredToInput)) {
                    $answeredTo->endOfMinute();
                }

                $query->where('submitted_at', '<=', $answeredTo);
            }

            return $query;
        };

        $query = ServiceReview::with(['user', 'whatsappMessage']);
        $applyFilters($query);

        $reviews = $query
            ->orderByDesc('batch_date')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // Estatísticas RESPEITAM os filtros aplicados
        $stats = [
            'total_invites' => $applyFilters(ServiceReview::query())->count(),
            'answered' => $applyFilters(ServiceReview::query())->whereNotNull('submitted_at')->count(),
            'pending_answers' => $applyFilters(ServiceReview::query())->whereNull('submitted_at')->where('whatsapp_status', 'sent')->count(),
            'low_ratings' => $applyFilters(ServiceReview::query())->whereNotNull('submitted_at')->where('rating', '<=', 3)->count(),
            'sent' => $applyFilters(ServiceReview::query())->where('whatsapp_status', 'sent')->count(),
            'failed' => $applyFilters(ServiceReview::query())->where('whatsapp_status', 'failed')->count(),
            'average_rating' => round((float) $applyFilters(ServiceReview::query())->whereNotNull('rating')->avg('rating'), 1),
        ];

        // Distribuição também respeita filtros
        $distribution = collect(range(1, 5))
            ->mapWithKeys(fn (int $rating) => [
                $rating => $applyFilters(ServiceReview::query())->where('rating', $rating)->count(),
            ]);

        // Dados para grafico de avaliacoes dos ultimos 14 dias
        $dailyStats = ServiceReview::selectRaw("DATE(batch_date) as date, COUNT(*) as total, SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as answered, ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) as avg_rating")
            ->where('batch_date', '>=', now()->subDays(13)->startOfDay())
            ->groupByRaw('DATE(batch_date)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartLabels = [];
        $chartInvites = [];
        $chartAnswered = [];
        $chartAvgRating = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $label = now()->subDays($i)->format('d/m');
            $chartLabels[] = $label;
            $chartInvites[] = (int) ($dailyStats[$date]->total ?? 0);
            $chartAnswered[] = (int) ($dailyStats[$date]->answered ?? 0);
            $chartAvgRating[] = (float) ($dailyStats[$date]->avg_rating ?? 0);
        }

        // Taxa de resposta (também respeita filtros)
        $responseRate = $stats['total_invites'] > 0
            ? round(($stats['answered'] / $stats['total_invites']) * 100, 1)
            : 0;

        return view('admin.reviews.index', compact(
            'reviews',
            'stats',
            'distribution',
            'chartLabels',
            'chartInvites',
            'chartAnswered',
            'chartAvgRating',
            'responseRate'
        ));
    }

    public function settings()
    {
        $settings = [
            'review_auto_send_enabled' => WhatsappSetting::isReviewAutoSendEnabled(),
            'review_email_enabled' => WhatsappSetting::get('review_email_enabled', 'true') === 'true',
            'review_message_template' => WhatsappSetting::getReviewMessageTemplate(),
            'is_connected' => WhatsappSetting::isConnected(),
            'connected_phone' => WhatsappSetting::getConnectedPhone(),
        ];

        $currentWindow = ServiceReview::resolveBatchWindow(today());

        return view('admin.reviews.settings', compact('settings', 'currentWindow'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'review_message_template' => 'required|string|max:1500',
            'review_auto_send_enabled' => 'nullable|boolean',
            'review_email_enabled' => 'nullable|boolean',
        ]);

        WhatsappSetting::set(
            'review_auto_send_enabled',
            $request->has('review_auto_send_enabled') ? 'true' : 'false'
        );
        WhatsappSetting::set(
            'review_email_enabled',
            $request->has('review_email_enabled') ? 'true' : 'false'
        );
        WhatsappSetting::set('review_message_template', $request->review_message_template);

        return redirect()
            ->route('admin.reviews.settings')
            ->with('success', 'Configuracoes atualizadas com sucesso!');
    }

    /**
     * Gera avaliações de teste para passageiros reais de uma data de viagem.
     * Esta acao nao envia mensagens pelo WhatsApp.
     */
    public function generateInvitations(Request $request)
    {
        $validated = $request->validate([
            'travel_date' => ['required', 'date', 'before_or_equal:today'],
            'answered_from' => ['required', 'date'],
            'answered_to' => ['required', 'date', 'after_or_equal:answered_from'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'travel_date.before_or_equal' => 'Escolha uma data de viagem de hoje ou anterior.',
            'answered_from.required' => 'Informe o início do intervalo de Respondido em.',
            'answered_to.required' => 'Informe o fim do intervalo de Respondido em.',
            'answered_to.after_or_equal' => 'O fim do intervalo precisa ser igual ou posterior ao início.',
            'quantity.max' => 'Para conferência, gere no máximo 100 convites por vez.',
        ]);

        $travelDate = Carbon::parse($validated['travel_date'])->startOfDay();
        $answeredFrom = Carbon::parse($validated['answered_from']);
        $answeredTo = Carbon::parse($validated['answered_to'])->endOfMinute();

        $passengerQuery = User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereNotIn('role', ['admin', 'manager']);
            });

        $passengersOnTravelDate = (clone $passengerQuery)
            ->whereDate('registered_at', $travelDate)
            ->get();

        $eligiblePassengers = $passengersOnTravelDate
            ->reject(fn (User $user) => WhatsappOptOut::isOptedOut($user->phone))
            ->unique(fn (User $user) => WhatsappOptOut::last8($user->phone))
            ->shuffle();

        $selectedPassengers = $eligiblePassengers->take((int) $validated['quantity']);

        foreach ($selectedPassengers as $passenger) {
            $review = $this->reviewWhatsappService->prepareReviewForUser($passenger, $travelDate);
            $submittedAt = $answeredFrom->copy()->addSeconds(random_int(
                0,
                (int) max(0, $answeredFrom->diffInSeconds($answeredTo))
            ));

            $review->update([
                'rating' => (int) $validated['rating'],
                'reason' => null,
                'submitted_at' => $submittedAt,
                'whatsapp_status' => 'sent',
            ]);
        }

        $selectedCount = $selectedPassengers->count();
        $message = $selectedCount === 0
            ? 'Nenhum passageiro elegível foi encontrado nessa data de viagem.'
            : "$selectedCount avaliação(ões) de teste gerada(s) com nota {$validated['rating']} e Respondido em dentro do intervalo informado. Nenhuma mensagem foi enviada.";

        return redirect()
            ->route('admin.reviews.index', [
                'date_from' => $travelDate->toDateString(),
                'date_to' => $travelDate->toDateString(),
                'status' => 'answered',
                'rating' => $validated['rating'],
                'answered_from' => $answeredFrom->format('Y-m-d\TH:i'),
                'answered_to' => $answeredTo->format('Y-m-d\TH:i'),
            ])
            ->with('success', $message);
    }

    public function update(Request $request, ServiceReview $review)
    {
        $validated = $request->validate([
            'submitted_at' => 'nullable|date',
            'rating' => 'nullable|integer|min:1|max:5',
            'reason' => 'nullable|string|max:1000',
        ]);

        $review->update([
            'submitted_at' => $validated['submitted_at'] ?: null,
            'rating' => $validated['rating'] ?: null,
            'reason' => $validated['reason'] ?: null,
        ]);

        return redirect()
            ->route('admin.reviews.index', $request->query())
            ->with('success', 'Avaliacao atualizada com sucesso!');
    }

    public function destroy(ServiceReview $review)
    {
        $review->delete();

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', 'Avaliacao excluida com sucesso!');
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:service_reviews,id',
            'submitted_at' => 'nullable|date',
            'rating' => 'nullable|integer|min:1|max:5',
            'reason' => 'nullable|string|max:1000',
        ]);

        $data = [];
        if ($request->filled('submitted_at')) {
            $data['submitted_at'] = $validated['submitted_at'];
        }
        if ($request->filled('rating')) {
            $data['rating'] = $validated['rating'];
        }
        if ($request->has('reason') && $validated['reason'] !== null) {
            $data['reason'] = $validated['reason'];
        }

        if (!empty($data)) {
            ServiceReview::whereIn('id', $validated['ids'])->update($data);
        }

        $count = count($validated['ids']);

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', "$count avaliacao(oes) atualizada(s) com sucesso!");
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:service_reviews,id',
        ]);

        $count = ServiceReview::whereIn('id', $validated['ids'])->delete();

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', "$count avaliacao(oes) excluida(s) com sucesso!");
    }

    public function sendTest(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'batch_date' => 'nullable|date',
        ], [
            'phone.required' => 'Informe o numero que vai receber o teste.',
            'phone.min' => 'O numero precisa ter pelo menos 10 digitos.',
            'email.email' => 'E-mail invalido.',
            'batch_date.date' => 'A data do lote precisa ser valida.',
        ]);

        $result = $this->reviewWhatsappService->sendManualTest(
            $validated['phone'],
            $validated['name'] ?? null,
            $validated['batch_date'] ?? today()->toDateString(),
            $validated['email'] ?? null
        );

        $messages = [];

        if ($result['success']) {
            $messages[] = 'WhatsApp enviado para ' . ($result['review']->phone ?: $validated['phone']);
        } else {
            $messages[] = 'WhatsApp falhou: ' . ($result['error'] ?? 'erro desconhecido');
        }

        if (!empty($result['email_sent'])) {
            $messages[] = 'Email enviado para ' . $validated['email'];
        } elseif (!empty($validated['email']) && empty($result['email_sent'])) {
            $messages[] = 'Email falhou: ' . ($result['email_error'] ?? 'erro desconhecido');
        }

        $linkedUserMessage = ($result['matched_user'] ?? null)
            ? ' Usuario vinculado ao cadastro existente.'
            : '';

        $hasSuccess = ($result['success'] || !empty($result['email_sent']));

        return redirect()
            ->route('admin.reviews.settings')
            ->withInput()
            ->with($hasSuccess ? 'success' : 'error', implode(' | ', $messages) . $linkedUserMessage)
            ->with('manual_review_link', $result['link'] ?? null);
    }

    /**
     * Exportar relatório de avaliações em PDF
     */
    public function exportPdf(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $reviews = ServiceReview::with('user')
            ->whereBetween('batch_date', [$startDate, $endDate])
            ->orderByDesc('batch_date')
            ->get();

        $answered = $reviews->whereNotNull('submitted_at');
        $totalInvites = $reviews->count();
        $totalAnswered = $answered->count();
        $avgRating = $answered->avg('rating') ?: 0;
        $responseRate = $totalInvites > 0 ? round(($totalAnswered / $totalInvites) * 100, 1) : 0;

        $distribution = collect(range(1, 5))->mapWithKeys(fn($r) => [
            $r => $answered->where('rating', $r)->count()
        ]);

        $lowRatings = $answered->where('rating', '<=', 3);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reviews.pdf', [
            'reviews' => $reviews,
            'answered' => $answered,
            'totalInvites' => $totalInvites,
            'totalAnswered' => $totalAnswered,
            'avgRating' => $avgRating,
            'responseRate' => $responseRate,
            'distribution' => $distribution,
            'lowRatings' => $lowRatings,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("avaliacoes_{$startDate}_{$endDate}.pdf");
    }
}
