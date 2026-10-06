{{--
    Default collection archive (resolved as `sunrice.index`), shown at the
    collection's archive route when "Has archive" is enabled.

    Available: $entries (paginator of Entry, already resolved for $locale),
    $collection, $locale, $pageType.

    $collection->archive_data holds the listing heading (`title`) and
    intro (`intro`) set under Structure → Collections → Pages & URLs.
--}}
@extends('sunrice.layouts.app', ['seoTitle' => ($collection->archive_data['title'] ?? null) ?: $collection->title])

@section('content')
    <h1>{{ $collection->archive_data['title'] ?? $collection->title }}</h1>

    @if (! empty($collection->archive_data['intro']))
        <p class="muted">{{ $collection->archive_data['intro'] }}</p>
    @endif

    @if ($entries->isEmpty())
        <p class="muted">Nothing here yet.</p>
    @else
        <ul class="cards">
            @foreach ($entries as $entry)
                @include('sunrice.partials.card', ['entry' => $entry])
            @endforeach
        </ul>

        <div class="pagination">{{ $entries->links() }}</div>
    @endif
@endsection
