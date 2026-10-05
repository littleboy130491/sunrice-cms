<title>{{ $title }}</title>
@if($description)
<meta name="description" content="{{ $description }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:title" content="{{ $title }}">
@if($description)
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('-', '_', $locale) }}">
@if($image)
<meta property="og:image" content="{{ $image }}">
@endif
@foreach($alternates as $alternateLocale => $url)
<link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $url }}">
@endforeach
