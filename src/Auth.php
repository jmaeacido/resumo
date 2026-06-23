<?php

namespace Resumo;

final class Auth
{
    public static function user(): ?array
    {
        $id = (int)($_SESSION['user_id'] ?? 0);
        return $id > 0 ? Database::findUserById($id) : null;
    }

    public static function userId(): ?int
    {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    public static function signup(string $name, string $email, string $password): array
    {
        $name = self::cleanName($name);
        $email = self::cleanEmail($email);
        self::validatePassword($password);

        if (Database::findUserByEmail($email)) {
            throw new \RuntimeException('An account already exists for that email.');
        }

        $user = Database::createUser($name, $email, password_hash($password, PASSWORD_DEFAULT));
        self::setUser($user);

        return self::publicUser($user);
    }

    public static function login(string $email, string $password): array
    {
        $email = self::cleanEmail($email);
        $user = Database::findUserByEmail($email);

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            throw new \RuntimeException('Email or password is incorrect.');
        }

        self::setUser($user);
        return self::publicUser($user);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function publicUser(?array $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'email' => (string)$user['email'],
        ];
    }

    private static function setUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if (mb_strlen($name) < 2) {
            throw new \RuntimeException('Enter your name.');
        }
        if (mb_strlen($name) > 120) {
            throw new \RuntimeException('Name must be 120 characters or fewer.');
        }

        return $name;
    }

    private static function cleanEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Enter a valid email address.');
        }

        return $email;
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \RuntimeException('Password must be at least 8 characters.');
        }
    }
}
