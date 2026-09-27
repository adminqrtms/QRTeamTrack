<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'QRTMS') }}</title>

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
</body>
</html>