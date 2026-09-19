<?php

declare(strict_types=1);

namespace App\Units;

use PDO;

/**
 * Медиа (таблица `media`): фото и видео трека.
 *
 * На этом этапе — вставка записей о загруженных фотографиях, список
 * и подсчёт по треку (мягкое удаление поддерживается колонкой deleted_at).
 */
final class TrackMedia
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Вставка записи о файле. Данные из MediaFiles::store() + description/visibility.
     */
    public function insert(int $trackId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO media
                (track_id, type, file_path, original_name, mime_type, file_size,
                 width, height,
                 latitude, longitude, direction, taken_at, description, position, visibility)
             VALUES
                (:track_id, 'photo', :file_path, :original_name, :mime_type, :file_size,
                 :width, :height,
                 :latitude, :longitude, :direction, :taken_at, :description, :position, :visibility)"
        );

        $stmt->execute([
            'track_id'      => $trackId,
            'file_path'     => $data['file_path'],
            'original_name' => $data['original_name'] ?? '',
            'mime_type'     => $data['mime_type'] ?? '',
            'file_size'     => $data['file_size'] ?? 0,
            'width'         => $data['width'] ?? null,
            'height'        => $data['height'] ?? null,
            'latitude'      => $data['latitude'] ?? null,
            'longitude'     => $data['longitude'] ?? null,
            'direction'     => $data['direction'] ?? null,
            'taken_at'      => $data['taken_at'] ?? null,
            'description'   => $data['description'] ?? null,
            'position'      => $this->nextPosition($trackId),
            'visibility'    => $data['visibility'] ?? 'private',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function nextPosition(int $trackId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), -1) + 1
               FROM media
              WHERE track_id = :track_id AND deleted_at IS NULL'
        );
        $stmt->execute(['track_id' => $trackId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Фотографии трека (без мягко-удалённых).
     */
    public function listForTrack(int $trackId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media
              WHERE track_id = :track_id AND deleted_at IS NULL
              ORDER BY position ASC, taken_at ASC, id ASC'
        );
        $stmt->execute(['track_id' => $trackId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForTrack(int $trackId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM media
              WHERE track_id = :track_id AND deleted_at IS NULL'
        );
        $stmt->execute(['track_id' => $trackId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Запись медиа по id + track_id (используется для проверки владения).
     */
    public function find(int $mediaId, int $trackId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media
              WHERE id = :id AND track_id = :track_id AND deleted_at IS NULL
              LIMIT 1'
        );
        $stmt->execute(['id' => $mediaId, 'track_id' => $trackId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Есть ли уже на треке фото с теми же координатами и размером
     * (линейным — width/height — и размером файла). Дубликаты пропускаются при загрузке.
     *
     * Координаты сравниваются null-безопасно (два фото без GPS считаются совпавшими),
     * но width/height на дубликат-проверку должны быть заданы (файл — валидное изображение).
     */
    public function isDuplicate(int $trackId, ?float $latitude, ?float $longitude, ?int $width, ?int $height, int $fileSize): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT EXISTS(
                SELECT 1 FROM media
                 WHERE track_id = :track_id AND type = 'photo' AND deleted_at IS NULL
                   AND latitude <=> :latitude AND longitude <=> :longitude
                   AND width = :width AND height = :height AND file_size = :file_size
             )"
        );
        $stmt->execute([
            'track_id'  => $trackId,
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'width'     => $width,
            'height'    => $height,
            'file_size' => $fileSize,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Обновление описания фотографии.
     */
    public function updateDescription(int $mediaId, int $trackId, string $description): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media
                SET description = :description
              WHERE id = :id AND track_id = :track_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id'          => $mediaId,
            'track_id'    => $trackId,
            'description' => $description !== '' ? $description : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Мягкое удаление фотографии (deleted_at = NOW()).
     */
    public function softDelete(int $mediaId, int $trackId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media
                SET deleted_at = NOW()
              WHERE id = :id AND track_id = :track_id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $mediaId, 'track_id' => $trackId]);

        return $stmt->rowCount() > 0;
    }
}