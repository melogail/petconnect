<?php

namespace App\Traits;

use Illuminate\Pipeline\Pipeline;

trait HasPipeline
{
    public function pipeline($passable, array $pipelines)
    {
        return app(Pipeline::class)
            ->send($passable)
            ->through($pipelines)
            ->thenReturn();
    }
}
