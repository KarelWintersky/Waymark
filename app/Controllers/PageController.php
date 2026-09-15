<?php

declare(strict_types=1);

namespace App\Controllers;

use Arris\Controllers\AbstractController;
use PDO;

/**
 * Публичные страницы: главная, список публичных треков, профиль пользователя.
 */
final class PageController extends AbstractController
{
    private function publicTracks(?int $userId = null): array
    {
        $sql = "SELECT t.id, t.title, t.description, t.date_recorded, t.created_at,
                       u.id AS user_id, u.username
                  FROM tracks t
                  JOIN users u ON u.id = t.user_id
                 WHERE t.visibility = 'public' AND t.deleted_at IS NULL AND u.deleted_at IS NULL";

        $params = [];

        if ($userId !== null) {
            $sql .= " AND t.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " ORDER BY t.created_at DESC, t.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function home(): void
    {
        $this->presenter->present([
            'template' => 'home.tpl',
            'title'    => 'Waymark — фото-воспоминания о путешествиях',
            'tracks'   => $this->publicTracks(),
        ]);
    }

    public function tracks(): void
    {
        $this->presenter->present([
            'template' => 'tracks.tpl',
            'title'    => 'Публичные треки — Waymark',
            'tracks'   => $this->publicTracks(),
        ]);
    }

    public function user(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id, username, created_at FROM users WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            return;
        }

        $this->presenter->present([
            'template' => 'user.tpl',
            'title'    => $user['username'] . ' — Waymark',
            'user'     => $user,
            'tracks'   => $this->publicTracks((int)$user['id']),
        ]);
    }
}