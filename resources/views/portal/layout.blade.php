<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'WiFi Tocantins Portal') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/tailwind.play.js') }}"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .elegant-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(249, 250, 251, 0.95) 100%);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }
        .connect-button {
            background: linear-gradient(135deg, #10B981 0%, #059669 50%, #047857 100%);
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
        }
        .connect-button:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 14px 40px rgba(16, 185, 129, 0.45);
        }
    </style>
    @stack('head')
</head>
<body class="bg-slate-50 min-h-screen">
    @yield('content')

    <script src="{{ asset('js/mac-detector.js') }}"></script>
    <script src="{{ asset('js/portal-dashboard.js') }}"></script>
    @stack('scripts')
</body>
</html>

