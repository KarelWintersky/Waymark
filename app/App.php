<?php

declare(strict_types=1);

namespace App;

use Arris\AppLogger;
use Arris\Database\Config;
use Arris\Database\Connector;
use Arris\DelightAuth\Auth\Auth;
use Arris\Presenter\Template;
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

    private ?Template $template = null;

    private ?Auth $auth = null;

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
            $config = new Config();
            $config
                ->setHost(App::fromConfig('db.hostname'))
                ->setDatabase(App::fromConfig('db.database'))
                ->setUsername(App::fromConfig('db.username'))
                ->setPassword(App::fromConfig('db.password'));

            $this->pdo = new Connector($config);
        }

        return $this->pdo;
    }

    /**
     * Шаблонизатор (Smarty через Arris\Presenter\Template), ленивая инициализация.
     */
    public function template(): Template
    {
        if ($this->template === null) {
            Template::setDefaults([
                'setTemplateDir'   => (string)$this->fromConfig('paths.templates'),
                'setCompileDir'    => (string)$this->fromConfig('paths.cache') . '/smarty',
                'setForceCompile'  => (bool)$this->fromConfig('app.debug', false),
            ]);

            $this->template = Template::make([], $this->logger('app'));
        }

        return $this->template;
    }

    /**
     * Авторизация (Arris\DelightAuth\Auth), ленивая инициализация.
     * Троттлинг отключается в debug-режиме (удобно для разработки).
     */
    public function auth(): Auth
    {
        if ($this->auth === null) {
            $this->auth = new Auth(
                databaseConnection: $this->pdo(),
                dbTablePrefix: '',
                throttling: (bool)!$this->fromConfig('app.debug', false),
            );
        }

        return $this->auth;
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