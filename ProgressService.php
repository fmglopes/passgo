<?php
namespace PassGo\Service;

use PassGo\Repository\DocumentRepository;
use PassGo\Repository\GuideRepository;
use PDO;

/** Personal progress: documents ticked and guide steps completed. */
class ProgressService
{
    private $guide;
    private $documents;

    public function __construct(PDO $pdo)
    {
        $this->guide = new GuideRepository($pdo);
        $this->documents = new DocumentRepository($pdo);
    }

    /**
     * Add a "done" flag to each step and compute totals. Pure function (no database).
     * The first step that is not done is flagged as "next".
     */
    public static function summarizeSteps(array $steps, array $doneIds): array
    {
        $total = count($steps);
        $doneIds = array_map('intval', $doneIds);
        $done = 0;
        $nextId = null;
        foreach ($steps as $i => $step) {
            $isDone = in_array((int) $step['id'], $doneIds, true);
            $steps[$i]['done'] = $isDone;
            if ($isDone) {
                $done++;
            } elseif ($nextId === null) {
                $nextId = (int) $step['id'];
            }
        }
        foreach ($steps as $i => $step) {
            $steps[$i]['is_next'] = ($nextId !== null && (int) $step['id'] === $nextId);
        }
        return [
            'steps'    => $steps,
            'done'     => $done,
            'total'    => $total,
            'percent'  => $total > 0 ? (int) round($done * 100 / $total) : 0,
            'complete' => $total > 0 && $done === $total,
        ];
    }

    /** Add a "checked" flag to each document and count the required ones that are ready. Pure function. */
    public static function summarizeDocuments(array $docs, array $checkedIds): array
    {
        $checkedIds = array_map('intval', $checkedIds);
        $requiredTotal = 0;
        $requiredReady = 0;
        $missing = [];
        foreach ($docs as $i => $doc) {
            $checked = in_array((int) $doc['id'], $checkedIds, true);
            $docs[$i]['checked'] = $checked;
            $required = ($doc['is_required'] === true || $doc['is_required'] === 't' || $doc['is_required'] === '1');
            if ($required) {
                $requiredTotal++;
                if ($checked) {
                    $requiredReady++;
                } else {
                    $missing[] = $doc;
                }
            }
        }
        return [
            'documents'      => $docs,
            'required_total' => $requiredTotal,
            'required_ready' => $requiredReady,
            'missing'        => $missing,
            'all_ready'      => $requiredTotal > 0 && $requiredReady === $requiredTotal,
        ];
    }

    public function stepProgress(int $userId, int $serviceId): array
    {
        return self::summarizeSteps($this->guide->steps($serviceId), $this->guide->doneIds($userId, $serviceId));
    }

    public function documentProgress(int $userId, int $serviceId): array
    {
        return self::summarizeDocuments(
            $this->documents->forService($serviceId),
            $this->documents->checkedIds($userId, $serviceId)
        );
    }

    /** Mark a step done or not done. Returns false when the step does not belong to the service. */
    public function setStepDone(int $userId, int $serviceId, int $stepId, bool $done): bool
    {
        if (!$this->guide->stepBelongsToService($stepId, $serviceId)) {
            return false;
        }
        $this->guide->setDone($userId, $stepId, $done);
        return true;
    }

    public function saveDocumentChecks(int $userId, int $serviceId, array $documentIds): void
    {
        $this->documents->replaceChecks($userId, $serviceId, $documentIds);
    }
}
