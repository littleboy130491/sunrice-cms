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
`<meta name="robots">` (only when the page is hidden), canonical link,
Open Graph tags, Twitter card tags, `hreflang` alternates and
`x-default`. All URLs are absolute. Fallback pages canonicalize to the
main-locale URL and emit no hreflang. Works without an entry (uses
`app.name`, current URL).

Values come from the entry's SEO tab: meta title, description, canonical
URL, share image and "Hide from search engines". Override them per call
with the `title`, `description` and `noindex` props:

```blade
<x-sunrice::seo :entry="$entry ?? null" title="Search results" :noindex="true" />
```

Hidden entries are also left out of `/sitemap.xml`. Hide a whole site
(e.g. staging) and set the `twitter:site` handle, default description and
default share image under **Settings** in the admin (or with
`SUNRICE_NOINDEX=true` / `SUNRICE_TWITTER_SITE=@handle`).

## `<x-sunrice::code>`

Prints the code snippets from **Settings → Code snippets** (analytics, tag
managers, chat widgets…). Put one of each in your layout:

```blade
<head>
    …
    <x-sunrice::code position="head" />
</head>
<body>
    <x-sunrice::code position="body_start" />
    …
    <x-sunrice::code position="body_end" />
</body>
```

Snippets are printed as-is, so only give `sunrice.settings.edit` to people
you trust with the site's scripts. The starter layout and the package's
fallback views already include all three.

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
post to `route('sunrice.frontend.forms.submit', $handle)` with the
page's language, so messages come back in it. The slot sees
`$component->form` (fields/schema), `$component->error('email')` and
`$component->old('email')` (this form's message and previous input; other
forms on the same page keep theirs), and `$component->success()`. After a
submission the visitor lands back on the form (`#sunrice-form-{handle}`),
and messages name fields by their label.
