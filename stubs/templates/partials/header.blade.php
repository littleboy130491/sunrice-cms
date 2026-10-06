{{--
    Site header: data from a global set + a menu + the language switcher.

    Admin setup this partial expects (all optional — it renders without them):
      - Global set `site` with fields: `name` (text), `logo` (asset, single image)
      - Menu `main`
--}}
@php
    $site = sunrice_global('site');           // GlobalData: read fields as properties or ->get('field', 'default')
    $mainMenu = sunrice_menu('main');          // Collection of MenuNode (label, url, newTab, children)
    // ['id' => '/about', 'en' => '/en/about']: this entry, term page or listing in each language.
    // $sunricePage describes the page itself ($entry/$term may be leftovers of a template loop).
    $page = $sunricePage ?? null;
    $languages = sunrice_locale_urls($page?->entry ?? $page?->term, $page?->term ? $page->collection : null);
@endphp

<header class="site-header">
    <div class="container">
        <a class="brand" href="{{ url(\Sunrice\Support\Locales::prefix($locale) ?: '/') }}">
            @if ($site->logo)
                {{-- Asset fields hydrate to Sunrice\Models\Asset: url(), url('thumbnail'|'medium'|'large'), alt, title --}}
                <img src="{{ $site->logo->url('medium') }}" alt="{{ $site->logo->alt ?? $site->get('name') }}">
            @endif
            <span>{{ $site->get('name', config('app.name')) }}</span>
        </a>

        @if ($mainMenu->isNotEmpty())
            <nav aria-label="{{ __('sunrice::frontend.main_menu') }}">
                @include('sunrice.partials.menu', ['items' => $mainMenu])
            </nav>
        @endif

        @if (count($languages) > 1)
            {{-- Language names come from Settings → Languages; the code is the fallback. --}}
            <nav class="lang-switch" aria-label="{{ __('sunrice::frontend.languages') }}">
                @foreach ($languages as $code => $href)
                    <a href="{{ $href }}" hreflang="{{ $code }}" lang="{{ $code }}" @if ($code === $locale) aria-current="true" @endif>{{ \Sunrice\Support\Locales::name($code) }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</header>
