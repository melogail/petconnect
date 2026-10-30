<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * The VitePress build is served from outside public/ on its own host, behind
 * the `admin` guard. These tests point `docs.path` at a throwaway build so they
 * neither depend on nor touch `npm run docs:build` output.
 */
beforeEach(function () {
    $this->docsRoot = sys_get_temp_dir().'/sniffpal-docs-'.uniqid();
    File::ensureDirectoryExists($this->docsRoot.'/guide');
    File::put($this->docsRoot.'/index.html', '<h1>Docs home</h1>');
    File::put($this->docsRoot.'/guide/api.html', '<h1>API</h1>');
    File::put($this->docsRoot.'/404.html', '<h1>Missing</h1>');
    config(['docs.path' => $this->docsRoot]);

    $this->docsUrl  = fn (string $path = '') => 'http://'.config('docs.domain').'/'.ltrim($path, '/');
});

afterEach(function () {
    File::deleteDirectory($this->docsRoot);
});

test('a guest is sent to the admin login and told not to index', function () {
    $this->get(($this->docsUrl)())
        ->assertRedirect(route('nova.pages.login'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('a signed-in member is still treated as a guest', function () {
    $this->actingAs(User::factory()->create())
        ->get(($this->docsUrl)())
        ->assertRedirect(route('nova.pages.login'));
});

test('an admin reads the build with clean urls and noindex headers', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(($this->docsUrl)())
        ->assertOk()
        ->assertSee('Docs home')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($admin, 'admin')
        ->get(($this->docsUrl)('guide/api'))
        ->assertOk()
        ->assertSee('API');
});

test('an unknown page answers with the build\'s 404 page', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(($this->docsUrl)('guide/nope'))
        ->assertNotFound()
        ->assertSee('Missing');
});

test('a path cannot escape the build directory', function () {
    File::put(dirname($this->docsRoot).'/sniffpal-docs-secret.txt', 'secret');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(($this->docsUrl)('../sniffpal-docs-secret.txt'))
        ->assertNotFound()
        ->assertDontSee('secret');

    File::delete(dirname($this->docsRoot).'/sniffpal-docs-secret.txt');
});

test('the admin login stays reachable on the docs host', function () {
    $this->get(($this->docsUrl)('nova/login'))
        ->assertOk();
});

test('nova\'s bare /login redirect is forwarded to the nova login page', function () {
    $this->get(($this->docsUrl)('login'))
        ->assertRedirect(route('nova.pages.login'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('the docs host does not answer on the application host', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/guide/api')
        ->assertNotFound();
});
