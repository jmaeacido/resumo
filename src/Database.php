<?php

namespace Resumo;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function saveReport(array $analysis, ?int $userId = null): int
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('INSERT INTO resume_reports (user_id, mode, score, job_title, analysis_json, created_at) VALUES (:user_id, :mode, :score, :job_title, :analysis_json, :created_at)');
        $stmt->execute([
            ':user_id' => $userId,
            ':mode' => $analysis['mode'] ?? 'resume',
            ':score' => $analysis['overall'] ?? 0,
            ':job_title' => $analysis['job_title'] ?? null,
            ':analysis_json' => json_encode($analysis, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function findReport(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT analysis_json FROM resume_reports WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $json = $stmt->fetchColumn();

        return $json ? json_decode($json, true) : null;
    }

    public static function createUser(string $name, string $email, string $passwordHash): array
    {
        $stmt = self::pdo()->prepare('INSERT INTO users (name, email, password_hash, created_at) VALUES (:name, :email, :password_hash, :created_at)');
        $stmt->execute([
            ':name' => $name,
            ':email' => mb_strtolower($email),
            ':password_hash' => $passwordHash,
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return self::findUserById((int)self::$pdo->lastInsertId()) ?? [];
    }

    public static function findUserByEmail(string $email): ?array
    {
        $stmt = self::pdo()->prepare('SELECT id, name, email, password_hash, created_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => mb_strtolower($email)]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findUserById(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $host = env_value('DB_HOST', '127.0.0.1');
        $port = env_value('DB_PORT', '3306');
        $database = env_value('DB_DATABASE', 'resumo');
        $username = env_value('DB_USERNAME', 'root');
        $password = env_value('DB_PASSWORD', '');

        $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $server = new PDO($serverDsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::migrate();
        return self::$pdo;
    }

    private static function migrate(): void
    {
        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS resume_reports (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                mode VARCHAR(20) NOT NULL,
                score INTEGER NOT NULL,
                job_title VARCHAR(255) NULL,
                analysis_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX resume_reports_user_created_at_index (user_id, created_at),
                INDEX resume_reports_mode_created_at_index (mode, created_at),
                INDEX resume_reports_score_index (score)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureColumn('resume_reports', 'user_id', 'ALTER TABLE resume_reports ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id, ADD INDEX resume_reports_user_created_at_index (user_id, created_at)');

        self::$pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY users_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = self::$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        if ((int)$stmt->fetchColumn() === 0) {
            self::$pdo->exec($sql);
        }
    }
}
