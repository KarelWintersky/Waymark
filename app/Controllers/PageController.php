<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Units\Track;
use App\Units\TrackLinks;
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
     * «по ссылке» для чужих — 404 (по ссылке — только /shared/{token}).
     */
    public function view(int $id): void
    {
        $track = (new Track($this->pdo))->findWithUser($id);
        $this->requireTrackOrNotFound($track);

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

        $this->renderTrackPage($track);
    }

    /**
     * Доступ к треку по ссылке без авторизации: /shared/{token}.
     */
    public function shared(string $token): void
    {
        $link = (new TrackLinks($this->pdo))->findValid($token);

        if ($link === null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Ссылка недействительна',
            ], 404);

            return;
        }

        $track = (new Track($this->pdo))->findWithUser((int)$link['track_id']);
        $this->requireTrackOrNotFound($track);

        $this->renderTrackPage($track);
    }

    /**
     * Отдаёт страницу 404, если трек (с автором) не найден.
     */
    private function requireTrackOrNotFound(?array $track): void
    {
        if ($track === null || $track['user_deleted'] !== null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            exit;
        }
    }

    /**
     * Рендер страницы трека с картой (общий для владельца, публичного и по ссылке).
     */
    private function renderTrackPage(array $track): void
    {
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

        $stats = self::geometryStats($geometry);

        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        $this->presenter->present([
            'template'         => 'tracks/view.tpl',
            'title'            => $track['title'] . ' — Waymark',
            'map'              => true,
            'track'            => $track,
            'visibility_label' => self::VISIBILITY_LABELS[$track['visibility']] ?? (string)$track['visibility'],
            'has_map'          => count($geometry) > 1,
            'points_count'     => count($geometry),
            'stats_distance'   => $stats['distance_km'] !== null
                ? str_replace('.', ',', sprintf('%.2f', $stats['distance_km']))
                : null,
            'stats_duration'   => $stats['duration_seconds'] !== null ? self::durationHms($stats['duration_seconds']) : null,
            'geometry_json'    => json_encode($geometry, $jsonFlags),
            'bbox_json'        => json_encode($bbox, $jsonFlags),
            'default_lat'      => (float)$this->app->fromConfig('default.lat', 59.93863),
            'default_lon'      => (float)$this->app->fromConfig('default.lon', 30.314113),
            'default_zoom'     => (int)$this->app->fromConfig('default.zoom', 11),
        ]);
    }

    /**
     * Длина маршрута (гаверсинус, км) и длительность по времени засечек.
     */
    private static function geometryStats(array $geometry): array
    {
        $count = count($geometry);

        $distanceKm = 0.0;
        if ($count > 1) {
            for ($i = 1; $i < $count; $i++) {
                $a = $geometry[$i - 1];
                $b = $geometry[$i];
                if (!isset($a['lat'], $a['lng'], $b['lat'], $b['lng'])) {
                    continue;
                }
                $distanceKm += self::haversineKm((float)$a['lat'], (float)$a['lng'], (float)$b['lat'], (float)$b['lng']);
            }
        }

        $durationSeconds = null;
        $first = $geometry[0] ?? null;
        $last  = $geometry[$count - 1] ?? null;
        $t1 = isset($first['time']) ? strtotime((string)$first['time']) : false;
        $t2 = isset($last['time']) ? strtotime((string)$last['time']) : false;

        if ($t1 !== false && $t2 !== false && $t2 >= $t1) {
            $durationSeconds = $t2 - $t1;
        }

        return [
            'distance_km'      => $count > 1 ? round($distanceKm, 2) : null,
            'duration_seconds' => $durationSeconds,
        ];
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radius = 6371.0;
        $toRad = M_PI / 180;

        $dLat = ($lat2 - $lat1) * $toRad;
        $dLng = ($lng2 - $lng1) * $toRad;

        $a = sin($dLat / 2) ** 2
            + cos($lat1 * $toRad) * cos($lat2 * $toRad) * sin($dLng / 2) ** 2;

        return 2 * $radius * asin(sqrt($a));
    }

    private static function durationHms(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }
}