<?php

declare(strict_types=1);

/**
 * Serve a file from public/ when using PHP's built-in server.
 * Returns true if the request was handled (caller should stop booting Symfony).
 */
if (!function_exists('symfony101_serve_static_file')) {
function symfony101_serve_static_file(): bool
{
    if (php_sapi_name() !== 'cli-server') {
        return false;
    }

    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $file = __DIR__ . rawurldecode($uriPath);

    if (!is_file($file)) {
        return false;
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
    } elseif (function_exists('mime_content_type')) {
        $detected = mime_content_type($file);
        if ($detected !== false) {
            header('Content-Type: ' . $detected);
        }
    }

    header('Content-Length: ' . (string) filesize($file));
    readfile($file);

    return true;
}
}
