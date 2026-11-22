<?php
namespace PassGo\Repository;

use PDO;

class AppointmentRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private const SELECT = "SELECT a.id, a.user_id, a.ticket_code, a.status, a.created_at, a.cancelled_at,
               a.service_id, a.slot_id, t.starts_at, (t.starts_at > now()) AS is_upcoming,
               s.name AS service_name, s.estimated_minutes,
               i.name AS institution_name, i.address, i.city, i.opening_hours, i.phone
          FROM appointments a
          JOIN time_slots t   ON t.id = a.slot_id
          JOIN services s     ON s.id = a.service_id
          JOIN institutions i ON i.id = t.institution_id";

    private function normalize(array $row): array
    {
        $row['is_upcoming'] = ($row['is_upcoming'] === true || $row['is_upcoming'] === 't' || $row['is_upcoming'] === '1');
        return $row;
    }

    /** Booked appointments whose time has passed become "completed". */
    public function markPastCompleted(int $userId): void
    {
        $st = $this->pdo->prepare(
            "UPDATE appointments SET status = 'completed'
              WHERE user_id = :u AND status = 'booked'
                AND slot_id IN (SELECT id FROM time_slots WHERE starts_at <= now())"
        );
        $st->execute([':u' => $userId]);
    }

    /** Only returns the appointment if it belongs to the given user. */
    public function findForUser(int $id, int $userId): ?array
    {
        $st = $this->pdo->prepare(self::SELECT . ' WHERE a.id = :id AND a.user_id = :u');
        $st->execute([':id' => $id, ':u' => $userId]);
        $row = $st->fetch();
        return $row ? $this->normalize($row) : null;
    }

    /** Upcoming = booked and in the future. Past = everything else. */
    public function listForUser(int $userId, bool $upcoming): array
    {
        $cond = $upcoming
            ? "a.status = 'booked' AND t.starts_at > now()"
            : "NOT (a.status = 'booked' AND t.starts_at > now())";
        $order = $upcoming ? 'ASC' : 'DESC';
        $st = $this->pdo->prepare(self::SELECT . " WHERE a.user_id = :u AND $cond ORDER BY t.starts_at $order");
        $st->execute([':u' => $userId]);
        return array_map([$this, 'normalize'], $st->fetchAll());
    }

    public function nextUpcoming(int $userId): ?array
    {
        $list = $this->listForUser($userId, true);
        return $list ? $list[0] : null;
    }

    public function countBookedInSlot(int $slotId): int
    {
        $st = $this->pdo->prepare("SELECT count(*) FROM appointments WHERE slot_id = :s AND status = 'booked'");
        $st->execute([':s' => $slotId]);
        return (int) $st->fetchColumn();
    }

    public function hasActiveForService(int $userId, int $serviceId): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM appointments WHERE user_id = :u AND service_id = :s AND status = 'booked'");
        $st->execute([':u' => $userId, ':s' => $serviceId]);
        return (bool) $st->fetchColumn();
    }

    public function hasActiveInSlot(int $userId, int $slotId): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM appointments WHERE user_id = :u AND slot_id = :s AND status = 'booked'");
        $st->execute([':u' => $userId, ':s' => $slotId]);
        return (bool) $st->fetchColumn();
    }

    public function codeExists(string $code): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM appointments WHERE ticket_code = :c');
        $st->execute([':c' => $code]);
        return (bool) $st->fetchColumn();
    }

    public function create(int $userId, int $serviceId, int $slotId, string $ticketCode): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO appointments (user_id, service_id, slot_id, ticket_code) VALUES (:u, :s, :t, :c) RETURNING id'
        );
        $st->execute([':u' => $userId, ':s' => $serviceId, ':t' => $slotId, ':c' => $ticketCode]);
        return (int) $st->fetchColumn();
    }

    /** Lock one appointment row (inside a transaction) and return it with slot timing. */
    public function lockForUser(int $id, int $userId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT a.id, a.status, (t.starts_at > now()) AS is_upcoming
               FROM appointments a JOIN time_slots t ON t.id = a.slot_id
              WHERE a.id = :id AND a.user_id = :u FOR UPDATE OF a'
        );
        $st->execute([':id' => $id, ':u' => $userId]);
        $row = $st->fetch();
        return $row ? $this->normalize($row) : null;
    }

    public function markCancelled(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE appointments SET status = 'cancelled', cancelled_at = now() WHERE id = :id");
        $st->execute([':id' => $id]);
    }
}
