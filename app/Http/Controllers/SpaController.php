<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpaController extends Controller
{
    public function serve(?string $path = null): BinaryFileResponse
    {
        $path = $path ?? '';
        $publicPath = public_path('app/'.ltrim($path, '/'));

        if ($path !== '' && is_file($publicPath)) {
            return response()->file($publicPath);
        }

        $index = public_path('app/index.html');
        if (! is_file($index)) {
            abort(503, 'Frontend no desplegado. Ejecuta: cd started-kit && npm run build:laravel && scripts/deploy-to-idenna.sh');
        }

        return response()->file($index);
    }
}
