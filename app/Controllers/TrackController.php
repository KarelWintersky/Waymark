<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Units\Track;
use App\Units\TrackFiles;
use App\Units\TrackImporter;
use App\Units\TrackLinks;
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

    private function maxUploadBytes(): int
    {
        return (int)$this->app->fromConfig('limits.upload_max_size_bytes', 50 * 1024 * 1024);
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

    public function myTracks(): void
    {
        $this->requireLogin();

        $userId = (int)$this->auth->getUserId();
        $tracks = $this->track->listForUser($userId);

        foreach ($tracks as &$row) {
            $row['visibility_label'] = self::VISIBILITY_LABELS[$row['visibility']] ?? $row['visibility'];
            $row['source_file'] = $this->files->filePath($userId, (int)$row['id'], (string)$row['source']);
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
                'source_file' => $this->files->filePath($userId, $id, (string)$track['source']),
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