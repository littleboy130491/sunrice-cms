<?php

declare(strict_types=1);

namespace Sunrice\View\Components;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\Component;
use Sunrice\Frontend\SiteSearch;
use Sunrice\Models\Entry;

/**
 * `<x-sunrice::search>` — site search in Blade. Renders only its slot:
 *
 *   $component->action   the search page URL in the page's language
 *   $component->name     the query-string name ("q")
 *   $component->query    what was searched (from ?q=)
 *   $component->results  with :results="true", the matching entries
 *                        (paginator, resolved in the page language)
 *
 * Use it for the search box (header) and, with results, for a search page
 * or a search inside a section (collections="articles").
 */
class Search extends Component
{
    public string $action;

    public string $query;

    /** @var LengthAwarePaginator<int, Entry>|null */
    public ?LengthAwarePaginator $results = null;

    /**
     * @param  bool  $results  run the search and fill $results
     * @param  array<int, string>|string|null  $collections  handles to search (default: sunrice.search.collections)
     * @param  int|null  $perPage  results per page (default: sunrice.search.per_page)
     * @param  string  $pageName  query-string page name
     * @param  string  $name  query-string name of the search text
     */
    public function __construct(
        bool $results = false,
        array|string|null $collections = null,
        ?int $perPage = null,
        string $pageName = 'page',
        public string $name = 'q',
    ) {
        $this->action = SiteSearch::url();
        $raw = request()->query($name);
        $this->query = is_string($raw) ? trim($raw) : '';

        if ($results) {
            $handles = is_string($collections) ? array_filter(array_map('trim', explode(',', $collections))) : $collections;
            $this->results = app(SiteSearch::class)->search($this->query, $handles ?: null, $perPage, $pageName);
        }
    }

    public function render(): string
    {
        return 'sunrice::components.entries';
    }
}
