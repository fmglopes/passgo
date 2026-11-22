<?php
namespace PassGo\Repository;

use PDO;

class SlotRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Future slots (next 14 days) at the institution that offers the service,
     * with how many places are already taken.
     */
    public function availableFor(int $serviceId): array
    {
        $st = $this->pdo->prepare(
            "SELECT t.id, t.starts_at, t.capacity,
                    (SELECT count(*) FROM appointments a WHERE a.slot_id = t.id AND a.status = 'booked') AS taken
               FROM time_slots t
               JOIN services s ON s.institution_id = t.institution_id
              WHERE s.id = :sid
                AND t.starts_at > now()
                AND t.starts_at < now() + INTERVAL '14 days'
              ORDER BY t.starts_at"
        );
        $st->execute([':sid' => $serviceId]);
        return $st->fetchAll();
    }

    /**
     * Lock one slot row until the end of the transaction, so two people cannot
     * take the last place at the same time. Must be called inside a transaction.
     */
    public function lock(int $slotId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, institution_id, starts_at, capacity, (starts_at <= now()) AS is_past
               FROM time_slots WHERE id = :id FOR UPDATE'
        );
        $st->execute([':id' => $slotId]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $row['is_past'] = ($row['is_past'] === true || $row['is_past'] === 't' || $row['is_past'] === '1');
        return $row;
    }
}
