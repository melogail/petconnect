<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(app_path('Pipelines/Testing'));
});

test('it generates a pipeline class in the pipelines namespace', function () {
    $this->artisan('make:pipeline', ['name' => 'Testing/ChargeCustomer'])
        ->assertSuccessful();

    $path = app_path('Pipelines/Testing/ChargeCustomer.php');

    expect($path)->toBeReadableFile();

    $contents = File::get($path);

    expect($contents)
        ->toContain('namespace App\Pipelines\Testing;')
        ->toContain('class ChargeCustomer')
        ->toContain('public function handle(mixed $passable, Closure $next): mixed');
});

test('it refuses to overwrite an existing pipeline without the force option', function () {
    $this->artisan('make:pipeline', ['name' => 'Testing/ChargeCustomer'])->assertSuccessful();

    $this->artisan('make:pipeline', ['name' => 'Testing/ChargeCustomer'])
        ->expectsOutputToContain('already exists');

    $this->artisan('make:pipeline', ['name' => 'Testing/ChargeCustomer', '--force' => true])
        ->assertSuccessful();
});
