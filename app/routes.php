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
    R::addHandler(\App\Controllers\AuthController::class, new \App\Controllers\AuthController($app, $logger, $pagePresenter));
    R::addHandler(\App\Controllers\TrackController::class, new \App\Controllers\TrackController($app, $logger, $pagePresenter));

    // Health check
    R::get('/health', [\App\Controllers\HealthController::class, 'health'], 'health');

    // Публичные страницы
    R::get('/', [\App\Controllers\PageController::class, 'home'], 'home');
    R::get('/tracks', [\App\Controllers\PageController::class, 'tracks'], 'tracks');
    R::get('/tracks/{id:\d+}', [\App\Controllers\PageController::class, 'view'], 'track_view');
    R::get('/users/{id:\d+}', [\App\Controllers\PageController::class, 'user'], 'user');

    // Авторизация
    R::get('/login', [\App\Controllers\AuthController::class, 'login'], 'login');
    R::post('/login', [\App\Controllers\AuthController::class, 'login']);
    R::post('/logout', [\App\Controllers\AuthController::class, 'logout'], 'logout');
    R::get('/register', [\App\Controllers\AuthController::class, 'register'], 'register');
    R::get('/password/forgot', [\App\Controllers\AuthController::class, 'forgotPassword'], 'password_forgot');

// Треки (CRUD, только авторизованные)
    R::get('/my/tracks', [\App\Controllers\TrackController::class, 'myTracks'], 'my_tracks');
    R::get('/tracks/create', [\App\Controllers\TrackController::class, 'create'], 'track_create');
    R::post('/tracks/create', [\App\Controllers\TrackController::class, 'create']);
    R::get('/tracks/create/manual', [\App\Controllers\TrackController::class, 'createManual'], 'track_create_manual');
    R::post('/tracks/create/manual', [\App\Controllers\TrackController::class, 'createManual']);
    R::get('/tracks/{id:\d+}/edit', [\App\Controllers\TrackController::class, 'edit'], 'track_edit');
    R::post('/tracks/{id:\d+}/edit', [\App\Controllers\TrackController::class, 'edit']);
    R::post('/tracks/{id:\d+}/delete', [\App\Controllers\TrackController::class, 'delete'], 'track_delete');

    // Медиа: страница управления фотографиями трека (GET — таблица, POST — мультизагрузка)
    R::get('/tracks/{id:\d+}/media', [\App\Controllers\TrackController::class, 'media'], 'track_media');
    R::post('/tracks/{id:\d+}/media', [\App\Controllers\TrackController::class, 'media']);
    R::post('/tracks/{id:\d+}/media/{mediaId:\d+}/description', [\App\Controllers\TrackController::class, 'updateMediaDescription'], 'track_media_description');
    R::post('/tracks/{id:\d+}/media/{mediaId:\d+}/delete', [\App\Controllers\TrackController::class, 'deleteMedia'], 'track_media_delete');

    // Публикация (задачи 12-13)
    R::post('/tracks/{id:\d+}/share', [\App\Controllers\TrackController::class, 'share'], 'track_share');
    R::post('/tracks/{id:\d+}/publish', [\App\Controllers\TrackController::class, 'publish'], 'track_publish');
    R::post('/tracks/{id:\d+}/unpublish', [\App\Controllers\TrackController::class, 'unpublish'], 'track_unpublish');

    // Доступ к скрытому треку по ссылке (без авторизации)
    R::get('/shared/{token}', [\App\Controllers\PageController::class, 'shared'], 'shared');

    // TODO: остальные маршруты из раздела 8 ТЗ (по мере выполнения задач ROADMAP).
};