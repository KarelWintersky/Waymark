<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Простейший JSON-презентер для API-контроллеров.
 */
final class JsonPresenter
{
    public function present(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}