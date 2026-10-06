{{--
    Default term archive (resolved as `sunrice.taxonomies.show`; add
    `sunrice/taxonomies/{taxonomy}/show.blade.php` for one taxonomy),
    shown at the taxonomy's route when its archive is enabled.

    Available: $term, $taxonomy, $entries (paginator), $locale, $pageType.

    $term->name, $term->slug, $term->url    in the active language
    $term->parent / $term->children         for hierarchical taxonomies
    $term->get('handle')                    a field from the taxonomy blueprint (hydrated like entry fields)
--}}
@extends('sunrice.layouts.app', ['seoTitle' => $term->name.' — '.$taxonomy->title])

@section('content')
    <p class="meta">{{ $taxonomy->title }}</p>
    <h1>{{ $term->name }}</h1>

    @if ($description = $term->get('description'))
        <p class="muted">{{ $description }}</p>
    @endif

    @if ($term->children->isNotEmpty())
        <ul class="tags">
            @foreach ($term->children as $child)
                <li><a href="{{ $child->url }}">{{ $child->name }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($entries->isEmpty())
        <p class="muted">No entries in this {{ strtolower($taxonomy->title) }} yet.</p>
    @else
        <ul class="cards">
            @foreach ($entries as $entry)
                @include('sunrice.partials.card', ['entry' => $entry])
            @endforeach
        </ul>

        <div class="pagination">{{ $entries->links() }}</div>
    @endif
@endsection
