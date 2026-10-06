{{-- Entry teaser used by archives and listings. --}}
<li class="card">
    @if ($image = $entry->get('image'))
        <a href="{{ $entry->url }}"><img src="{{ $image->url('medium') }}" alt="{{ $image->alt ?? $entry->title }}" loading="lazy"></a>
    @endif
    @if ($entry->published_at)
        <time class="meta" datetime="{{ $entry->published_at->toAtomString() }}">{{ $entry->published_at->translatedFormat('j F Y') }}</time>
    @endif
    <h2><a href="{{ $entry->url }}">{{ $entry->title }}</a></h2>
    @if ($excerpt = $entry->get('excerpt'))
        <p class="muted">{{ $excerpt }}</p>
    @endif
</li>
