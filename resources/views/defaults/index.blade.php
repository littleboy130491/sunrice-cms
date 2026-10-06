<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-sunrice::seo :title="isset($collection) ? $collection->titleIn($locale ?? null) : null" />
    <x-sunrice::code position="head" />
</head>
<body>
    <x-sunrice::code position="body_start" />
    <h1>{{ isset($collection) ? $collection->titleIn($locale ?? null) : 'Archive' }}</h1>
    <ul>
        @foreach($entries as $entry)
            <li><a href="{{ $entry->url }}">{{ $entry->title }}</a></li>
        @endforeach
    </ul>
    @if($entries instanceof \Illuminate\Contracts\Pagination\Paginator)
        {{ $entries->links() }}
    @endif
    <x-sunrice::code position="body_end" />
</body>
</html>
