{{--
    Fieldset `gallery` — fields: `title` (text), `images` (asset, "Allow multiple" on).
    Multiple asset fields hydrate to a collection of Asset models.
--}}
<section class="block" id="block-{{ $block->id }}">
    @if ($block->title)
        <h2>{{ $block->title }}</h2>
    @endif
    <div class="gallery">
        @foreach ($block->images ?? [] as $image)
            <a href="{{ $image->url() }}">
                <img src="{{ $image->url('thumbnail') }}" alt="{{ $image->alt ?? '' }}" loading="lazy">
            </a>
        @endforeach
    </div>
</section>
