<?php

declare(strict_types=1);

namespace App\Units;

use DateTime;
use DateTimeZone;
use InvalidArgumentException;

use phpGPX\Models\Collection;
use phpGPX\phpGPX;

/**
 * Импорт трека из файла GPX (sibyx/phpgpx) или JSON (формат phpGPX).
 *
 * Возвращает нормализованные данные для сохранения:
 *   - geometry    — массив точек [{lat, lng, ele, time}];
 *   - bbox        — [min_lat, max_lat, min_lng, max_lng];
 *   - date_recorded — дата начала записи (первая точка/см. файла) в таймзоне приложения;
 *   - description — описание из файла (может отсутствовать);
 *   - title       — название из файла (может отсутствовать);
 *   - count       — количество точек.
 */
final class TrackImporter
{
    public function __construct(private string $timezone = 'UTC')
    {
        $this->timezone = (string)\App\App::fromConfig('app.timezone', 'UTC');
    }

    public function parse(string $path, string $source): array
    {
        return match ($source) {
            'json' => $this->parseJson($path),
            default => $this->parseGpx($path),
        };
    }

    private function parseGpx(string $path): array
    {
        try {
            $gpx = phpGPX::load($path);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Не удалось разобрать GPX-файл: ' . $e->getMessage());
        }

        foreach ($gpx->tracks as $track) {
            if ($this->buildFromCollection($track, $result)) {
                return $result;
            }
        }

        foreach ($gpx->routes as $route) {
            if ($this->buildFromCollection($route, $result)) {
                return $result;
            }
        }

        throw new InvalidArgumentException('В файле не найдено точек трека');
    }

    private function buildFromCollection(Collection $track, ?array &$result): bool
    {
        $points = $track->getPoints();

        if (empty($points)) {
            return false;
        }

        $geometry = [];
        $minLat = $maxLat = $minLng = $maxLng = null;
        $startedAt = null;

        foreach ($points as $point) {
            $lat = (float)$point->latitude;
            $lng = (float)$point->longitude;

            $minLat = $minLat === null ? $lat : min($minLat, $lat);
            $maxLat = $maxLat === null ? $lat : max($maxLat, $lat);
            $minLng = $minLng === null ? $lng : min($minLng, $lng);
            $maxLng = $maxLng === null ? $lng : max($maxLng, $lng);

            $geometry[] = [
                'lat'  => round($lat, 6),
                'lng'  => round($lng, 6),
                'ele'  => $point->elevation !== null ? round((float)$point->elevation, 2) : null,
                'time' => $point->time?->format('c'),
            ];

            if ($startedAt === null && $point->time !== null) {
                $startedAt = $point->time;
            }
        }

        $result = [
            'geometry'      => $geometry,
            'bbox'          => [round($minLat, 6), round($maxLat, 6), round($minLng, 6), round($maxLng, 6)],
            'date_recorded' => $startedAt ? $this->toLocal($startedAt) : null,
            'description'   => trim((string)$track->description) ?: null,
            'title'         => trim((string)$track->name) ?: null,
            'count'         => count($geometry),
        ];

        return true;
    }

    private function parseJson(string $path): array
    {
        $data = json_decode((string)file_get_contents($path), true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Некорректный JSON трека');
        }

        foreach (($data['tracks'] ?? $data['routes'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $points = [];

            foreach (($row['trkseg'] ?? []) as $segment) {
                foreach (($segment['points'] ?? []) as $point) {
                    $points[] = $point;
                }
            }

            if (empty($points)) {
                $points = $row['points'] ?? [];
            }

            if (empty($points)) {
                continue;
            }

            $geometry = [];
            $minLat = $maxLat = $minLng = $maxLng = null;
            $startedAtRaw = $row['stats']['startedAt'] ?? null;

            foreach ($points as $point) {
                if (!isset($point['lat'], $point['lon'])) {
                    continue;
                }

                $lat = (float)$point['lat'];
                $lng = (float)$point['lon'];

                $minLat = $minLat === null ? $lat : min($minLat, $lat);
                $maxLat = $maxLat === null ? $lat : max($maxLat, $lat);
                $minLng = $minLng === null ? $lng : min($minLng, $lng);
                $maxLng = $maxLng === null ? $lng : max($maxLng, $lng);

                $geometry[] = [
                    'lat'  => round($lat, 6),
                    'lng'  => round($lng, 6),
                    'ele'  => isset($point['ele']) && $point['ele'] !== null ? round((float)$point['ele'], 2) : null,
                    'time' => (string)($point['time'] ?? '') ?: null,
                ];

                if ($startedAtRaw === null && !empty($point['time'])) {
                    $startedAtRaw = $point['time'];
                }
            }

            if (empty($geometry)) {
                throw new InvalidArgumentException('В файле нет точек с координатами');
            }

            $description = trim((string)($row['desc'] ?? '')) ?: null;

            return [
                'geometry'      => $geometry,
                'bbox'          => [round($minLat, 6), round($maxLat, 6), round($minLng, 6), round($maxLng, 6)],
                'date_recorded' => $startedAtRaw ? $this->fromString($startedAtRaw) : null,
                'description'   => $description,
                'title'         => trim((string)($row['name'] ?? '')) ?: null,
                'count'         => count($geometry),
            ];
        }

        throw new InvalidArgumentException('В JSON-файле не найдено треков с точками');
    }

    /**
     * Дата из файла (UTC) → таймзона приложения → 'Y-m-d H:i:s' для БД.
     */
    private function toLocal(DateTime $datetime): string
    {
        $datetime->setTimezone(new DateTimeZone($this->timezone));

        return $datetime->format('Y-m-d H:i:s');
    }

    private function fromString(string $value): ?string
    {
        try {
            return $this->toLocal(new DateTime($value));
        } catch (\Throwable) {
            return null;
        }
    }
}