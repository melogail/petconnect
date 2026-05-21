<?php

namespace App\Pipelines;

use Closure;

class FilterBadWordsPipeline
{
    private array $badWords;

    public function __construct()
    {
        $this->badWords = config('bad-words');
    }

    public function handle($passable, Closure $next)
    {

        if (empty($passable)) {
            return $next($passable);
        }

        $passable = str_ireplace($this->badWords, '****', $passable);

        return $next($passable);
    }
}
