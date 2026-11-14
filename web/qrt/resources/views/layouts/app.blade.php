<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>QRTeam Track</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Bootstrap Styles & Scripts -->
    <link href="{{ asset('bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <script src="{{ asset('bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>

    <!-- Page-specific styles -->
    @stack('styles')

    <style>
        body { font-family: 'Nunito', sans-serif; }

        /* Required form fields: red asterisk after the label */
        .form-label.required::after { content: " *"; color: #dc3545; font-weight: 700; }
        /* Show/hide password button inside password fields */
        .password-toggle-wrap { position: relative; }
        .password-toggle-wrap > input { padding-right: 2.75rem; }
        .password-toggle-wrap > input.is-invalid { background-position: right 2.6rem center; }
        .password-toggle { position: absolute; top: 50%; right: .5rem; transform: translateY(-50%);
                           border: 0; background: transparent; color: #6c757d; padding: .25rem; line-height: 0; }
        .password-toggle:hover, .password-toggle:focus-visible { color: #0d6efd; }
        .password-rules li { list-style: none; }
        .password-rules li::before { content: "\2715"; display: inline-block; width: 1.2em; color: #dc3545; }
        .password-rules li.ok { color: #198754; }
        .password-rules li.ok::before { content: "\2713"; color: #198754; }

        /* Live refresh: briefly highlight rows that just appeared */
        @keyframes live-new-flash { from { background-color: #fff3cd; } to { background-color: transparent; } }
        .live-new, .live-new > td { animation: live-new-flash 3s ease-out; }
        .live-indicator { font-size: .75rem; }
        .live-indicator .dot { width: 8px; height: 8px; border-radius: 50%; background: #198754; display: inline-block; animation: live-pulse 1.5s infinite; }
        @keyframes live-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
    </style>
</head>
<body class="bg-light">
    <div id="app">
        @auth
            @include('partials.navbar')
        @endauth

        <main>
            @yield('content')
        </main>
    </div>

    <!-- Optional old section support -->
    @yield('scripts')

    <!-- Page-specific scripts -->
    @stack('scripts')
    <script src="{{ asset('js/password-toggle.js') }}" defer></script>

    @auth
        <script src="{{ asset('js/live-refresh.js') }}" defer></script>
    @endauth
</body>
</html>