<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Amp\Http\Server\Response as AmphpResponse;

/**
 * Serves static files out of the project's public/ directory, mirroring what a
 * production web server (nginx/apache) would do before handing dynamic requests
 * to index.php. Without this, TYPO3-emitted asset URLs (/_assets/..., /fileadmin/...,
 * /typo3temp/...) would be dispatched to Application::handle() and return 404s.
 */
final class StaticFileServer
{
    private const array MIME = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'mjs'   => 'application/javascript',
        'map'   => 'application/json',
        'json'  => 'application/json',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'otf'   => 'font/otf',
        'html'  => 'text/html; charset=utf-8',
        'htm'   => 'text/html; charset=utf-8',
        'txt'   => 'text/plain; charset=utf-8',
        'xml'   => 'application/xml',
        'pdf'   => 'application/pdf',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
    ];

    public function __construct(private readonly string $publicRoot)
    {
    }

    public function tryServe(string $path): ?AmphpResponse
    {
        // Reject anything ending in .php so we never read PHP source as bytes.
        if (str_ends_with(strtolower($path), '.php')) {
            return null;
        }

        $relative = ltrim($path, '/');
        if ($relative === '') {
            return null;
        }

        // Path-traversal guard: reject any '..' or empty segments. We deliberately
        // do NOT use realpath() to validate the location, because TYPO3 publishes
        // extension assets via symlinks from public/_assets/ into vendor/, so a
        // legitimate request resolves to a path outside the public root.
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '') {
                return null;
            }
        }

        $candidate = $this->publicRoot . '/' . $relative;
        if (!is_file($candidate)) {
            return null;
        }

        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';

        return new AmphpResponse(
            status: 200,
            headers: [
                'Content-Type'   => $mime,
                'Content-Length' => (string) filesize($candidate),
                'Cache-Control'  => 'public, max-age=0',
            ],
            body: file_get_contents($candidate),
        );
    }
}
