<?php

require_once __DIR__ . '/serve-static.php';

if (symfony101_serve_static_file()) {
    return;
}

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
