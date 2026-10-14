<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\Event;

/**
 * Generator de draft post-eveniment. Implementarea implicită este bazată pe template
 * (funcționează fără AI). Un generator AI poate fi legat în container ulterior.
 */
interface BlogDraftGenerator
{
    public function generate(Event $event): BlogPost;
}
