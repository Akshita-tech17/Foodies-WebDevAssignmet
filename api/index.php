<?php
// Vercel Serverless Entry Point
chdir(__DIR__ . '/..');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

$filePath = __DIR__ . '/..' . $uri;

if (file_exists($filePath) && is_file($filePath)) {
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        require $filePath;
        exit;
    }
}

// Default to index.php
require __DIR__ . '/../index.php';
