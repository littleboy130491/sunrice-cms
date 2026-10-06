<title>{{ $title }}</title>
@if($description)
<meta name="description" content="{{ $description }}">
@endif
@if($robots)
<meta name="robots" content="{{ $robots }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title }}">
@if($description)
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('-', '_', $locale) }}">
@foreach(array_keys($alternates) as $alternateLocale)
@if($alternateLocale !== 'x-default' && $alternateLocale !== $locale)
<meta property="og:locale:alternate" content="{{ str_replace('-', '_', $alternateLocale) }}">
@endif
@endforeach
@if($image)
<meta property="og:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
@if($twitterSite)
<meta name="twitter:site" content="{{ $twitterSite }}">
@endif
<meta name="twitter:title" content="{{ $title }}">
@if($description)
<meta name="twitter:description" content="{{ $description }}">
@endif
@if($image)
<meta name="twitter:image" content="{{ $image }}">
@endif
@foreach($alternates as $alternateLocale => $url)
<link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $url }}">
@endforeach
