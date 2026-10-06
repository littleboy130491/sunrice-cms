<?php

declare(strict_types=1);

namespace Sunrice\Frontend;

use Illuminate\Support\Facades\View;
use Sunrice\Sunrice;

/**
 * Resolves the Blade view for a page. First existing candidate wins;
 * developer hooks (Sunrice::resolveTemplateUsing) run afterwards and
 * may replace the result.
 */
class TemplateResolver
{
    public function resolve(TemplateContext $context): string
    {
        $view = collect($this->candidates($context))
            ->first(fn (string $view) => View::exists($view));

        $view ??= 'sunrice::defaults.show';

        foreach (app(Sunrice::class)->templateHooks() as $hook) {
            $view = $hook($view, $context) ?? $view;
        }

        return $view;
    }

    /** @return array<int, string> */
    protected function candidates(TemplateContext $context): array
    {
        return match ($context->pageType) {
            'entry' => array_filter([
                $context->entry?->template,
                $context->collection?->setting('template'),
                'sunrice.'.$context->collection?->handle.'.show',
                'sunrice.show',
                'sunrice::defaults.show',
            ]),
            'archive' => array_filter([
                $context->collection?->setting('archive_template'),
                'sunrice.'.$context->collection?->handle.'.index',
                'sunrice.index',
                'sunrice::defaults.index',
            ]),
            'term' => array_filter([
                $context->taxonomy?->setting('template'),
                'sunrice.taxonomies.'.$context->taxonomy?->handle.'.show',
                'sunrice.taxonomies.show',
                'sunrice::defaults.term',
            ]),
            default => ['sunrice::defaults.show'],
        };
    }
}
