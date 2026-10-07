<?php

declare(strict_types=1);

namespace Sunrice\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Mcp\Templates;

#[IsReadOnly]
#[Description('Read this before creating or changing page templates: how Sunrice picks a template (priority and fallbacks), layouts and partials, the variables each page type receives, reading fields, querying entries, menus, globals, forms, SEO and languages, plus which template every collection and taxonomy uses right now and the template files that exist.')]
class GetTemplateGuide extends SunriceTool
{
    protected string $name = 'get_template_guide';

    protected const PRIMER = <<<'MD'
# Sunrice templates: guide for agents

Templates are Laravel Blade views. The site's own templates live in
`resources/views/sunrice/` (view names `sunrice.…`, e.g. the file
`articles/show.blade.php` is the view `sunrice.articles.show`). The
package's fallbacks are `sunrice::defaults.*`. Read starter templates with
read_template (source "starter") for complete, working examples, and copy
their patterns.

## How a page finds its template (first existing view wins)

- Entry page: the entry's own `template` → the collection's `template`
  setting → `sunrice.{collection}.show` → `sunrice.show` →
  `sunrice::defaults.show`.
- Listing (archive) page: the collection's `archive_template` →
  `sunrice.{collection}.index` → `sunrice.index` → `sunrice::defaults.index`.
- Term page: the term's own `template` → the taxonomy's `template` →
  `sunrice.taxonomies.{taxonomy}.show` → `sunrice.taxonomies.show` →
  `sunrice::defaults.term`.
- `/` shows the homepage entry (Settings) through the entry chain.
- Developer hooks (Sunrice::resolveTemplateUsing) may override the result.

So: to give one page a unique design, create e.g. `pages/landing.blade.php`
and set that entry's template to `sunrice.pages.landing` (update_entry
template). For every entry of a collection, create
`{collection}/show.blade.php`. Prefer these conventional names over
collection settings.

## Inheritance

- Pages `@extends('sunrice.layouts.app')` and fill `@section('content')`;
  pass `['seoTitle' => …]` as the second @extends argument for a fallback
  `<title>`. The layout `@include`s `sunrice.partials.header` / `footer`.
- Layouts must contain `<x-sunrice::seo />` in `<head>` and the three
  `<x-sunrice::code position="head|body_start|body_end" />` snippets.
- Layouts and partials read the page from `$sunricePage` (->entry, ->term,
  ->collection, ->taxonomy, ->pageType, ->locale), never `$entry`: a child's
  `@foreach ($entries as $entry)` overwrites `$entry` before the layout
  renders.
- Reusable bits: `@include('sunrice.partials.card', ['entry' => $item])`;
  flexible-content blocks: one partial per block type in `blocks/`
  (`@includeFirst(['sunrice.blocks.'.$block->type, 'sunrice.blocks.default'], ['block' => $block])`).

## Variables

Every page: `$locale`, `$pageType` ('entry' | 'archive' | 'term'),
`$sunricePage`. Entry pages: `$entry`, `$collection`. Listing pages:
`$entries` (paginator: `->links()`), `$collection`. Term pages: `$term`,
`$taxonomy`, `$entries` (paginator), `$collection` (null when the term page
spans all collections). Entries are already in the visitor's language.

## Rules

- Escape text with `{{ }}`; print rich_text fields with `{!! !!}` (already
  sanitized). Never print other input unescaped.
- Get data only through Sunrice: `$entry->get('field')`,
  `sunrice_entries('handle')->…->get()`, `<x-sunrice::entries>`,
  `sunrice_menu()`, `sunrice_global()`, `$term->get()`,
  `$collection->archive('field')`. No DB queries, no models, no @php
  blocks with logic beyond small formatting.
- Every field can be empty: guard with `@if`, `?->`, `?? ''`.
- Images: `$asset->url('medium')` (sizes: thumbnail, medium, large;
  `->url()` for the original), always `alt="{{ $asset->alt }}"`.
- Interface text in the visitor's language: `__('sunrice::frontend.key')`
  or plain text when the site has one language.
- Links: `$entry->url`, `$term->url`, menus' `$item->url`; never build URLs
  from slugs.
- CSS/JS: put files in the theme folder (write_template target "theme",
  served at /sunrice-theme/…) and link them with `asset('sunrice-theme/…')`,
  or keep a `<style>` in the layout like the starter. External CDNs work too.
- After writing, open the page with render_page and fix any error.
MD;

    public function handle(Request $request): Response
    {
        $docs = dirname(__DIR__, 3).'/docs/';
        $read = fn (string $page) => is_file($docs.$page.'.md') ? (string) file_get_contents($docs.$page.'.md') : '';

        return $this->json([
            'guide' => self::PRIMER,
            'docs' => [
                'templates' => $read('templates'),
                'blade_components' => $read('blade-components'),
                'helpers_and_queries' => $read('helpers'),
            ],
            'current_template_map' => Templates::map(),
            'site_templates' => Templates::files(Templates::appDirectory()),
            'theme_files' => Templates::files(Templates::themeDirectory()),
            'starter_templates' => Templates::files(Templates::starterDirectory()),
            'starter_published' => is_dir(Templates::appDirectory()),
            'can_write_templates' => WriteTemplate::allowed($request->user()),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
