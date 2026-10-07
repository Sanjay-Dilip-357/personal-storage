<?php
/**
 * PERSONAL STORAGE — Note Repository
 * Full CRUD with ownership enforcement and soft-delete support.
 */

declare(strict_types=1);

class NoteRepository extends Repository
{
    protected string $table = 'notes';

    /**
     * Get paginated, searchable notes for a user (active only).
     */
    public function getUserNotes(
        int    $userId,
        string $search = '',
        string $sort = 'updated_at',
        string $order = 'DESC',
        int    $limit = 50,
        int    $offset = 0
    ): array {
        // Whitelist sort columns to prevent SQL injection
        $allowedSorts = ['created_at', 'updated_at', 'title'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'updated_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT `id`, `user_id`, `title`, `content`, `created_at`, `updated_at`
                FROM `notes`
                WHERE `user_id` = :uid AND `deleted_at` IS NULL";
        $params = [':uid' => $userId];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (`title` LIKE :search_title OR `content` LIKE :search_content)";
            $params[':search_title']   = $like;
            $params[':search_content'] = $like;
        }

        $sql .= " ORDER BY `{$sort}` {$order} LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count active notes for a user (with optional search).
     */
    public function getUserNoteCount(int $userId, string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM `notes`
                WHERE `user_id` = :uid AND `deleted_at` IS NULL";
        $params = [':uid' => $userId];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (`title` LIKE :search_title OR `content` LIKE :search_content)";
            $params[':search_title']   = $like;
            $params[':search_content'] = $like;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Get a single active note with ownership verification.
     */
    public function getUserNoteById(int $userId, int $noteId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `notes`
             WHERE `id` = :nid AND `user_id` = :uid AND `deleted_at` IS NULL
             LIMIT 1'
        );
        $stmt->execute([':nid' => $noteId, ':uid' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Create a new note. Returns the new note ID.
     */
    public function createNote(int $userId, ?string $title, string $content): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `notes` (`user_id`, `title`, `content`)
             VALUES (:uid, :title, :content)'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':title'   => ($title !== null && trim($title) !== '') ? trim($title) : null,
            ':content' => $content,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing note with ownership check.
     */
    public function updateNote(int $userId, int $noteId, ?string $title, string $content): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE `notes`
             SET `title` = :title, `content` = :content
             WHERE `id` = :nid AND `user_id` = :uid AND `deleted_at` IS NULL'
        );
        return $stmt->execute([
            ':title'   => ($title !== null && trim($title) !== '') ? trim($title) : null,
            ':content' => $content,
            ':nid'     => $noteId,
            ':uid'     => $userId,
        ]);
    }

    /**
     * Soft-delete a note (move to recycle bin).
     * Expiry date is calculated in PHP to avoid INTERVAL parameterization bugs.
     */
    public function softDeleteNote(int $userId, int $noteId, int $recycleBinDays = 7): bool
    {
        $now    = date('Y-m-d H:i:s');
        $expiry = date('Y-m-d H:i:s', time() + ($recycleBinDays * 86400));

        $stmt = $this->db->prepare(
            'UPDATE `notes`
             SET `deleted_at` = :now, `deletion_expiry_at` = :expiry
             WHERE `id` = :nid AND `user_id` = :uid AND `deleted_at` IS NULL'
        );
        return $stmt->execute([
            ':now'    => $now,
            ':expiry' => $expiry,
            ':nid'    => $noteId,
            ':uid'    => $userId,
        ]);
    }

    /**
     * Get count of active notes.
     */
    public function getUserActiveNoteCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `notes` WHERE `user_id` = :uid AND `deleted_at` IS NULL'
        );
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get count of notes in recycle bin.
     */
    public function getUserRecycleBinNoteCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `notes` WHERE `user_id` = :uid AND `deleted_at` IS NOT NULL'
        );
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Calculate total byte size of notes.
     */
    public function getUserNotesStorageBytes(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(OCTET_LENGTH(content) + OCTET_LENGTH(COALESCE(title, ""))), 0)
             FROM `notes`
             WHERE `user_id` = :uid AND `deleted_at` IS NULL'
        );
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get recent notes for dashboard.
     */
    public function getRecentNotes(int $userId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `notes`
             WHERE `user_id` = :uid AND `deleted_at` IS NULL
             ORDER BY `updated_at` DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}