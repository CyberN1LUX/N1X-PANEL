<?php

return [
    /*
    |--------------------------------------------------------------------------
    | N1X PANEL Configuration
    |--------------------------------------------------------------------------
    |
    | Global hosting, process manager, reverse proxy, and port management
    | configurations for native Linux multi-language application execution.
    |
    */

    'panel' => [
        'name' => env('APP_NAME', 'N1LUX PETRO'),
        'port' => (int) env('PANEL_PORT', 8080),
        'host' => env('PANEL_HOST', '0.0.0.0'),
        'timezone' => env('PANEL_TIMEZONE', 'Asia/Kolkata'),
    ],

    'applications' => [
        'root' => env('APPLICATIONS_ROOT', '/var/lib/n1x/applications'),
        'default_port' => (int) env('APPLICATION_DEFAULT_PORT', 5000),
        'port_range_start' => (int) env('APPLICATION_PORT_RANGE_START', 5000),
        'port_range_end' => (int) env('APPLICATION_PORT_RANGE_END', 9000),
        'default_runtime' => env('APPLICATION_DEFAULT_RUNTIME', 'node'),
        'max_count' => (int) env('APPLICATION_MAX_COUNT', 20),
    ],

    'process' => [
        'manager' => env('PROCESS_MANAGER', 'systemd'),
        'node_manager' => env('NODE_PROCESS_MANAGER', 'pm2'),
    ],

    'nginx' => [
        'enabled' => filter_var(env('NGINX_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'config_path' => env('NGINX_CONFIG_PATH', '/etc/nginx/conf.d'),
    ],

    'security' => [
        'user_prefix' => env('APPLICATION_USER_PREFIX', 'n1x-app'),
        'run_as_unprivileged' => filter_var(env('APPLICATION_RUN_AS_UNPRIVILEGED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'logging' => [
        'path' => env('APPLICATION_LOG_PATH', '/var/log/n1x'),
        'level' => env('LOG_LEVEL', 'info'),
    ],
];

