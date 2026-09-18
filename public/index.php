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

// Статические файлы public/: раздача напрямую (в проде — nginx `try_files $uri`,
// здесь — эмуляция для встроенного dev-сервера `php -S`). Файлы storage/media/
// (симлинк public/storage → ../storage) тоже отдаются — аналог X-Accel в dev.
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uriPath !== '/') {
    $real = realpath(__DIR__ . $uriPath);

    $allowedRoots = [__DIR__ . '/'];
    $mediaRoot = realpath(__DIR__ . '/storage/media');
    if ($mediaRoot !== false) {
        $allowedRoots[] = $mediaRoot . '/';
    }

    $inAllowedRoot = false;
    foreach ($allowedRoots as $root) {
        if ($real !== false && str_starts_with($real . '/', $root)) {
            $inAllowedRoot = true;
            break;
        }
    }

    if ($inAllowedRoot && is_file($real)) {
        $mime = match (strtolower((string)pathinfo($real, PATHINFO_EXTENSION))) {
            'css', 'css.map'   => 'text/css; charset=utf-8',
            'js', 'mjs'        => 'application/javascript; charset=utf-8',
            'png'              => 'image/png',
            'jpg', 'jpeg'      => 'image/jpeg',
            'gif'              => 'image/gif',
            'webp'             => 'image/webp',
            'svg'              => 'image/svg+xml',
            'ico'              => 'image/x-icon',
            'woff2'            => 'font/woff2',
            'woff'             => 'font/woff',
            'ttf'              => 'font/ttf',
            default            => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($real));
        readfile($real);
        exit;
    }
}

$app = \App\App::getInstance([\App\App::configFilePath()]);

date_default_timezone_set((string)$app->fromConfig('app.timezone', 'UTC'));

// Логгер
AppLogger::init(
    (string)$app->fromConfig('app.name', 'Waymark'),
    bin2hex(random_bytes(8)),
    [
        'default_logfile_path' => (string)$app->fromConfig('paths.logs'),
        'default_log_level'    => Logger::DEBUG,
    ]
);

AppLogger::addScope('app', [
    ['app.debug.log', Logger::DEBUG, ['enable' => (bool)$app->fromConfig('app.debug', false)]],
    ['app.error.log', Logger::ERROR, ['enable' => true]],
]);

$app->logger('app')->info('Request started', ['uri' => $_SERVER['REQUEST_URI'] ?? '/']);

// Помощник: HTML-страница ошибки
$pagePresenter = new \App\Presenters\TemplatePresenter($app->template());

// Текущий пользователь для шапки (сессии delight-auth)
$auth = $app->auth();
$currentUser = null;
if ($auth->isLoggedIn()) {
    $currentUser = [
        'id'       => $auth->getUserId(),
        'username' => $auth->getUsername(),
    ];
}
$pagePresenter->assign('current_user', $currentUser);

$errorPage = static function (string $template, int $status, string $title) use ($pagePresenter): void {
    $pagePresenter->present([
        'template' => $template,
        'title'    => $title,
    ], $status);
};

// Маршруты
$routes = require __DIR__ . '/../app/routes.php';
$routes();

// Диспатч
try {
    \Arris\AppRouter::dispatch();
} catch (AppRouterNotFoundException $e) {
    $app->logger('app')->warning('Route not found', ['uri' => $_SERVER['REQUEST_URI'] ?? '/']);
    ($errorPage)('errors/404.tpl', 404, 'Страница не найдена');
} catch (AppRouterMethodNotAllowedException $e) {
    ($errorPage)('errors/404.tpl', 405, 'Метод не разрешён');
} catch (Throwable $e) {
    $app->logger('app')->error('Unhandled exception', [
        'exception' => $e::class,
        'message'   => $e->getMessage(),
        'file'      => $e->getFile() . ':' . $e->getLine(),
    ]);

    ($errorPage)('errors/500.tpl', 500, 'Ошибка сервера');
}