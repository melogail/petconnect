<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreatePipelineClass extends Command
{
    protected $signature = 'make:pipeline {name} {--http : Use HTTP-specific template} {--query : Use Query Builder template}';

    protected $description = 'Create a new pipeline class';

    public function handle(): int
    {
        $directory = app_path('Pipelines');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $name = Str::studly($this->argument('name'));
        $filePath = $directory.'/'.$name.'Pipeline.php';

        if (File::exists($filePath)) {
            $this->error('Pipeline class already exists!');

            return 1;
        }

        File::put($filePath, $this->generatePipelineClass($name));

        $this->info('Pipeline class ['.$name.'Pipeline] created successfully!');

        return 0;
    }

    private function generatePipelineClass(string $name): string
    {
        $template = match (true) {
            $this->option('query') => $this->getQueryTemplate(),
            $this->option('http') => $this->getHttpTemplate(),
            default => $this->getGenericTemplate(),
        };

        return str_replace('{{ name }}', $name, $template);
    }

    private function getGenericTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Pipelines;

use Closure;

class {{ name }}Pipeline
{
    public function handle($passable, Closure $next)
    {
        // TODO: Implement handle method.
        
        return $next($passable);
    }
}
PHP;
    }

    private function getHttpTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Pipelines;

use Closure;
use Illuminate\Http\Request;

class {{ name }}Pipeline
{
    public function handle(Request $request, Closure $next)
    {
        // TODO: Implement handle method.

        return $next($request);
    }
}
PHP;
    }

    private function getQueryTemplate(): string
    {
        return <<<'PHP'
<?php

namespace App\Pipelines;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class {{ name }}Pipeline
{
    public function handle(Builder $query, Closure $next)
    {
        // TODO: Implement handle method.
        
        return $next($query);
    }
}
PHP;
    }
}
