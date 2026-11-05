<?php

declare(strict_types=1);

namespace Vanda\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the prebuilt renderer, so applications need no Node.js toolchain.
 */
final class AssetController
{
    private const TYPES = [
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'map' => 'application/json',
        'woff2' => 'font/woff2',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        $directory = self::directory();
        $path = realpath($directory.'/'.$file);

        abort_if($directory === '' || $path === false || ! is_file($path) || ! str_starts_with($path, $directory.'/'), 404);

        return response()->file($path, [
            'Content-Type' => self::TYPES[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public static function directory(): string
    {
        return (string) realpath(__DIR__.'/../../resources/dist');
    }
}
