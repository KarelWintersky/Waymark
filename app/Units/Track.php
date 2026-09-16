<?php

declare(strict_types=1);

namespace App\Units;

use PDO;

/**
 * Трек: работа с записями таблицы `tracks` (мягкое удаление, проверка владельца).
 *
 * Геометрия не парсится на этом этапе — в `geometry` кладётся пустой JSON `[]`.
 */
final class Track
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId, array $data): int
    {
        $geometry = $data['geometry'] ?? [];
        $bbox = $data['bbox'] ?? null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO tracks (user_id, title, description, source, date_recorded,
                                 geometry, bbox_min_lat, bbox_max_lat, bbox_min_lng, bbox_max_lng, visibility)
             VALUES (:user_id, :title, :description, :source, :date_recorded,
                     :geometry, :min_lat, :max_lat, :min_lng, :max_lng, :visibility)'
        );

        $stmt->execute([
            'user_id'       => $userId,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? '',
            'source'        => $data['source'],
            'date_recorded' => $data['date_recorded'] ?? null,
            'geometry'      => json_encode($geometry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'min_lat'       => $bbox[0] ?? null,
            'max_lat'       => $bbox[1] ?? null,
            'min_lng'       => $bbox[2] ?? null,
            'max_lng'       => $bbox[3] ?? null,
            'visibility'    => $data['visibility'] ?? 'private',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Импорт данных из файла: геометрия, bbox; дата заполняется только если пустая
     * (режим «взять из файла»), описание — только если пустое.
     */
    public function import(int $id, int $userId, array $data): bool
    {
        $geometry = $data['geometry'] ?? [];
        $bbox = $data['bbox'] ?? null;

        $stmt = $this->pdo->prepare(
            'UPDATE tracks
                SET geometry       = :geometry,
                    bbox_min_lat   = :min_lat,
                    bbox_max_lat   = :max_lat,
                    bbox_min_lng   = :min_lng,
                    bbox_max_lng   = :max_lng,
                    date_recorded  = COALESCE(:date_recorded, date_recorded),
                    description    = CASE WHEN :description <> \'\' THEN :description ELSE description END,
                    updated_at     = CURRENT_TIMESTAMP
              WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'geometry'      => json_encode($geometry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'min_lat'       => $bbox[0] ?? null,
            'max_lat'       => $bbox[1] ?? null,
            'min_lng'       => $bbox[2] ?? null,
            'max_lng'       => $bbox[3] ?? null,
            'date_recorded' => $data['date_recorded'] ?? null,
            'description'   => (string)($data['description'] ?? ''),
            'id'            => $id,
            'user_id'       => $userId,
        ]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tracks WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Трек с проверкой владельца (для страниц редактирования/удаления).
     */
    public function findMine(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tracks WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tracks
                SET title        = :title,
                    description  = :description,
                    source       = :source,
                    date_recorded = :date_recorded
              WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            'title'         => $data['title'],
            'description'   => $data['description'] ?? '',
            'source'        => $data['source'],
            'date_recorded' => $data['date_recorded'] ?? null,
            'id'            => $id,
            'user_id'       => $userId,
        ]);
    }

    public function softDelete(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tracks
                SET deleted_at = CURRENT_TIMESTAMP
              WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );

        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    /**
     * Список треков пользователя (без мягко-удалённых), новые первыми.
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tracks
              WHERE user_id = :user_id AND deleted_at IS NULL
              ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}