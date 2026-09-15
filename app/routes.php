<?php

declare(strict_types=1);

use Arris\AppRouter as R;

/**
 * Декларация маршрутов приложения.
 *
 * Контроллеры с конструктором регистрируются как инстансы через R::addHandler()
 * до объявления маршрутов.
 */

return static function (): void {
    $app = \App\App::getInstance();
    $logger = $app->logger('app');

    $jsonPresenter = new \App\Presenters\JsonPresenter();
    $pagePresenter = new \App\Presenters\TemplatePresenter($app->template());

    R::init($logger);

    R::addHandler(\App\Controllers\HealthController::class, new \App\Controllers\HealthController($app, $logger, $jsonPresenter));
    R::addHandler(\App\Controllers\PageController::class, new \App\Controllers\PageController($app, $logger, $pagePresenter));

    // Health check
    R::get('/health', [\App\Controllers\HealthController::class, 'health'], 'health');

    // Публичные страницы
    R::get('/', [\App\Controllers\PageController::class, 'home'], 'home');
    R::get('/tracks', [\App\Controllers\PageController::class, 'tracks'], 'tracks');
    R::get('/users/{id:\d+}', [\App\Controllers\PageController::class, 'user'], 'user');

    // TODO: остальные маршруты из раздела 8 ТЗ (по мере выполнения задач ROADMAP).
};