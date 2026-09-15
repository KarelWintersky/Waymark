<?php

declare(strict_types=1);

namespace App\Controllers;

use Arris\Controllers\AbstractController;

/**
 * Контроллер проверки работоспособности приложения.
 */
final class HealthController extends AbstractController
{
    public function health(): void
    {
        $db = false;

        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
            $db = true;
        } catch (\Throwable $e) {
            $this->logger->error('DB health check failed', ['error' => $e->getMessage()]);
        }

        $this->success([
            'service' => (string)$this->config['app.name'],
            'version' => (string)$this->config['app.version'],
            'db'      => $db,
            'time'    => date('c'),
        ], 'OK');
    }
}