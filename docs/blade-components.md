# Blade components

## `<x-sunrice::entries>`

Render published entries of a collection inside a slot — the slot
receives the component (`$component->entries`).

```blade
<x-sunrice::entries collection="articles" :paginate="true" :per-page="10">
    @foreach ($component->entries as $entry)
        <article>
            <h2><a href="{{ $entry->url }}">{{ $entry->title }}</a></h2>
        </article>
    @endforeach
    {{ $component->entries->links() }}
</x-sunrice::entries>
```

Props: `collection` (required), `paginate`, `per-page`, `page-name`
(default `{collection}_page`, so two components on one page paginate
independently), `limit`, `where` (`['author' => 3]` or
`[['price', '>', 10]]`), `terms` (`'news'`/`['news','guides']` — includes
child terms), `order-by` (`published_at desc`), `with` (extra eager
loads).

## `<x-sunrice::seo>`

`<x-sunrice::seo :entry="$entry" />` emits `<title>`, meta description,
canonical link, Open Graph/Twitter tags, `hreflang` alternates and
`x-default`. Fallback pages canonicalize to the main-locale URL and emit
no hreflang. Works without an entry (uses `app.name`, current URL).

## `<x-sunrice::form>`

Public form renderer:

```blade
<x-sunrice::form handle="contact">
    <input name="data[name]" required>
    <input name="data[email]" type="email" required>
    <textarea name="data[message]"></textarea>
</x-sunrice::form>
```

The component outputs `<form>` + `@csrf` + honeypot fields; submissions
post to `route('sunrice.frontend.forms.submit', $handle)`. The slot
sees `$component->form` (fields/schema), the shared `$errors` bag, and
`$component->success()` (true after a successful submission flash or the
form's `success_message`).
