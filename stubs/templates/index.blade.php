{{--
    Default collection archive (resolved as `sunrice.index`), shown at the
    collection's archive route when "Has archive" is enabled.

    Available: $entries (paginator of Entry, already resolved for $locale),
    $collection, $locale, $pageType.

    $collection->archiveText($locale) gives the listing heading and intro
    in the active language (falling back to the main language), set under
    Structure → Collections → Pages & URLs.
--}}
@php($listing = $collection->archiveText($locale))
@extends('sunrice.layouts.app', ['seoTitle' => $listing['title'] ?: $collection->title])

@section('content')
    <h1>{{ $listing['title'] ?: $collection->title }}</h1>

    @if ($listing['intro'])
        <p class="muted">{{ $listing['intro'] }}</p>
    @endif

    @if ($entries->isEmpty())
        <p class="muted">{{ __('sunrice::frontend.nothing_yet') }}</p>
    @else
        <ul class="cards">
            @foreach ($entries as $entry)
                @include('sunrice.partials.card', ['entry' => $entry])
            @endforeach
        </ul>

        <div class="pagination">{{ $entries->links() }}</div>
    @endif
@endsection
