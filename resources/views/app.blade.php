<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name') }} — Sunrice</title>
    {{-- Apply the saved appearance before paint to avoid a light/dark flash. --}}
    <script>
        (function () {
            try {
                var appearance = localStorage.getItem('sunrice-appearance') || 'system';
                var dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">
    @php
        $dist = file_exists(public_path('vendor/sunrice/manifest.json'))
            ? 'vendor/sunrice'
            : null;
        if ($dist !== null) {
            Vite::useBuildDirectory($dist);
        }
    @endphp
    @if ($dist !== null)
        @vite('resources/js/app.tsx')
    @else
        {{-- Fallback to the package's committed dist/ --}}
        @php
            $manifestPath = base_path('vendor/sunrice/cms/dist/manifest.json');
            if (! file_exists($manifestPath)) {
                $manifestPath = dirname(__DIR__, 2).'/dist/manifest.json';
            }
            $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
            $entry = $manifest['resources/js/app.tsx'] ?? null;
        @endphp
        @if ($entry)
            <script type="module" src="{{ asset('vendor/sunrice/'.$entry['file']) }}"></script>
            @foreach ($entry['css'] ?? [] as $css)
                <link rel="stylesheet" href="{{ asset('vendor/sunrice/'.$css) }}">
            @endforeach
        @endif
    @endif
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
