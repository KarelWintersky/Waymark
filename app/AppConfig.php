<?php

declare(strict_types=1);

namespace App;

/**
 * Дефолтный конфиг приложения.
 *
 * Значения по умолчанию. Пользовательский конфиг (корневой _config.yaml либо
 * переданный явно через WAYMARK_CONFIG / --config=) накладывается поверх
 * этих значений (массивы сливаются рекурсивно, null в файле — удаление ключа).
 */
final class AppConfig
{
    public static function getDefaultConfig(): array
    {
        $root = dirname(__DIR__);

        return [
            'app' => [
                'name'     => 'Waymark',
                'version'  => '0.0.1',
                'debug'    => true,
                'timezone' => 'Europe/Moscow',
            ],

            'db' => [
                'driver'   => 'mysql',
                'hostname' => 'localhost',
                'port'     => 3306,
                'database' => 'waymark',
                'username' => 'waymark',
                'password' => 'password',
                'charset'  => 'utf8mb4',
            ],

            'paths' => [
                'root'      => $root,
                'public'    => $root . '/public',
                'storage'   => $root . '/storage',
                'media'     => $root . '/storage/media',
                'tracks'    => $root . '/storage/tracks',
                'cache'     => $root . '/storage/cache',
                'logs'      => $root . '/storage/logs',
                'templates' => $root . '/templates',
            ],

            'limits' => [
                'upload_max_size_bytes' => 50 * 1024 * 1024, // 50 MB
                'upload_max_images'     => 10,
                'upload_max_videos'     => 5,
                'max_poi_per_track'     => 100,
            ],
        ];
    }
}