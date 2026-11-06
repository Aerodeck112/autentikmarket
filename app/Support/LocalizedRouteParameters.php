<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Model;

/**
 * Traduce parametrii de rută între limbi acolo unde slug-ul diferă pe limbă.
 */
class LocalizedRouteParameters
{
    /** @param  array<string, string>  $bindingFields  câmpul de binding al fiecărui parametru (ex. {producer:slug} → slug) */
    public static function translate(string $routeName, array $params, string $target, array $bindingFields = []): array
    {
        foreach ($params as $key => $value) {
            if ($value instanceof Model) {
                // {producer:slug} → slug-ul, nu id-ul (altfel /hu/termelok/1 → 404)
                $field = $bindingFields[$key] ?? null;
                $params[$key] = $field ? $value->getAttribute($field) : $value->getRouteKey();
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
