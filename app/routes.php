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
    $presenter = new \App\Presenters\JsonPresenter();

    R::init($logger);

    R::addHandler(\App\Controllers\HealthController::class, new \App\Controllers\HealthController($app, $logger, $presenter));

    // Health check
    R::get('/health', [\App\Controllers\HealthController::class, 'health'], 'health');

    // TODO: остальные маршруты из раздела 8 ТЗ (по мере выполнения задач ROADMAP).
};