<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$configurationKeys = [
    'memory_limit',
    'max_execution_time',
    'max_input_time',
    'post_max_size',
    'upload_max_filesize',
    'session.serialize_handler',
    'session.upload_progress.enabled',
    'fatal_error_backtraces',
    'expose_php',
    'display_errors',
    'log_errors',
];

$configuration = [];
foreach ($configurationKeys as $key) {
    $configuration[$key] = ini_get($key);
}

$info = [
    'expected_version' => getenv('LAB_RUNTIME_EXPECTED_VERSION') ?: null,
    'php_version' => PHP_VERSION,
    'php_version_id' => PHP_VERSION_ID,
    'sapi' => PHP_SAPI,
    'zend_version' => zend_version(),
    'build' => [
        'os_family' => PHP_OS_FAMILY,
        'architecture' => php_uname('m'),
        'zts' => PHP_ZTS,
        'debug' => PHP_DEBUG,
    ],
    'apache_version' => function_exists('apache_get_version') ? apache_get_version() : null,
    'loaded_extensions' => get_loaded_extensions(),
    'configuration' => $configuration,
    'container_started_at' => @file_get_contents('/tmp/php2shell-started-at') ?: null,
    'http_health' => 'ok',
];

echo json_encode($info, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
