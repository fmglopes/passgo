<?php
namespace PassGo\Repository;

use PDO;

class ServiceRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function categories(): array
    {
        return $this->pdo->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();
    }

    /** Escape LIKE wildcards so a user typing % or _ searches for the literal character. */
    public static function likePattern(string $q): string
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }

    /**
     * Search services by keyword (name, summary, institution) and optional category.
     * The SQL text only contains fixed fragments; user input is always bound.
     */
    public function search(string $q = '', ?int $categoryId = null): array
    {
        $sql = 'SELECT s.id, s.name, s.summary, s.estimated_minutes, s.requires_appointment,
                       c.id AS category_id, c.name AS category_name, i.name AS institution_name
                  FROM services s
                  JOIN categories c   ON c.id = s.category_id
                  JOIN institutions i ON i.id = s.institution_id
                 WHERE TRUE';
        $params = [];
        $q = trim($q);
        if ($q !== '') {
            $sql .= ' AND (s.name ILIKE :q1 OR s.summary ILIKE :q2 OR i.name ILIKE :q3)';
            $like = self::likePattern($q);
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }
        if ($categoryId !== null) {
            $sql .= ' AND s.category_id = :cat';
            $params[':cat'] = $categoryId;
        }
        $sql .= ' ORDER BY s.name';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT s.id, s.name, s.summary, s.description, s.estimated_minutes, s.requires_appointment,
                    s.institution_id, c.id AS category_id, c.name AS category_name,
                    i.name AS institution_name, i.address, i.city, i.phone, i.opening_hours, i.website
               FROM services s
               JOIN categories c   ON c.id = s.category_id
               JOIN institutions i ON i.id = s.institution_id
              WHERE s.id = :id'
        );
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    }

    /** A few services to show on the home page. */
    public function popular(int $limit = 4): array
    {
        $st = $this->pdo->prepare(
            'SELECT s.id, s.name, s.summary, s.estimated_minutes, s.requires_appointment,
                    c.id AS category_id, c.name AS category_name, i.name AS institution_name
               FROM services s
               JOIN categories c   ON c.id = s.category_id
               JOIN institutions i ON i.id = s.institution_id
              ORDER BY s.id
              LIMIT :lim'
        );
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}
