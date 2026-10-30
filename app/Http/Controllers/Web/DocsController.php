<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\Mime\MimeTypes;

/**
 * Serves the static VitePress build from outside public/, so the files only
 * reach a request that has already passed the admin guard on the route.
 *
 * The Content-Type comes from the extension, not from sniffing the bytes:
 * finfo reports a JS module as text/plain and browsers then refuse to run it.
 */
class DocsController extends Controller
{
    public function __invoke(string $path = ''): Response
    {
        $root = realpath(config('docs.path'));

        abort_if($root === false, 404, 'The documentation has not been built. Run `npm run docs:build`.');

        $path = trim($path, '/');

        foreach ([$path, "{$path}.html", "{$path}/index.html", '404.html'] as $candidate) {
            $file = realpath($root.'/'.$candidate);

            if ($file !== false && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
                $mime = MimeTypes::getDefault()->getMimeTypes(pathinfo($file, PATHINFO_EXTENSION))[0] ?? 'application/octet-stream';

                return response(File::get($file), $candidate === '404.html' ? 404 : 200, [
                    'Content-Type'  => $mime,
                    'Cache-Control' => 'private, no-store',
                ]);
            }
        }

        abort(404);
    }
}
