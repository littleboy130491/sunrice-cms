<?php

declare(strict_types=1);

// Interface text used by the starter templates and the public form.
// Override per site: php artisan vendor:publish --tag=sunrice-translations
return [
    'not_translated' => 'This page is not translated yet; showing the original version.',
    'nothing_yet' => 'Nothing here yet.',
    'nothing_in_term' => 'Nothing in :term yet.',
    'by' => 'by :name',
    'related' => 'Related',
    'latest_articles' => 'Latest articles',
    'learn_more' => 'Learn more',
    'languages' => 'Language',
    'main_menu' => 'Main',
    'footer_menu' => 'Footer',
    'submit' => 'Submit',
    'thank_you' => 'Thank you!',
    'upload_failed' => 'The file could not be uploaded. It may be larger than :size.',
    'upload_type' => 'This file type isn\'t accepted. Please upload: :types.',
    'upload_type_blocked' => 'This file type isn\'t accepted.',
    'upload_size' => 'The file is too large. The maximum is :size.',
    'upload_hint' => 'Accepted: :types. Up to :size.',
    'upload_hint_any' => 'Up to :size.',
    // Banner above unpublished pages, for signed-in editors.
    'draft' => 'Draft',
    'draft_scheduled' => 'This entry is scheduled for :date and isn\'t visible to the public yet.',
    'draft_unpublished' => 'This entry is a draft and isn\'t visible to the public.',
    'draft_not_ready' => 'This translation isn\'t marked Ready, so the public sees the :language version.',
    'draft_signed_in' => 'You can see it because you\'re signed in.',
    'edit_entry' => 'Edit entry',
    // Entry filter (<x-sunrice::entry-filter> + partials/entry-filter).
    'filter_min' => 'Min',
    'filter_max' => 'Max',
    'filter_from' => 'From',
    'filter_to' => 'To',
    'filter_any' => 'Any',
    'filter_apply' => 'Apply',
    'filter_clear' => 'Clear filters',
    'filter_remove' => 'Remove :filter',
    'sort_by' => 'Sort by',
    'results' => '{0} No results|{1} 1 result|[2,*] :count results',
    // Site search.
    'search' => 'Search',
    'search_placeholder' => 'Search this site',
    'search_results_for' => 'Results for “:query”',
    'search_nothing' => 'Nothing matches “:query”. Try other words.',
    'search_hint' => 'Type a few words to search the site.',
    // Header menu on small screens.
    'menu' => 'Menu',
    // Error pages.
    'error_404_title' => 'Page not found',
    'error_404_text' => 'The page you are looking for doesn\'t exist or has moved.',
    'error_500_title' => 'Something went wrong',
    'error_500_text' => 'We couldn\'t show this page. Please try again in a moment.',
    'back_home' => 'Back to the home page',
];
