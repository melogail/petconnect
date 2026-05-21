<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CreateServiceClass extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        Artisan::call('make:class', [
            'name' => 'Services/'.$name.'Service',
            '--no-interaction' => true,
        ]);
        $this->info('Service class '.$name.' created successfully!');
    }
}
