<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CreateRepositoryClass extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $path = 'Repositories/Eloquent/'.$name.'Repository';

        Artisan::call('make:class', [
            'name' => $path,
            '--no-interaction' => true,
            '--force' => true,
        ]);

        $this->info('Repository class '.$name.' created successfully!');
    }
}
