<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Producer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $entries = [];
            $static = ['home', 'about', 'producers.index', 'events.index', 'blog.index', 'reviews.index', 'for-producers', 'faq', 'contact', 'legal.terms', 'legal.privacy'];
            foreach ($static as $name) {
                $entries[] = [route('ro.'.$name), route('hu.'.$name), null];
            }
            foreach (Producer::publicListing()->select('slug', 'updated_at')->get() as $p) {
                $entries[] = [route('ro.producers.show', $p->slug), route('hu.producers.show', $p->slug), $p->updated_at];
            }
            foreach (Event::published()->select('slug', 'updated_at')->get() as $e) {
                $entries[] = [route('ro.events.show', $e->slug), route('hu.events.show', $e->slug), $e->updated_at];
            }
            foreach (BlogPost::published()->get(['slug_ro', 'slug_hu', 'updated_at']) as $b) {
                $entries[] = [route('ro.blog.show', $b->slug_ro), route('hu.blog.show', $b->slug_hu), $b->updated_at];
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
            foreach ($entries as [$ro, $hu, $mod]) {
                foreach ([$ro, $hu] as $loc) {
                    $out .= '<url><loc>'.e($loc).'</loc>'
                        .'<xhtml:link rel="alternate" hreflang="ro" href="'.e($ro).'"/>'
                        .'<xhtml:link rel="alternate" hreflang="hu" href="'.e($hu).'"/>'
                        .'<xhtml:link rel="alternate" hreflang="x-default" href="'.e($ro).'"/>'
                        .($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '')
                        ."</url>\n";
                }
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nDisallow: /crm\nDisallow: /account\nDisallow: /producer\nDisallow: /staff\nDisallow: /files\nDisallow: /review\nDisallow: /p/\n\nSitemap: ".route('sitemap')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
