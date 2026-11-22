<?php
namespace PassGo\Repository;

use PDO;

class GuideRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function steps(int $serviceId): array
    {
        $st = $this->pdo->prepare('SELECT id, position, title, description FROM guide_steps WHERE service_id = :s ORDER BY position');
        $st->execute([':s' => $serviceId]);
        return $st->fetchAll();
    }

    /** Ids of the steps a user has completed for one service. */
    public function doneIds(int $userId, int $serviceId): array
    {
        $st = $this->pdo->prepare(
            'SELECT p.step_id FROM user_step_progress p
               JOIN guide_steps g ON g.id = p.step_id
              WHERE p.user_id = :u AND g.service_id = :s'
        );
        $st->execute([':u' => $userId, ':s' => $serviceId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** True when the step exists and belongs to the service. */
    public function stepBelongsToService(int $stepId, int $serviceId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM guide_steps WHERE id = :id AND service_id = :s');
        $st->execute([':id' => $stepId, ':s' => $serviceId]);
        return (bool) $st->fetchColumn();
    }

    public function setDone(int $userId, int $stepId, bool $done): void
    {
        if ($done) {
            $st = $this->pdo->prepare('INSERT INTO user_step_progress (user_id, step_id) VALUES (:u, :s) ON CONFLICT DO NOTHING');
        } else {
            $st = $this->pdo->prepare('DELETE FROM user_step_progress WHERE user_id = :u AND step_id = :s');
        }
        $st->execute([':u' => $userId, ':s' => $stepId]);
    }
}
