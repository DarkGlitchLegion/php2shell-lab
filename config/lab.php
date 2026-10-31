<?php

return [
    'os_execution_enabled' => (bool) env('LAB_OS_EXECUTION_ENABLED', false),
    'session_lab_enabled' => (bool) env('LAB_SESSION_LAB_ENABLED', false),
    'runtimes' => [
        'vulnerable' => [
            'label' => 'PHP 8.5.11 · vulnerable',
            'expected_version' => '8.5.11',
            'health_url' => env('PHP2SHELL_VULNERABLE_URL'),
            'terminal_url' => env('PHP2SHELL_VULNERABLE_TERMINAL_URL', 'http://127.0.0.1:7681'),
            'container_name' => 'php2shell-vulnerable',
        ],
        'patched' => [
            'label' => 'PHP 8.5.12 · patched',
            'expected_version' => '8.5.12',
            'health_url' => env('PHP2SHELL_PATCHED_URL'),
            'terminal_url' => env('PHP2SHELL_PATCHED_TERMINAL_URL', 'http://127.0.0.1:7682'),
            'container_name' => 'php2shell-patched',
        ],
    ],
];
