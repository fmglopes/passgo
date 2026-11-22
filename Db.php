<?php
// Helpers to prepare the TEST database and create data for tests.

use PassGo\Database;

function test_pdo(): PDO
{
    return Database::pdo();
}

/** Drop and recreate all tables, then load the demo seed. Only ever runs against a *_test database. */
function reset_database(): void
{
    $name = (string) config('db.name');
    if (stripos($name, 'test') === false) {
        fwrite(STDERR, "Refusing to reset database '$name': its name must contain 'test'.\n");
        exit(2);
    }
    $pdo = test_pdo();
    $pdo->exec(file_get_contents(PASSGO_ROOT . '/database/schema.sql'));
    $pdo->exec(file_get_contents(PASSGO_ROOT . '/database/seed.sql'));
}

function unique_email(string $prefix = 'user'): string
{
    static $n = 0;
    $n++;
    return $prefix . $n . '.' . bin2hex(random_bytes(3)) . '@example.test';
}

/** Insert a user quickly (cost 4 hash keeps tests fast). Returns the user id. */
function make_user(string $name = 'Test User', ?string $email = null, string $password = 'secret123'): int
{
    $st = test_pdo()->prepare('INSERT INTO users (name, email, password_hash) VALUES (:n, :e, :h) RETURNING id');
    $st->execute([':n' => $name, ':e' => $email ?: unique_email(), ':h' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4])]);
    return (int) $st->fetchColumn();
}

/** Insert a time slot at an institution, $hours from now (negative = in the past). Returns the slot id. */
function make_slot(int $institutionId, float $hours = 48, int $capacity = 2): int
{
    static $n = 0;
    $n++;
    $st = test_pdo()->prepare(
        "INSERT INTO time_slots (institution_id, starts_at, capacity)
         VALUES (:i, now() + (:h * INTERVAL '1 hour') + (:n * INTERVAL '1 second'), :c) RETURNING id"
    );
    $st->execute([':i' => $institutionId, ':h' => $hours, ':n' => $n, ':c' => $capacity]);
    return (int) $st->fetchColumn();
}

function service_id_by_name(string $name): int
{
    $st = test_pdo()->prepare('SELECT id FROM services WHERE name = :n');
    $st->execute([':n' => $name]);
    return (int) $st->fetchColumn();
}

function institution_of_service(int $serviceId): int
{
    $st = test_pdo()->prepare('SELECT institution_id FROM services WHERE id = :s');
    $st->execute([':s' => $serviceId]);
    return (int) $st->fetchColumn();
}

function appointment_status(int $id): string
{
    $st = test_pdo()->prepare('SELECT status FROM appointments WHERE id = :id');
    $st->execute([':id' => $id]);
    return (string) $st->fetchColumn();
}
