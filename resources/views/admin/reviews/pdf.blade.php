<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Avaliações</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; line-height: 1.4; }

        .header { background: #00A335; color: #fff; padding: 20px 30px; }
        .header h1 { font-size: 18px; margin-bottom: 2px; }
        .header p { font-size: 10px; opacity: 0.8; }

        .content { padding: 20px 30px; }

        .period { background: #F8F9FA; border: 1px solid #E5E5E5; border-radius: 6px; padding: 10px 15px; margin-bottom: 20px; font-size: 10px; color: #333; }

        .stats-grid { display: table; width: 100%; margin-bottom: 20px; }
        .stat-row { display: table-row; }
        .stat-box { display: table-cell; width: 25%; padding: 4px; }
        .stat-inner { background: #F8F9FA; border: 1px solid #E5E5E5; border-radius: 6px; padding: 12px; text-align: center; }
        .stat-value { font-size: 22px; font-weight: bold; color: #00A335; }
        .stat-value.gold { color: #E6A817; }
        .stat-value.blue { color: #1565C0; }
        .stat-value.red { color: #D32F2F; }
        .stat-label { font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        .section-title { font-size: 13px; font-weight: bold; color: #111; margin: 20px 0 10px; padding-bottom: 5px; border-bottom: 2px solid #00A335; }

        .dist-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .dist-table td { padding: 6px 10px; font-size: 11px; }
        .dist-table .stars { color: #E6A817; font-size: 13px; }
        .dist-bar { background: #E5E5E5; height: 12px; border-radius: 6px; overflow: hidden; width: 200px; display: inline-block; vertical-align: middle; }
        .dist-fill { background: #00A335; height: 100%; border-radius: 6px; }
        .dist-fill.low { background: #D32F2F; }

        table.reviews { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9px; }
        table.reviews th { background: #00A335; color: #fff; padding: 6px 8px; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.reviews td { padding: 5px 8px; border-bottom: 1px solid #E5E5E5; vertical-align: top; }
        table.reviews tr:nth-child(even) { background: #F8F9FA; }

        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-green { background: #E8F5E9; color: #00A335; }
        .badge-gold { background: #FFF8E1; color: #E6A817; }
        .badge-red { background: #FFEBEE; color: #D32F2F; }
        .badge-gray { background: #F8F9FA; color: #888; }

        .footer { text-align: center; font-size: 8px; color: #888; margin-top: 20px; padding-top: 10px; border-top: 1px solid #E5E5E5; }

        .low-section { margin-top: 15px; }
        .low-item { background: #FFEBEE; border: 1px solid #D32F2F20; border-radius: 6px; padding: 10px; margin-bottom: 8px; }
        .low-item .phone { font-weight: bold; color: #D32F2F; }
        .low-item .reason { color: #333; margin-top: 4px; font-style: italic; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <h1>Relatório de Avaliações</h1>
        <p>Starlink · Tocantins Transporte WiFi</p>
    </div>

    <div class="content">

        <!-- Período -->
        <div class="period">
            Período: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</strong> a <strong>{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
            &nbsp;|&nbsp; Gerado em: {{ now()->format('d/m/Y H:i') }}
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-row">
                <div class="stat-box">
                    <div class="stat-inner">
                        <div class="stat-value">{{ $totalInvites }}</div>
                        <div class="stat-label">Convites Enviados</div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-inner">
                        <div class="stat-value">{{ $totalAnswered }}</div>
                        <div class="stat-label">Respostas</div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-inner">
                        <div class="stat-value gold">{{ number_format($avgRating, 1, ',', '.') }}</div>
                        <div class="stat-label">Nota Média</div>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-inner">
                        <div class="stat-value blue">{{ $responseRate }}%</div>
                        <div class="stat-label">Taxa de Resposta</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribuição de Notas -->
        <div class="section-title">Distribuição das Notas</div>
        <table class="dist-table">
            @for($r = 5; $r >= 1; $r--)
            @php $count = $distribution[$r] ?? 0; $pct = $totalAnswered > 0 ? ($count / $totalAnswered) * 100 : 0; @endphp
            <tr>
                <td style="width:80px"><span class="stars">{{ str_repeat('★', $r) }}{{ str_repeat('☆', 5 - $r) }}</span></td>
                <td>
                    <div class="dist-bar">
                        <div class="dist-fill {{ $r <= 2 ? 'low' : '' }}" style="width: {{ $pct }}%"></div>
                    </div>
                </td>
                <td style="width:40px;text-align:right;font-weight:bold">{{ $count }}</td>
                <td style="width:50px;text-align:right;color:#888">{{ number_format($pct, 0) }}%</td>
            </tr>
            @endfor
        </table>

        <!-- Notas Baixas -->
        @if($lowRatings->count() > 0)
        <div class="section-title" style="color:#D32F2F;border-color:#D32F2F">Notas Baixas (1 a 3 estrelas) — {{ $lowRatings->count() }} registro(s)</div>
        <div class="low-section">
            @foreach($lowRatings->take(15) as $review)
            <div class="low-item">
                <span class="phone">{{ $review->phone ?: 'Sem telefone' }}</span>
                <span class="badge badge-red" style="margin-left:8px">{{ $review->rating }} ★</span>
                <span style="color:#888;font-size:9px;margin-left:8px">{{ $review->batch_date?->format('d/m/Y') }}</span>
                @if($review->reason)
                <div class="reason">{{ $review->reason }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Lista Completa -->
        <div class="page-break"></div>
        <div class="section-title">Lista Completa de Avaliações ({{ $reviews->count() }})</div>
        <table class="reviews">
            <thead>
                <tr>
                    <th>Telefone</th>
                    <th>Lote</th>
                    <th>Envio</th>
                    <th>Nota</th>
                    <th>Motivo</th>
                    <th>Respondido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reviews as $review)
                <tr>
                    <td>{{ $review->phone ?: '-' }}</td>
                    <td>{{ $review->batch_date?->format('d/m') }}</td>
                    <td>
                        @php
                            $st = match($review->whatsapp_status) {
                                'sent' => ['Enviado', 'badge-green'],
                                'failed' => ['Falha', 'badge-red'],
                                default => ['Pendente', 'badge-gray'],
                            };
                        @endphp
                        <span class="badge {{ $st[1] }}">{{ $st[0] }}</span>
                    </td>
                    <td>
                        @if($review->rating)
                            <span class="badge {{ $review->rating >= 4 ? 'badge-green' : ($review->rating >= 3 ? 'badge-gold' : 'badge-red') }}">
                                {{ $review->rating }} ★
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td style="max-width:180px;word-wrap:break-word">{{ \Illuminate\Support\Str::limit($review->reason, 80) ?: '-' }}</td>
                    <td>{{ $review->submitted_at?->format('d/m H:i') ?: '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            © {{ date('Y') }} Starlink · Tocantins Transporte WiFi — Relatório gerado automaticamente
        </div>
    </div>

</body>
</html>
