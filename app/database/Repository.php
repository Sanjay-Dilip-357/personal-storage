<?php
/**
 * ==============================================
 * PERSONAL STORAGE — Base Repository
 * ==============================================
 * Provides clean query methods so we never
 * scatter raw SQL across controllers.
 */

declare(strict_types=1);

abstract class Repository
{
    protected PDO $db;
    protected string $table;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Find a single record by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find a single record by a specific column.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$column}` = :value LIMIT 1");
        $stmt->execute([':value' => $value]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find all records matching conditions.
     *
     * @param array  $conditions  ['column' => 'value']
     * @param string $orderBy     e.g., 'created_at DESC'
     * @param int    $limit
     * @param int    $offset
     */
    public function findWhere(
        array  $conditions = [],
        string $orderBy = 'id DESC',
        int    $limit = 50,
        int    $offset = 0
    ): array {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                if ($val === null) {
                    $clauses[] = "`{$col}` IS NULL";
                } else {
                    $paramKey = ':w_' . $col;
                    $clauses[] = "`{$col}` = {$paramKey}";
                    $params[$paramKey] = $val;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        // Sanitize ORDER BY (whitelist approach)
        $allowedDirections = ['ASC', 'DESC'];
        $parts = explode(' ', trim($orderBy));
        $dir = strtoupper(end($parts));
        if (!in_array($dir, $allowedDirections, true)) {
            $dir = 'DESC';
        }
        $sql .= " ORDER BY {$orderBy}";
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Insert a record and return the new ID.
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $this->table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update a record by ID.
     */
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        foreach (array_keys($data) as $col) {
            $setClauses[] = "`{$col}` = :{$col}";
        }

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `id` = :id",
            $this->table,
            implode(', ', $setClauses)
        );

        $data[':id'] = $id;
        // Re-key data with colons for binding
        $params = [];
        foreach ($data as $key => $val) {
            $params[str_starts_with($key, ':') ? $key : ":{$key}"] = $val;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a record by ID (hard delete).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Count records matching conditions.
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                if ($val === null) {
                    $clauses[] = "`{$col}` IS NULL";
                } else {
                    $paramKey = ':c_' . $col;
                    $clauses[] = "`{$col}` = {$paramKey}";
                    $params[$paramKey] = $val;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if a record exists.
     */
    public function exists(string $column, mixed $value, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `{$column}` = :value";
        $params = [':value' => $value];

        if ($excludeId !== null) {
            $sql .= " AND `id` != :exclude";
            $params[':exclude'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Execute a raw prepared query (for complex joins/aggregates).
     * Use sparingly — prefer specific repository methods.
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a raw prepared statement that doesn't return rows.
     */
    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}