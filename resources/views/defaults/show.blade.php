<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-sunrice::seo :entry="$entry ?? null" />
</head>
<body>
    <article>
        <h1>{{ $entry->title ?? '' }}</h1>
        @foreach(($entry->data ?? []) as $handle => $value)
            @if(is_string($value))
                <div class="field field-{{ $handle }}">{!! $value !!}</div>
            @endif
        @endforeach
    </article>
</body>
</html>
