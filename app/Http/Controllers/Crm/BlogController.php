<?php

namespace App\Http\Controllers\Crm;

use App\Enums\BlogStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Event;
use App\Services\AuditLogger;
use App\Services\Blog\BlogDraftGenerator;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Blog: DRAFT → REVIEW → PUBLISHED. Nimic generat automat nu se publică singur. */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        return view('crm.blog.index', [
            'posts' => BlogPost::with(['event', 'author'])->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25),
            'events' => Event::where('status', 'completed')->doesntHave('blogPosts')->orderByDesc('event_date')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        return view('crm.blog.form', ['post' => new BlogPost, 'events' => Event::orderByDesc('event_date')->limit(30)->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $post = new BlogPost($data);
        $post->author_id = $request->user()->id;
        $post->save();

        return redirect()->route('crm.blog.edit', $post)->with('success', __('Articolul a fost salvat ca ciornă.'));
    }

    public function edit(BlogPost $post): View
    {
        return view('crm.blog.form', ['post' => $post, 'events' => Event::orderByDesc('event_date')->limit(30)->get()]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validated($request, $post);
        $post->update($data);
        Cache::forget('sitemap.xml');

        return back()->with('success', __('Articolul a fost salvat.'));
    }

    public function status(Request $request, BlogPost $post): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::enum(BlogStatus::class)]]);
        $to = BlogStatus::from($request->input('status'));
        $old = $post->status;
        $post->forceFill([
            'status' => $to,
            'published_at' => $to === BlogStatus::Published ? ($post->published_at ?? now()) : $post->published_at,
        ])->save();
        AuditLogger::log('blog.status_changed', $post, ['status' => $old], ['status' => $to]);
        Cache::forget('sitemap.xml');

        return back()->with('success', __('Statusul articolului: :s', ['s' => $to->label()]));
    }

    public function fromEvent(Event $event, BlogDraftGenerator $generator): RedirectResponse
    {
        $post = $generator->generate($event);

        return redirect()->route('crm.blog.edit', $post)->with('success', __('Draft generat din datele evenimentului. Revizuiește-l înainte de publicare.'));
    }

    protected function validated(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title_ro' => ['required', 'string', 'max:255'],
            'title_hu' => ['required', 'string', 'max:255'],
            'slug_ro' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('blog_posts', 'slug_ro')->ignore($post?->id)],
            'slug_hu' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('blog_posts', 'slug_hu')->ignore($post?->id)],
            'excerpt_ro' => ['nullable', 'string', 'max:1000'],
            'excerpt_hu' => ['nullable', 'string', 'max:1000'],
            'body_ro' => ['nullable', 'string', 'max:100000'],
            'body_hu' => ['nullable', 'string', 'max:100000'],
            'meta_title_ro' => ['nullable', 'string', 'max:255'],
            'meta_title_hu' => ['nullable', 'string', 'max:255'],
            'meta_description_ro' => ['nullable', 'string', 'max:255'],
            'meta_description_hu' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'exists:events,id'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('autentik.uploads.image_max_kb')],
        ]);
        if ($request->hasFile('cover')) {
            if ($post?->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $data['cover_path'] = Uploads::storeImage($request->file('cover'), 'blog');
        }
        unset($data['cover']);
        foreach (['slug_ro', 'slug_hu'] as $s) {
            if (blank($data[$s] ?? null)) {
                unset($data[$s]);
            }
        }

        return $data;
    }
}
