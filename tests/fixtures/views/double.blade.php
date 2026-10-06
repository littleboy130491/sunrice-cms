<x-sunrice::entries collection="news" :paginate="true" :per-page="1">
    @foreach($component->entries as $e)<span class="n">{{ $e->title }}</span>@endforeach
</x-sunrice::entries>
<x-sunrice::entries collection="events" :paginate="true" :per-page="1">
    @foreach($component->entries as $e)<span class="v">{{ $e->title }}</span>@endforeach
</x-sunrice::entries>