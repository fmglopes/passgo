<?php
namespace PassGo\Repository;

use PDO;

class DocumentRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Required documents of a service (required ones first). */
    public function forService(int $serviceId): array
    {
        $st = $this->pdo->prepare(
            'SELECT d.id, d.name, d.description, sd.is_required, sd.note
               FROM service_documents sd
               JOIN documents d ON d.id = sd.document_id
              WHERE sd.service_id = :sid
              ORDER BY sd.is_required DESC, d.name'
        );
        $st->execute([':sid' => $serviceId]);
        return $st->fetchAll();
    }

    /** Ids of the documents a user has ticked for a service. */
    public function checkedIds(int $userId, int $serviceId): array
    {
        $st = $this->pdo->prepare('SELECT document_id FROM user_document_checks WHERE user_id = :u AND service_id = :s');
        $st->execute([':u' => $userId, ':s' => $serviceId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Replace the user's ticked documents for a service with exactly this list.
     * Ids that do not belong to the service are ignored.
     */
    public function replaceChecks(int $userId, int $serviceId, array $documentIds): void
    {
        $valid = array_map(function ($d) {
            return (int) $d['id'];
        }, $this->forService($serviceId));
        $wanted = array_values(array_intersect(array_map('intval', $documentIds), $valid));

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM user_document_checks WHERE user_id = :u AND service_id = :s');
            $del->execute([':u' => $userId, ':s' => $serviceId]);
            $ins = $this->pdo->prepare('INSERT INTO user_document_checks (user_id, service_id, document_id) VALUES (:u, :s, :d)');
            foreach ($wanted as $docId) {
                $ins->execute([':u' => $userId, ':s' => $serviceId, ':d' => $docId]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
