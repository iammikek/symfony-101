<?php

require_once __DIR__ . '/serve-static.php';

if (symfony101_serve_static_file()) {
    return true;
}

require_once __DIR__ . '/index.php';
