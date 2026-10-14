<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Model;

/**
 * Traduce parametrii de rută între limbi acolo unde slug-ul diferă pe limbă.
 */
class LocalizedRouteParameters
{
    public static function translate(string $routeName, array $params, string $target): array
    {
        foreach ($params as $key => $value) {
            if ($value instanceof Model) {
                $params[$key] = $value->getRouteKey();
            }
        }

        if ($routeName === 'blog.show' && isset($params['slug'])) {
            $post = BlogPost::where('slug_ro', $params['slug'])->orWhere('slug_hu', $params['slug'])->first();
            if ($post) {
                $params['slug'] = $post->slugFor($target);
            }
        }

        return $params;
    }
}
