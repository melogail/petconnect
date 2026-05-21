<?php

namespace App\Pipelines;

use Closure;

class TrimContentPipeline
{
    public function handle($passable, Closure $next)
    {
        if (empty($passable)) {
            return $next($passable);
        }

        if (is_string($passable)) {
            $passable = trim($passable);
        }

        return $next($passable);
    }
}
