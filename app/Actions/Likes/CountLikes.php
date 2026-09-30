<?php

namespace App\Actions\Likes;

use App\Contracts\Likeable;

/**
 * The like count a toggle response reports back.
 *
 * The web client redirects back and re-reads the page after a like; a mobile
 * client keeps the screen and needs the new count in the toggle's own
 * response, so ToggleLike's boolean is paired with this.
 */
class CountLikes
{
    public function handle(Likeable $likeable): int
    {
        return $likeable->likes()->count();
    }
}
