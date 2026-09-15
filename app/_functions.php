<?php

declare(strict_types=1);

/**
 * Глобальные функции приложения Waymark.
 *
 * Файл подключается через composer.json «autoload.files» и загружается
 * вместе с vendor/autoload.php до старта приложения.
 */

if (!function_exists('waymark')) {
    function waymark(?string $key = null, mixed $default = null): mixed
    {
        return \App\App::config($key, $default);
    }
}