<?php

declare(strict_types=1);

namespace App\Controllers;

use Arris\Controllers\AbstractController;
use Arris\DelightAuth\Auth\Auth;
use Arris\DelightAuth\Auth\Exceptions\AuthException;
use Arris\DelightAuth\Auth\Exceptions\EmailNotVerifiedException;
use Arris\DelightAuth\Auth\Exceptions\InvalidEmailException;
use Arris\DelightAuth\Auth\Exceptions\InvalidPasswordException;
use Arris\DelightAuth\Auth\Exceptions\TooManyRequestsException;

/**
 * Авторизация: вход/выход (сессии). Регистрация и сброс пароля —
 * сейчас только фронтенд-заготовки, аккаунты создаются скриптом admin/auth.register.php.
 */
final class AuthController extends AbstractController
{
    /**
     * «Запомнить меня» — 14 дней.
     */
    private const REMEMBER_DURATION = 60 * 60 * 24 * 14;

    private Auth $auth;

    public function __construct(
        ?\Arris\App $app = null,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?object $presenter = null,
        ?Auth $auth = null,
    ) {
        parent::__construct($app, $logger, $presenter);
        $this->auth = $auth ?? $this->app->auth();
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    public function login(): void
    {
        if ($this->auth->isLoggedIn()) {
            $this->redirect('/');
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $remember = isset($_POST['remember']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Введите корректный email';
            } else {
                try {
                    $this->auth->login(
                        email: $email,
                        password: $password,
                        rememberDuration: $remember ? self::REMEMBER_DURATION : null,
                    );

                    $this->logger->info('User logged in', ['user_id' => $this->auth->getUserId()]);
                    $this->redirect('/');
                } catch (InvalidEmailException | InvalidPasswordException) {
                    $error = 'Неверный email или пароль';
                } catch (EmailNotVerifiedException) {
                    $error = 'Email не подтверждён';
                } catch (TooManyRequestsException) {
                    $error = 'Слишком много попыток входа. Попробуйте позже';
                } catch (AuthException $e) {
                    $this->logger->warning('Login failed', ['error' => $e->getMessage()]);
                    $error = 'Не удалось войти';
                }
            }
        }

        $this->presenter->present([
            'template' => 'auth/login.tpl',
            'title'    => 'Вход — Waymark',
            'error'    => $error,
            'email'    => trim((string)($_POST['email'] ?? '')),
        ]);
    }

    public function logout(): void
    {
        $this->auth->logOut();
        $this->logger->info('User logged out');

        $this->redirect('/');
    }

    /**
     * Заглушка: публичная регистрация отключена, аккаунты создаёт администратор.
     */
    public function register(): void
    {
        if ($this->auth->isLoggedIn()) {
            $this->redirect('/');
        }

        $this->presenter->present([
            'template' => 'auth/register.tpl',
            'title'    => 'Регистрация — Waymark',
        ]);
    }

    /**
     * Заглушка: бэкенд восстановления пароля появится позже.
     */
    public function forgotPassword(): void
    {
        $this->presenter->present([
            'template' => 'auth/password-forgot.tpl',
            'title'    => 'Сброс пароля — Waymark',
        ]);
    }
}