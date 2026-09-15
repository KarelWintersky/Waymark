<?php

declare(strict_types=1);

/**
 * Точка входа Waymark (web).
 *
 * Путь к конфигу:
 *   - WAYMARK_CONFIG (getenv) — если задан;
 *   - иначе корневой _config.yaml.
 * Крон-скрипты (admin/) используют --config=/path/to/config.yaml (см. ROADMAP, задача 1).
 */

define('__PATH_ROOT__', dirname(__DIR__));

require_once __DIR__ . '/../vendor/autoload.php';

use Arris\AppLogger;
use Arris\AppLogger\Monolog\Logger;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;

$app = \App\App::getInstance([\App\App::configFilePath()]);

date_default_timezone_set((string)$app->fromConfig('app.timezone', 'UTC'));

// Логгер
AppLogger::init(
    (string)$app->config('app.name'),
    bin2hex(random_bytes(8)),
    [
        'default_logfile_path' => (string)$app->config('paths.logs'),
        'default_log_level'    => Logger::DEBUG,
    ]
);

AppLogger::addScope('app', [
    ['app.debug.log', Logger::DEBUG, ['enable' => (bool)$app->config('app.debug')]],
    ['app.error.log', Logger::ERROR, ['enable' => true]],
]);

$app->logger('app')->info('Request started', ['uri' => $_SERVER['REQUEST_URI'] ?? '/']);

// Маршруты
$routes = require __DIR__ . '/../app/routes.php';
$routes();

// Диспатч
try {
    \Arris\AppRouter::dispatch();
} catch (AppRouterNotFoundException $e) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Not Found'], JSON_UNESCAPED_UNICODE);
    $app->logger('app')->warning('Route not found', ['uri' => $_SERVER['REQUEST_URI'] ?? '/']);
} catch (AppRouterMethodNotAllowedException $e) {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $app->logger('app')->error('Unhandled exception', [
        'exception' => $e::class,
        'message'   => $e->getMessage(),
        'file'      => $e->getFile() . ':' . $e->getLine(),
    ]);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE);
}