{{--
    Collection-specific override: used only for the `articles` collection
    (resolved as `sunrice.articles.show`, ahead of `sunrice.show`).
    Copy this folder and rename it to give any collection its own design.

    Shows: dates, author, taxonomy terms, a relationship field and
    querying other entries with <x-sunrice::entries>.

    Optional fields used: `image` (asset), `excerpt` (textarea),
    `body` (rich text), `related` (entries).
--}}
@extends('sunrice.layouts.app')

@section('content')
    <article>
        <p class="meta">
            @if ($entry->published_at)
                <time datetime="{{ $entry->published_at->toAtomString() }}">{{ $entry->published_at->translatedFormat('j F Y') }}</time>
            @endif
            {{-- `author` is the CMS user who created the entry. --}}
            @if ($entry->author)
                <span>by {{ $entry->author->name }}</span>
            @endif
        </p>

        <h1>{{ $entry->title }}</h1>

        @if ($excerpt = $entry->get('excerpt'))
            <p class="muted">{{ $excerpt }}</p>
        @endif

        @if ($image = $entry->get('image'))
            <img src="{{ $image->url('large') }}" alt="{{ $image->alt ?? $entry->title }}">
        @endif

        {!! $entry->get('body') !!}

        {{-- Terms attached to the entry (from any taxonomy the collection uses). --}}
        @if ($entry->terms->isNotEmpty())
            <ul class="tags">
                @foreach ($entry->terms as $term)
                    @php($term->resolveFor($locale))
                    <li><a href="{{ $term->urlIn($entry->collection) }}">{{ $term->name }}</a></li>
                @endforeach
            </ul>
        @endif
    </article>

    {{-- An "entries" field returns the picked entries, resolved for the active language. --}}
    @if (($related = $entry->get('related')) && $related->isNotEmpty())
        <section class="block">
            <h2>Related</h2>
            <ul class="cards">
                @foreach ($related as $item)
                    @include('sunrice.partials.card', ['entry' => $item])
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Query any collection from a template. Only published entries are returned,
         in the active language with whole-entry fallback. --}}
    <section class="block">
        <h2>Latest articles</h2>
        <x-sunrice::entries collection="articles" :limit="3" order-by="published_at desc">
            <ul class="cards">
                @foreach ($component->entries as $item)
                    @continue($item->id === $entry->id)
                    @include('sunrice.partials.card', ['entry' => $item])
                @endforeach
            </ul>
        </x-sunrice::entries>
    </section>
@endsection
