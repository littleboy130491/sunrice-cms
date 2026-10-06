{{--
    Base layout for every Sunrice page.

    Child templates `@extends('sunrice.layouts.app')` and fill:
      - @section('content')   the page body
    and may pass `$entry` (single pages) so <x-sunrice::seo> can emit
    the title, description, canonical URL, Open Graph tags and hreflang.

    Variables Sunrice passes to every template: $locale, $pageType
    ('entry' | 'archive' | 'term'), plus $entry / $entries / $collection /
    $term / $taxonomy depending on the page type.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- SEO tags. Without an entry it falls back to app.name and the current URL. --}}
    <x-sunrice::seo :entry="$entry ?? null" :title="$seoTitle ?? null" />

    {{-- Starter styles: delete this block and use your own CSS (Vite, Tailwind, ...). --}}
    <style>
        :root { color-scheme: light dark; --fg: #18181b; --muted: #71717a; --line: #e4e4e7; --accent: #2563eb; }
        @media (prefers-color-scheme: dark) { :root { --fg: #fafafa; --muted: #a1a1aa; --line: #27272a; --accent: #60a5fa; } }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: var(--fg); }
        a { color: var(--accent); }
        img { max-width: 100%; height: auto; border-radius: 8px; }
        .container { max-width: 960px; margin: 0 auto; padding: 0 20px; }
        .site-header, .site-footer { border-bottom: 1px solid var(--line); }
        .site-footer { border-top: 1px solid var(--line); border-bottom: 0; margin-top: 64px; padding: 32px 0; color: var(--muted); font-size: 14px; }
        .site-header .container { display: flex; gap: 24px; align-items: center; justify-content: space-between; min-height: 64px; flex-wrap: wrap; }
        .brand { font-weight: 700; text-decoration: none; color: inherit; display: flex; gap: 8px; align-items: center; }
        .brand img { height: 32px; width: auto; border-radius: 0; }
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; gap: 20px; flex-wrap: wrap; }
        nav li ul { display: none; }
        nav a { color: inherit; text-decoration: none; }
        nav a[aria-current="page"] { color: var(--accent); font-weight: 600; }
        .lang-switch { display: flex; gap: 8px; font-size: 14px; text-transform: uppercase; }
        .muted { color: var(--muted); }
        .meta { color: var(--muted); font-size: 14px; display: flex; gap: 12px; flex-wrap: wrap; }
        .tags { display: flex; gap: 8px; flex-wrap: wrap; padding: 0; list-style: none; }
        .tags a { font-size: 13px; padding: 2px 10px; border: 1px solid var(--line); border-radius: 999px; text-decoration: none; }
        .cards { display: grid; gap: 24px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); padding: 0; list-style: none; }
        .card { border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
        .card h2, .card h3 { margin: 8px 0; font-size: 18px; }
        .block { margin: 48px 0; }
        .hero { padding: 64px 0; text-align: center; }
        .hero h1 { font-size: clamp(32px, 5vw, 52px); line-height: 1.1; margin: 0 0 16px; }
        .button { display: inline-block; padding: 10px 18px; border-radius: 8px; background: var(--fg); color: #fff; text-decoration: none; }
        @media (prefers-color-scheme: dark) { .button { color: #18181b; } }
        .gallery { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        form label { display: block; margin: 12px 0 4px; font-weight: 600; }
        form input, form textarea, form select { width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; font: inherit; background: transparent; color: inherit; }
        form button { margin-top: 16px; padding: 10px 18px; border: 0; border-radius: 8px; background: var(--fg); color: #fff; font: inherit; cursor: pointer; }
        .error { color: #dc2626; font-size: 14px; }
        .notice { padding: 12px 16px; border-radius: 8px; background: #dcfce7; color: #166534; }
        .pagination nav { margin-top: 32px; }
    </style>
    @stack('head')
    <x-sunrice::code position="head" />
</head>
<body>
    <x-sunrice::code position="body_start" />
    @include('sunrice.partials.header')

    <main class="container">
        @yield('content')
    </main>

    @include('sunrice.partials.footer')
    @stack('scripts')
    <x-sunrice::code position="body_end" />
</body>
</html>
