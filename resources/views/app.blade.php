<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ \Sunrice\Support\Branding::name() }}</title>
    @if ($sunriceLogo = \Sunrice\Support\Branding::logoUrl())
        <link rel="icon" href="{{ $sunriceLogo }}">
    @endif
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
    @if ($sunriceFont = \Sunrice\Support\Branding::fontHref())
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="{{ $sunriceFont }}" rel="stylesheet">
    @endif
    {{-- Branding (Settings → Branding): font and global color. --}}
    <style>{!! \Sunrice\Support\Branding::css() !!}</style>
    <script>window.sunriceBrand = @json(\Sunrice\Support\Branding::name());</script>
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
