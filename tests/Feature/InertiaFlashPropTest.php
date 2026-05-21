<?php

use Illuminate\Support\Facades\Session;

test('inertia json response includes shared flash success in props', function () {
    Session::flash('success', 'Pet created successfully');

    $version = file_exists($manifest = public_path('build/manifest.json'))
        ? hash_file('xxh128', $manifest)
        : '';

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ])->get(route('home'));

    $response->assertOk();
    $json = $response->json();

    expect($json['props']['flash']['success'] ?? null)->toBe('Pet created successfully');
});
