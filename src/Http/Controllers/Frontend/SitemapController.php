<?php

declare(strict_types=1);

namespace Sunrice\Http\Controllers\Frontend;

use Illuminate\Routing\Controller;
use Sunrice\Frontend\SitemapBuilder;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends Controller
{
    public function __invoke(SitemapBuilder $sitemap): Response
    {
        return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
    }
}
