<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('site.blog.index', ['posts' => BlogPost::published()->latest('published_at')->paginate(12)]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();
        $column = $locale === 'hu' ? 'slug_hu' : 'slug_ro';
        $post = BlogPost::published()->where($column, $slug)->first();

        if (! $post) {
            // Slug din cealaltă limbă → redirect permanent la URL-ul corect.
            $other = BlogPost::published()->where($locale === 'hu' ? 'slug_ro' : 'slug_hu', $slug)->firstOrFail();

            return redirect(lroute('blog.show', $other->slugFor($locale)), 301);
        }

        $post->load(['event.photos', 'photos']);
        $related = BlogPost::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get();

        return view('site.blog.show', compact('post', 'related'));
    }
}
