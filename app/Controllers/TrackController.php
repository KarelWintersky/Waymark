<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Units\MediaFiles;
use App\Units\Track;
use App\Units\TrackFiles;
use App\Units\TrackImporter;
use App\Units\TrackLinks;
use App\Units\TrackMedia;
use Arris\Controllers\AbstractController;
use Arris\DelightAuth\Auth\Auth;
use DateTime;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * CRUD треков (этап 1): создание с загрузкой файла (без парсинга), редактирование
 * метаданных, мягкое удаление, список своих треков. Публикация — заглушки (задачи 12-13).
 */
final class TrackController extends AbstractController
{
    private const VISIBILITY_LABELS = [
        'private'   => 'Приватный',
        'protected' => 'По ссылке',
        'public'    => 'Публичный',
    ];

    private const EXTENSIONS = ['gpx', 'json'];

    private Auth $auth;

    private Track $track;

    private TrackFiles $files;

    private TrackImporter $importer;

    private TrackLinks $links;

    private MediaFiles $mediaFiles;

    private TrackMedia $media;

    public function __construct(
        ?\Arris\App $app = null,
        ?LoggerInterface $logger = null,
        ?object $presenter = null,
        ?Auth $auth = null,
    ) {
        parent::__construct($app, $logger, $presenter);
        $this->auth = $auth ?? $this->app->auth();
        $this->track = new Track($this->app->pdo());
        $this->files = new TrackFiles();
        $this->importer = new TrackImporter();
        $this->links = new TrackLinks($this->app->pdo());
        $this->mediaFiles = new MediaFiles();
        $this->media = new TrackMedia($this->app->pdo());
    }

    private function requireLogin(): void
    {
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/login');
        }
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    /**
     * Флэш-сообщение на следующий запрос (читается и снимается на странице медиа / трека).
     */
    private function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['waymark_flash'] = ['type' => $type, 'message' => $message];
    }

    private function maxUploadBytes(): int
    {
        return (int)$this->app->fromConfig('limits.upload_max_size_bytes', 50 * 1024 * 1024);
    }

    /**
     * Человекочитаемый размер файла для таблицы медиа.
     */
    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, '.', ' '), '0'), '.') . ' МБ';
        }
        if ($bytes >= 1024) {
            return rtrim(rtrim(number_format($bytes / 1024, 1, '.', ' '), '0'), '.') . ' КБ';
        }

        return $bytes . ' Б';
    }

    /**
     * «2026-09-16» из datepicker → datetime для БД, иначе null.
     *
     * @throws InvalidArgumentException
     */
    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);

        if ($date === false) {
            throw new InvalidArgumentException('Неверный формат даты');
        }

        return $date->format('Y-m-d 00:00:00');
    }

    /**
     * Ручное создание трека: точки {lat,lng} с карты, JSON в поле `points`.
     */
    public function createManual(): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();
        $error = null;
        $old = [
            'title'         => '',
            'description'   => '',
            'date_recorded' => '',
        ];
        $points = [];
        $pointsJson = '[]';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = '';
            $description = '';
            $dateRecorded = null;

            try {
                $points = $this->parseManualPoints((string)($_POST['points'] ?? ''));

                if (count($points) < 2) {
                    throw new InvalidArgumentException('Добавьте на карте минимум 2 засечки');
                }

                $title = trim((string)($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new InvalidArgumentException('Укажите название трека');
                }

                $description = trim((string)($_POST['description'] ?? ''));
                $dateRecorded = $this->normalizeDate((string)($_POST['date_recorded'] ?? ''));

                $lats = array_column($points, 'lat');
                $lngs = array_column($points, 'lng');

                $trackId = $this->track->create($userId, [
                    'title'         => $title,
                    'description'   => $description,
                    'source'        => 'manual',
                    'date_recorded' => $dateRecorded,
                    'geometry'      => $points,
                    'bbox'          => [min($lats), max($lats), min($lngs), max($lngs)],
                ]);

                $this->logger->info('Track created manually', [
                    'track_id' => $trackId,
                    'user_id'  => $userId,
                    'points'   => count($points),
                ]);
                $this->redirect('/my/tracks');
            } catch (Throwable $e) {
                $this->logger->error('Track create (manual) failed', ['error' => $e->getMessage()]);
                $error = $e->getMessage();
                $old = [
                    'title'         => $title,
                    'description'   => $description,
                    'date_recorded' => $dateRecorded ? substr($dateRecorded, 0, 10) : '',
                ];
                $pointsJson = json_encode($points, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        $this->presenter->present([
            'template'          => 'tracks/create-manual.tpl',
            'title'             => 'Создать трек — Waymark',
            'map'               => true,
            'old'               => $old,
            'error'             => $error,
            'draft_points_json' => $pointsJson,
            'default_lat'       => (float)$this->app->fromConfig('default.lat', 59.93863),
            'default_lon'       => (float)$this->app->fromConfig('default.lon', 30.314113),
            'default_zoom'      => (int)$this->app->fromConfig('default.zoom', 11),
        ]);
    }

    /**
     * Разбор и нормализация засечек из JSON-поля `points`.
     *
     * @return array<int, array{lat: float, lng: float}>
     *
     * @throws InvalidArgumentException
     */
    private function parseManualPoints(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || $decoded === []) {
            throw new InvalidArgumentException('Не удалось прочитать точки маршрута');
        }

        $points = [];

        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['lat'], $item['lng']) || !is_numeric($item['lat']) || !is_numeric($item['lng'])) {
                continue;
            }

            $points[] = [
                'lat' => round((float)$item['lat'], 6),
                'lng' => round((float)$item['lng'], 6),
            ];
        }

        return $points;
    }

    public function myTracks(): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();
        $tracks = $this->track->listForUser($userId);

        foreach ($tracks as &$row) {
            $row['visibility_label'] = self::VISIBILITY_LABELS[$row['visibility']] ?? $row['visibility'];
            $row['source_file'] = $row['source'] === 'manual'
                ? null
                : $this->files->filePath($userId, (int)$row['id'], (string)$row['source']);
            $row['share_url'] = null;

            if ($row['visibility'] === 'protected') {
                $link = $this->links->active((int)$row['id']);
                $row['share_url'] = $link !== null ? '/shared/' . $link['token'] : null;
            }
        }
        unset($row);

        $this->presenter->present([
            'template' => 'tracks/my.tpl',
            'title'    => 'Мои треки — Waymark',
            'tracks'   => $tracks,
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();
        $error = null;
        $old = [
            'title'          => '',
            'description'    => '',
            'date_recorded'  => '',
            'date_from_file' => true,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = '';
            $description = '';
            $dateFromFile = true;
            $dateRecorded = null;

            try {
                $title = trim((string)($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new InvalidArgumentException('Укажите название трека');
                }

                $description = trim((string)($_POST['description'] ?? ''));
                $dateFromFile = isset($_POST['date_from_file']);
                $dateRecorded = $dateFromFile ? null : $this->normalizeDate((string)($_POST['date_recorded'] ?? ''));

                if (!isset($_FILES['source_file'])) {
                    throw new InvalidArgumentException('Загрузите файл трека (GPX или JSON)');
                }

                $ext = $this->files->check($_FILES['source_file'], $this->maxUploadBytes());

                $parsed = $this->importer->parse($_FILES['source_file']['tmp_name'], $ext);

                $trackId = $this->track->create($userId, [
                    'title'         => $title,
                    'description'   => $description !== '' ? $description : $parsed['description'],
                    'source'        => $ext,
                    'date_recorded' => $dateRecorded ?? $parsed['date_recorded'],
                    'geometry'      => $parsed['geometry'],
                    'bbox'          => $parsed['bbox'],
                ]);

                try {
                    $this->files->store($userId, $trackId, $_FILES['source_file'], $ext);
                } catch (Throwable $e) {
                    $this->track->softDelete($trackId, $userId);
                    throw $e;
                }

                $this->logger->info('Track created', [
                    'track_id' => $trackId,
                    'user_id'  => $userId,
                    'points'   => $parsed['count'],
                ]);
                $this->redirect('/my/tracks');
            } catch (Throwable $e) {
                $this->logger->error('Track create failed', ['error' => $e->getMessage()]);
                $error = $e->getMessage();
                $old = [
                    'title'          => $title,
                    'description'    => $description,
                    'date_recorded'  => $dateRecorded ? substr($dateRecorded, 0, 10) : '',
                    'date_from_file' => $dateFromFile,
                ];
            }
        }

        $this->presenter->present([
            'template' => 'tracks/form.tpl',
            'title'    => 'Новый трек — Waymark',
            'mode'     => 'create',
            'track'    => null,
            'old'      => $old,
            'error'    => $error,
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();
        $track = $this->track->findMine($id, $userId);

        if ($track === null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            return;
        }

        $error = null;
        $old = [
            'title'          => (string)$track['title'],
            'description'    => (string)($track['description'] ?? ''),
            'date_recorded'  => $track['date_recorded'] ? substr((string)$track['date_recorded'], 0, 10) : '',
            'date_from_file' => $track['date_recorded'] === null,
        ];

        $title = $old['title'];
        $formDescription = $old['description'];
        $dateFromFile = $old['date_from_file'];
        $dateRecorded = $old['date_recorded'] === '' ? null : $old['date_recorded'] . ' 00:00:00';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $title = trim((string)($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new InvalidArgumentException('Укажите название трека');
                }

                $formDescription = trim((string)($_POST['description'] ?? ''));
                $dateFromFile = isset($_POST['date_from_file']);
                $dateRecorded = $dateFromFile ? null : $this->normalizeDate((string)($_POST['date_recorded'] ?? ''));

                $source = (string)$track['source'];
                $hasNewFile = isset($_FILES['source_file']) && $_FILES['source_file']['error'] !== UPLOAD_ERR_NO_FILE;

                $parsed = null;

                if ($hasNewFile) {
                    $source = $this->files->check($_FILES['source_file'], $this->maxUploadBytes());
                    $parsed = $this->importer->parse($_FILES['source_file']['tmp_name'], $source);

                    $this->files->store($userId, $id, $_FILES['source_file'], $source);

                    if ($track['source'] !== $source) {
                        @unlink($this->files->filePath($userId, $id, (string)$track['source']));
                    }
                }

                $this->track->update($id, $userId, [
                    'title'         => $title,
                    'description'   => $formDescription,
                    'source'        => $source,
                    'date_recorded' => $dateRecorded,
                ]);

                // Импорт: новый файл или legacy-трек без геометрии (ре-импорт существующего).
                $needsImport = $parsed !== null || (string)($track['geometry'] ?? '') === '[]';

                if ($needsImport && ($parsed !== null || is_file($this->files->filePath($userId, $id, $source)))) {
                    if ($parsed === null) {
                        $parsed = $this->importer->parse($this->files->filePath($userId, $id, $source), $source);
                    }

                    $this->track->import($id, $userId, [
                        ...$parsed,
                        'date_recorded' => $dateRecorded ?? $parsed['date_recorded'],
                        'description'   => $formDescription !== '' ? $formDescription : $parsed['description'],
                    ]);
                }

                $this->logger->info('Track updated', [
                    'track_id' => $id,
                    'user_id'  => $userId,
                    'points'   => $parsed['count'] ?? null,
                ]);
                $this->redirect('/my/tracks');
            } catch (Throwable $e) {
                $this->logger->error('Track edit failed', ['track_id' => $id, 'error' => $e->getMessage()]);
                $error = $e->getMessage();
                $old = [
                    'title'          => $title,
                    'description'    => $formDescription,
                    'date_recorded'  => $dateRecorded ? substr($dateRecorded, 0, 10) : '',
                    'date_from_file' => $dateFromFile,
                ];
            }
        }

        $this->presenter->present([
            'template' => 'tracks/form.tpl',
            'title'    => 'Редактирование трека — Waymark',
            'mode'     => 'edit',
            'track'    => [
                'id'          => $id,
                'source'      => $track['source'],
                'source_file' => $track['source'] === 'manual'
                    ? null
                    : $this->files->filePath($userId, $id, (string)$track['source']),
            ],
            'old'      => $old,
            'error'    => $error,
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();

        if ($this->track->softDelete($id, $userId)) {
            $this->logger->info('Track soft-deleted', ['track_id' => $id, 'user_id' => $userId]);
        }

        $this->redirect('/my/tracks');
    }

    /**
     * Страница «Медиа» трека — единственное место управления фотографиями.
     *
     * GET  — форма загрузки + таблица загруженных фото (превью, название,
     *        размер, координаты, направление взгляда, редактирование описания, удаление).
     * POST — мультизагрузка фотографий к треку (поле photos[]), см. storeUploadedPhotos().
     */
    public function media(int $id): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();
        $track = $this->track->findMine($id, $userId);

        if ($track === null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->storeUploadedPhotos($id, $track);

            return;
        }

        $media = $this->media->listForTrack($id);

        foreach ($media as &$row) {
            $row['size_human'] = $this->humanBytes((int)($row['file_size'] ?? 0));
        }
        unset($row);

        $flash = null;
        if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['waymark_flash'])) {
            $flash = $_SESSION['waymark_flash'];
            unset($_SESSION['waymark_flash']);
        }

        $this->presenter->present([
            'template'          => 'tracks/media.tpl',
            'title'             => 'Медиа — ' . $track['title'] . ' — Waymark',
            'track'             => $track,
            'media'             => $media,
            'flash'             => $flash,
            'upload_max_images' => (int)$this->app->fromConfig('limits.upload_max_images', 10),
        ]);
    }

    /**
     * Мультизагрузка фотографий к треку: POST /tracks/{id}/media (поле photos[]).
     *
     * Каждый файл проходит через arris.php-file-upload (валидация MIME/размера,
     * перемещение в storage/media/<user>/<track>/), после чего из файла читается
     * EXIF (время съёмки, координаты, направление взгляда) и в таблицу media
     * пишется запись вместе с координатами/направлением.
     *
     * @param array $track строка трека из findMine()
     */
    private function storeUploadedPhotos(int $id, array $track): void
    {
        $userId = (int)$this->auth->getUserId();

        $files = $_FILES['photos'] ?? null;

        // Нормализация: поле photos[] — массив; единичный файл сворачиваем в массив.
        if (is_array($files) && isset($files['name']) && !is_array($files['name'])) {
            foreach ($files as $key => $value) {
                $files[$key] = [$value];
            }
        }

        $total = is_array($files) && isset($files['name']) ? count($files['name']) : 0;

        if ($total === 0) {
            $this->flash('error', 'Выберите фотографии для загрузки');
            $this->redirect('/tracks/' . $id . '/media');
        }

        $maxTotal = (int)$this->app->fromConfig('limits.upload_max_images', 10);
        $room = max(0, $maxTotal - $this->media->countForTrack($id));
        $maxBytes = $this->maxUploadBytes();

        $uploaded = 0;
        $errors = [];

        foreach ($files['name'] as $index => $name) {
            if ($name === '') {
                continue;
            }

            if ($uploaded >= $room) {
                $errors[] = 'Достигнут лимит фотографий на трек (' . $maxTotal . ' шт.)';
                break;
            }

            try {
                $stored = $this->mediaFiles->store($userId, $id, $files, (int)$index, $maxBytes);

                $this->media->insert($id, [
                    ...$stored,
                    'visibility' => $track['visibility'] === 'public' ? 'public' : 'private',
                ]);

                $uploaded++;
            } catch (Throwable $e) {
                $this->logger->error('Photo upload failed', [
                    'track_id' => $id,
                    'user_id'  => $userId,
                    'file'     => (string)$name,
                    'error'    => $e->getMessage(),
                ]);
                $errors[] = $e->getMessage();
            }
        }

        $this->logger->info('Photos uploaded', [
            'track_id' => $id,
            'user_id'  => $userId,
            'uploaded' => $uploaded,
            'failed'   => count($errors),
        ]);

        $parts = [];
        if ($uploaded > 0) {
            $parts[] = 'Загружено фото: ' . $uploaded;
        }
        if ($errors !== []) {
            $parts[] = 'Ошибки (' . count($errors) . '): ' . implode('; ', array_slice($errors, 0, 3));
        }

        $this->flash($errors !== [] ? 'error' : 'success', $parts !== [] ? implode('. ', $parts) : 'Файлы не загружены');
        $this->redirect('/tracks/' . $id . '/media');
    }

    /**
     * Редактирование описания фотографии: POST /tracks/{id}/media/{mediaId}/description.
     */
    public function updateMediaDescription(int $id, int $mediaId): void
    {
        $this->requireLogin();
        $this->requireMine($id);

        if ($this->media->find($mediaId, $id) === null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            exit;
        }

        $description = trim((string)($_POST['description'] ?? ''));

        if (mb_strlen($description) > 500) {
            $this->flash('error', 'Описание слишком длинное (максимум 500 символов)');
            $this->redirect('/tracks/' . $id . '/media');
        }

        $this->media->updateDescription($mediaId, $id, $description);

        $this->logger->info('Media description updated', [
            'track_id' => $id,
            'media_id' => $mediaId,
            'user_id'  => (int)$this->auth->getUserId(),
        ]);

        $this->flash('success', 'Описание обновлено');
        $this->redirect('/tracks/' . $id . '/media');
    }

    /**
     * Мягкое удаление фотографии: POST /tracks/{id}/media/{mediaId}/delete.
     */
    public function deleteMedia(int $id, int $mediaId): void
    {
        $this->requireLogin();
        $this->requireMine($id);

        if (!$this->media->softDelete($mediaId, $id)) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            exit;
        }

        $this->logger->info('Media soft-deleted', [
            'track_id' => $id,
            'media_id' => $mediaId,
            'user_id'  => (int)$this->auth->getUserId(),
        ]);

        $this->flash('success', 'Фотография удалена');
        $this->redirect('/tracks/' . $id . '/media');
    }

    /**
     * «Опубликовать по ссылке»: visibility=protected + активный токен.
     */
    public function share(int $id): void
    {
        $this->requireLogin();
        $this->requireMine($id);

        $userId = (int)$this->auth->getUserId();
        $ttlDays = (int)$this->app->fromConfig('links.ttl_days', 0);
        $link = $this->links->ensure($id, $ttlDays);
        $this->track->setVisibility($id, $userId, 'protected');

        $this->logger->info('Track shared by link', ['track_id' => $id, 'user_id' => $userId]);
        $this->redirect('/my/tracks');
    }

    public function publish(int $id): void
    {
        $this->requireLogin();
        $this->requireMine($id);

        $userId = (int)$this->auth->getUserId();
        $this->links->revoke($id);
        $this->track->setVisibility($id, $userId, 'public');

        $this->logger->info('Track published', ['track_id' => $id, 'user_id' => $userId]);
        $this->redirect('/my/tracks');
    }

    public function unpublish(int $id): void
    {
        $this->requireLogin();
        $this->requireMine($id);

        $userId = (int)$this->auth->getUserId();
        $this->links->revoke($id);
        $this->track->setVisibility($id, $userId, 'private');

        $this->logger->info('Track made private', ['track_id' => $id, 'user_id' => $userId]);
        $this->redirect('/my/tracks');
    }

    /**
     * Проверка «трек существует и принадлежит» для операций публикации.
     */
    private function requireMine(int $id): void
    {
        $userId = (int)$this->auth->getUserId();

        if ($this->track->findMine($id, $userId) === null) {
            $this->presenter->present([
                'template' => 'errors/404.tpl',
                'title'    => 'Страница не найдена',
            ], 404);

            exit;
        }
    }
}