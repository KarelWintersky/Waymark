<?php

declare(strict_types=1);

namespace App\Units;

use PDO;

/**
 * Токены доступа к трекам «по ссылке» (задача 13).
 *
 * Один активный токен на трек: повторная публикация по ссылке переиспользует
 * существующий, если он ещё действителен. Снятие публикации отзывает токен.
 */
final class TrackLinks
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Активный (не отозванный и не протухший) токен трека, или null.
     */
    public function active(int $trackId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM track_links
              WHERE track_id = :track_id
                AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute(['track_id' => $trackId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Существующий активный токен либо создание нового.
     *
     * @return array{token: string, expires_at: string|null}
     */
    public function ensure(int $trackId, int $ttlDays): array
    {
        $active = $this->active($trackId);
        if ($active !== null) {
            return $active;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $ttlDays > 0
            ? date('Y-m-d H:i:s', time() + $ttlDays * 86400)
            : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO track_links (track_id, token, expires_at)
             VALUES (:track_id, :token, :expires_at)'
        );
        $stmt->execute([
            'track_id'   => $trackId,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Действительный токен по значению (для /shared/{token}).
     */
    public function findValid(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*, t.user_id, t.visibility
               FROM track_links l
               JOIN tracks t ON t.id = l.track_id AND t.deleted_at IS NULL
              WHERE l.token = :token
                AND l.revoked_at IS NULL
                AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)'
        );
        $stmt->execute(['token' => $token]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function revoke(int $trackId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE track_links
                SET revoked_at = CURRENT_TIMESTAMP
              WHERE track_id = :track_id AND revoked_at IS NULL'
        );

        return $stmt->execute(['track_id' => $trackId]);
    }
}