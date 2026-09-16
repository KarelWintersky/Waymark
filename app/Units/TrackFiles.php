<?php

declare(strict_types=1);

namespace App\Units;

use InvalidArgumentException;
use RuntimeException;

/**
 * Хранение файлов треков на диске.
 *
 * Структура: storage/tracks/<user_id>/<track_id>.<ext>, где ext — 'gpx'|'json'
 * (предсказуемое имя — путь выводится из id и source, в БД не хранится).
 */
final class TrackFiles
{
    private string $base;

    public function __construct(?string $base = null)
    {
        $this->base = $base ?? (string)\App\App::fromConfig('paths.tracks');
    }

    public function filePath(int $userId, int $trackId, string $ext): string
    {
        return "{$this->base}/{$userId}/{$trackId}.{$ext}";
    }

    /**
     * Валидация загружаемого файла: расширение, ошибка аплоада, размер.
     *
     * @throws InvalidArgumentException
     */
    public function check(array $file, int $maxBytes): string
    {
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($ext, ['gpx', 'json'], true)) {
            throw new InvalidArgumentException('Допустимы только файлы GPX или JSON');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Не удалось загрузить файл — повторите попытку');
        }

        if ((int)($file['size'] ?? 0) > $maxBytes) {
            throw new InvalidArgumentException('Файл превышает допустимый размер');
        }

        return $ext;
    }

    /**
     * Перемещение файла в storage/tracks/<user_id>/<track_id>.<ext>.
     */
    public function store(int $userId, int $trackId, array $file, string $ext): void
    {
        $dir = "{$this->base}/{$userId}";

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать каталог для файлов трека');
        }

        if (!move_uploaded_file($file['tmp_name'], $this->filePath($userId, $trackId, $ext))) {
            throw new RuntimeException('Не удалось сохранить файл трека');
        }
    }
}