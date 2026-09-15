<?php

use App\App;
use Arris\DelightAuth\Auth\Auth;
use Arris\DelightAuth\Auth\Exceptions\AuthException;

define("__ROOT__", dirname(__DIR__));
define('ENGINE_START_TIME', microtime(true));

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $app = App::factory((array)\App\App::configFilePath());

    $pdo = $app->pdo();

    $auth = new Auth(
        databaseConnection: $pdo,
        dbTablePrefix: ''
    );

    // get users
    $users = $pdo->query("SELECT id, email, username, roles_mask FROM users ORDER BY id")->fetchAll();
    array_walk($users, function (&$user) {
        $rolesBitmask = $user['roles_mask'];
        $roles = \array_filter(
            \Arris\DelightAuth\Auth\Role::getMap(),
            function ($each) use ($rolesBitmask) {
                return ($rolesBitmask & $each) === $each;
            },
            \ARRAY_FILTER_USE_KEY
        );
        $user['roles_mask'] = implode(', ', $roles);
    });

    Laravel\Prompts\info('Users: ');
    Laravel\Prompts\table(
        headers: ['id', 'E-Mail', 'Username', 'Roles'],
        rows: $users
    );

    $email = Laravel\Prompts\text(
        label: 'Введите email пользователя:',
        required: false,
        validate: function (string $email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? null : 'Incorrect email';
        }
    );

    $username = Laravel\Prompts\text(
        label: 'Введите имя пользователя: ',
        required: false,
        validate: fn (string $value) => match (true) {
            strlen($value) < 3 => 'The name must be at least 3 characters.',
            strlen($value) > 255 => 'The name must not exceed 255 characters.',
            default => null
        }
    );

    $password = Laravel\Prompts\password(
        label: 'Введите пароль пользователя: ',
        required: false,
        validate: fn (string $value) => match (true) {
            strlen($value) < 8 => 'The password must be at least 8 characters.',
            default => null
        },
        hint: 'Minimum 8 characters.'
    );

    $role = Laravel\Prompts\select(
        label: 'Assign roles and projects',
        options: [
            \Arris\DelightAuth\Auth\Role::ADMIN     =>  'Admin, can edit roles and view logs</>',
            0                                       =>  'Common publisher',
        ],
        // default: ['PUBLISHER'],
        required: true
    );

    $userId = $auth->admin()->createUser($email, $password, $username);
    if ($userId) {
        if ($role) {
            $auth->admin()->addRoleForUserById($userId, $role);
        }
    }

    echo 'We have created and activated a new user with the ID ' . $userId . PHP_EOL;
}
catch (AuthException $e) {
    var_dump($e);
}
