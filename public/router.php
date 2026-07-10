<?php

/**
 * Router for PHP's built-in server (make serve / Docker).
 * Serves existing files from public/ with correct MIME types;
 * everything else goes to Symfony's front controller.
 */
if (php_sapi_name() === 'cli-server') {
    $path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($path)) {
        return false;
    }
}

require_once __DIR__ . '/index.php';
