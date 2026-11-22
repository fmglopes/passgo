<?php
namespace PassGo\Repository;

use PDO;

class UserRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, name, email, password_hash, created_at FROM users WHERE id = :id');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT id, name, email, password_hash, created_at FROM users WHERE lower(email) = lower(:email)');
        $st->execute([':email' => $email]);
        return $st->fetch() ?: null;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $st = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash) RETURNING id');
        $st->execute([':name' => $name, ':email' => $email, ':hash' => $passwordHash]);
        return (int) $st->fetchColumn();
    }

    public function updateName(int $id, string $name): void
    {
        $st = $this->pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
        $st->execute([':name' => $name, ':id' => $id]);
    }
}
