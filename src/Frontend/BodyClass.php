<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Http\Request;
use Sunrice\Models\Setting;
use Sunrice\Sunrice;

/**
 * Classes for the page's <body>, like WordPress' body_class(): what kind
 * of page it is and what it shows, so CSS and scripts can target it.
 *
 *   entry page   page-entry collection-{handle} entry-{id} entry-{slug}
 *                [home] [has-parent parent-{id}]
 *   listing      page-archive collection-{handle} [paged paged-{n}]
 *   term page    page-term taxonomy-{handle} term-{id} term-{slug}
 *                [collection-{handle}] [paged paged-{n}]
 *   always       template-{view} lang-{locale} [logged-in] [is-draft] [is-preview]
 *
 * Hooks registered with Sunrice::bodyClassUsing() can add or remove
 * classes; the result is de-duplicated and safe to print in an attribute.
 */
class BodyClass
{
    /**
     * @param  array<int, string>|string  $extra  classes added by the template
     * @return array<int, string>
     */
    public static function for(Request $request, array|string $extra = []): array
    {
        $page = $request->attributes->get('sunrice.page');
        $page = $page instanceof TemplateContext ? $page : null;
        $classes = $page === null ? [] : static::forPage($page);

        $template = $request->attributes->get('sunrice.template');
        if (is_string($template) && $template !== '') {
            $classes[] = 'template-'.static::templateName($template);
        }
        $classes[] = 'lang-'.($page->locale ?? app()->getLocale());

        if ($page !== null && $page->pageType !== 'entry') {
            $current = (int) $request->query('page', '1');
            if ($current > 1) {
                $classes[] = 'paged';
                $classes[] = 'paged-'.$current;
            }
        }
        if (auth(config('sunrice.auth.guard', 'web'))->check()) {
            $classes[] = 'logged-in';
        }
        if ($request->attributes->get('sunrice.draft') === true) {
            $classes[] = 'is-draft';
        }
        if ($request->attributes->get('sunrice.preview') === true) {
            $classes[] = 'is-preview';
        }

        $classes = array_merge($classes, is_string($extra) ? preg_split('/\s+/', $extra) ?: [] : $extra);

        foreach (app(Sunrice::class)->bodyClassHooks() as $hook) {
            $classes = (array) $hook($classes, $page);
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $class) => is_scalar($class) ? static::clean((string) $class) : '',
            $classes,
        ))));
    }

    /** @return array<int, string> */
    protected static function forPage(TemplateContext $page): array
    {
        $classes = ['page-'.$page->pageType];
        if ($page->collection !== null) {
            $classes[] = 'collection-'.$page->collection->handle;
        }

        if ($page->pageType === 'entry' && $page->entry !== null) {
            $entry = $page->entry;
            if ((int) Setting::get('homepage_entry_id', 0) === (int) $entry->id) {
                $classes[] = 'home';
            }
            $classes[] = 'entry-'.$entry->id;
            $slug = $entry->getSlugAttribute();
            if ($slug !== null && $slug !== '') {
                $classes[] = 'entry-'.$slug;
            }
            if ($entry->parent_id !== null) {
                $classes[] = 'has-parent';
                $classes[] = 'parent-'.$entry->parent_id;
            }
        }

        if ($page->pageType === 'term' && $page->term !== null) {
            if ($page->taxonomy !== null) {
                $classes[] = 'taxonomy-'.$page->taxonomy->handle;
            }
            $classes[] = 'term-'.$page->term->id;
            $slug = $page->term->getSlugAttribute();
            if ($slug !== null && $slug !== '') {
                $classes[] = 'term-'.$slug;
            }
        }

        return $classes;
    }

    /**
     * 'sunrice.articles.show' → 'articles-show', 'sunrice::defaults.show'
     * → 'defaults-show', 'landing' → 'landing'.
     */
    protected static function templateName(string $view): string
    {
        $view = (string) preg_replace('/^sunrice(::|\.)/', '', $view);

        return str_replace(['.', '/', '::'], '-', $view);
    }

    /** Lowercase letters, digits, - and _ only. */
    protected static function clean(string $class): string
    {
        return trim((string) preg_replace('/[^a-z0-9_-]+/', '-', mb_strtolower($class)), '-');
    }
}
