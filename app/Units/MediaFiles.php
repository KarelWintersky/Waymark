<?php

declare(strict_types=1);

namespace App\Units;

use Arris\Toolkit\FileUpload;
use Arris\Toolkit\FileUploadResult;
use PHPExif\Enum\ReaderType;
use PHPExif\Exif;
use PHPExif\Reader\Reader;
use RuntimeException;
use Throwable;

/**
 * Хранение фотографий треков на диске и чтение EXIF.
 *
 * Структура: storage/media/<user_id>/<track_id>/<имя>. Имя генерируется
 * детерминированно из содержимого (MIME), в БД хранится путь относительно
 * корня media: <user_id>/<track_id>/<имя> (колонка media.file_path).
 */
final class MediaFiles
{
    private string $base;

    public function __construct(?string $base = null)
    {
        $this->base = $base ?? (string)\App\App::fromConfig('paths.media');
    }

    public function filePath(int $userId, int $trackId, string $name): string
    {
        return "{$this->base}/{$userId}/{$trackId}/{$name}";
    }

    /**
     * Копирует относительный путь (из media.file_path) в абсолютный путь на диске.
     */
    public function resolve(string $relativePath): string
    {
        return "{$this->base}/{$relativePath}";
    }

    /**
     * Загрузка одного файла (индекс из массива `photos[]`): валидация,
     * перемещение на диск, разбор EXIF. На вход — весь массив $_FILES['photos'],
     * на выходе — данные для вставки в таблицу media.
     *
     * @return array<string, mixed> Данные записи media (file_path относительно корня media).
     *
     * @throws RuntimeException
     */
    public function store(int $userId, int $trackId, array $files, int $index, int $maxBytes): array
    {
        $dir = "{$this->base}/{$userId}/{$trackId}";

        $uploader = FileUpload::fromFile($files, $index)
            ->setTargetPath($dir)
            ->allowMimeTypes(['image/jpeg', 'image/png'])
            ->setMaxFileSize($maxBytes)
            ->setFilenameGenerator(static function (FileUploadResult $source): string {
                $ext = match ($source->mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    default      => strtolower((string)pathinfo((string)$source->originalName, PATHINFO_EXTENSION)) ?: 'jpg',
                };

                return date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            })
            ->throwExceptions(false);

        $result = $uploader->process();

        if (!$result->isSuccess) {
            throw new RuntimeException(implode('; ', $result->errors ?: ['Не удалось загрузить файл']));
        }

        return [
            'file_path'     => "{$userId}/{$trackId}/{$result->savedName}",
            'original_name' => (string)$result->originalName,
            'mime_type'     => (string)$result->mimeType,
            'file_size'     => (int)$result->size,
            ...$this->extractExif((string)$result->fullPath),
        ];
    }

    /**
     * EXIF фотографии: время съёмки, координаты и направление взгляда.
     *
     * @return array{latitude: ?float, longitude: ?float, taken_at: ?string, direction: ?int}
     */
    public function extractExif(string $fullPath): array
    {
        $empty = [
            'latitude'  => null,
            'longitude' => null,
            'taken_at'  => null,
            'direction' => null,
        ];

        try {
            $exif = Reader::factory(ReaderType::NATIVE)->read($fullPath);
        } catch (Throwable) {
            return $empty;
        }

        if (!$exif instanceof Exif) {
            return $empty;
        }

        $lat = $exif->getLatitude();
        $lon = $exif->getLongitude();

        $direction = null;
        $imgDirection = $exif->getImgDirection();
        if ($imgDirection !== false) {
            $direction = (int)round($imgDirection);
        }

        $takenAt = null;
        $date = $exif->getCreationDate();
        if ($date instanceof \DateTimeInterface) {
            $takenAt = $date->format('Y-m-d H:i:s');
        }

        return [
            'latitude'  => $lat !== false ? round($lat, 6) : null,
            'longitude' => $lon !== false ? round($lon, 6) : null,
            'taken_at'  => $takenAt,
            'direction' => $direction,
        ];
    }
}