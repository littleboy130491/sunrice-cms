<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-sunrice::seo />
</head>
<body>
    <h1>{{ $term->name }}</h1>
    <ul>
        @foreach($entries as $entry)
            <li><a href="{{ $entry->url }}">{{ $entry->title }}</a></li>
        @endforeach
    </ul>
</body>
</html>
