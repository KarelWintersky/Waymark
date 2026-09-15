<?php

declare(strict_types=1);

namespace App;

use Arris\AppLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Приложение Waymark.
 *
 * Синглтон Arris\App, конфиг строится из дефолтов (AppConfig) + файла конфига.
 * Путь к файлу конфига для web-запросов определяется через WAYMARK_CONFIG
 * (по умолчанию — корневой _config.yaml). Крон-скрипты передают путь флагом --config=.
 */
final class App extends \Arris\App
{
    public const CONFIG_FILENAME = '_config.yaml';

    private ?\PDO $pdo = null;

    protected function getDefaultConfig(): array
    {
        return AppConfig::getDefaultConfig();
    }

    /**
     * Путь к конфиг-файлу приложения.
     */
    public static function configFilePath(): string
    {
        $env = getenv('WAYMARK_CONFIG');

        return $env ?: dirname(__DIR__) . '/' . self::CONFIG_FILENAME;
    }

    /**
     * Подключение к базе данных (ленивое, PDO).
     */
    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $connection = new \Arris\Database\Config(
                $this->getConfig('db'),
                $this->logger('db')
            );

            $this->pdo = $connection->connect();
        }

        return $this->pdo;
    }

    /**
     * PSR-логгер по скоупу AppLogger; если логгер не инициализирован — NullLogger.
     */
    public function logger(string $scope = 'app'): LoggerInterface
    {
        if (!class_exists(AppLogger::class) || AppLogger::getAppLoggerConfig() === []) {
            return new NullLogger();
        }

        return AppLogger::scope($scope);
    }
}