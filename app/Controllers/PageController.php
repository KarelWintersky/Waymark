<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Units\Track;
use Arris\Controllers\AbstractController;
use PDO;

/**
 * Публичные страницы: главная, список публичных треков, профиль пользователя, страница трека.
 */
final class PageController extends AbstractController
{
    private const VISIBILITY_LABELS = [
        'private'   => 'Приватный',
        'protected' => 'По ссылке',
        'public'    => 'Публичный',
    ];
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

    /**
     * Страница трека с картой Leaflet.
     *
     * Виден: владелец (любая видимость) и все (только public). Приватные и
     * «по ссылке» для чужих — 404 (по ссылке — задача 13).
     */
    public function view(int $id): void
    {
        $track = (new Track($this->pdo))->findWithUser($id);

        if ($track === null || $track['user_deleted'] !== null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            return;
        }

        $auth = $this->app->auth();
        $currentUserId = $auth->isLoggedIn() ? (int)$auth->getUserId() : null;
        $isOwner = $currentUserId !== null && (int)$track['user_id'] === $currentUserId;

        if (!$isOwner && $track['visibility'] !== 'public') {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            return;
        }

        $geometry = json_decode((string)($track['geometry'] ?? '[]'), true);
        if (!is_array($geometry)) {
            $geometry = [];
        }

        $bbox = array_map(
            static fn (?string $v): ?float => $v !== null ? (float)$v : null,
            [
                $track['bbox_min_lat'],
                $track['bbox_max_lat'],
                $track['bbox_min_lng'],
                $track['bbox_max_lng'],
            ]
        );

        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        $this->presenter->present([
            'template'          => 'tracks/view.tpl',
            'title'             => $track['title'] . ' — Waymark',
            'map'               => true,
            'track'             => $track,
            'visibility_label'  => self::VISIBILITY_LABELS[$track['visibility']] ?? (string)$track['visibility'],
            'has_map'           => count($geometry) > 1,
            'points_count'      => count($geometry),
            'geometry_json'     => json_encode($geometry, $jsonFlags),
            'bbox_json'         => json_encode($bbox, $jsonFlags),
        ]);
    }
}