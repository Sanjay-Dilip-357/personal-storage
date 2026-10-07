<?php
/**
 * PERSONAL STORAGE — File Repository
 * Enforces unified queries to merge Files and Notes inside "All Files" list.
 */

declare(strict_types=1);

class FileRepository extends Repository
{
    protected string $table = 'files';

    /**
     * List active files for a user. If category is empty, dynamically unions notes too.
     */
    public function getUserFiles(
        int    $userId,
        string $search = '',
        string $category = '',
        string $sort = 'created_at',
        string $order = 'DESC',
        int    $limit = 50,
        int    $offset = 0
    ): array {
        $allowedSorts = ['created_at', 'updated_at', 'original_name', 'size_bytes', 'category'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $params = [];

        // If a specific category filter is requested, query ONLY the files table
        if ($category !== '' && in_array($category, ['document', 'image', 'video', 'other'], true)) {
            $sql = "SELECT `id`, `user_id`, `original_name`, `mime_type`, `extension`,
                           `category`, `size_bytes`, `created_at`, `updated_at`, 'file' AS `item_type`
                    FROM `files`
                    WHERE `user_id` = :uid AND `deleted_at` IS NULL AND `category` = :cat";
            $params[':uid'] = $userId;
            $params[':cat'] = $category;

            if ($search !== '') {
                $sql .= " AND `original_name` LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            $finalSql = "SELECT * FROM ({$sql}) AS `results` ORDER BY `{$sort}` {$order} LIMIT :lim OFFSET :off";
        } else {
            // UNIFIED VIEW: Union active Files and active Notes together
            $fileQuery = "SELECT `id`, `user_id`, `original_name`, `mime_type`, `extension`,
                                 `category`, `size_bytes`, `created_at`, `updated_at`, 'file' AS `item_type`
                          FROM `files`
                          WHERE `user_id` = :uid_f AND `deleted_at` IS NULL";
            $params[':uid_f'] = $userId;

            if ($search !== '') {
                $fileQuery .= " AND `original_name` LIKE :search_f";
                $params[':search_f'] = '%' . $search . '%';
            }

            $noteQuery = "SELECT `id`, `user_id`, COALESCE(`title`, 'Untitled Note') AS `original_name`, 'text/plain' AS `mime_type`, 'note' AS `extension`,
                                 'document' AS `category`, OCTET_LENGTH(`content`) AS `size_bytes`, `created_at`, `updated_at`, 'note' AS `item_type`
                          FROM `notes`
                          WHERE `user_id` = :uid_n AND `deleted_at` IS NULL";
            $params[':uid_n'] = $userId;

            if ($search !== '') {
                $noteQuery .= " AND (COALESCE(`title`, 'Untitled Note') LIKE :search_n1 OR `content` LIKE :search_n2)";
                $params[':search_n1'] = '%' . $search . '%';
                $params[':search_n2'] = '%' . $search . '%';
            }

            $finalSql = "SELECT * FROM ({$fileQuery} UNION ALL {$noteQuery}) AS `unified` ORDER BY `{$sort}` {$order} LIMIT :lim OFFSET :off";
        }

        $stmt = $this->db->prepare($finalSql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count active items (unioned if no category filter is specified).
     */
    public function getUserFileCount(int $userId, string $search = '', string $category = ''): int
    {
        $params = [];

        if ($category !== '' && in_array($category, ['document', 'image', 'video', 'other'], true)) {
            $sql = "SELECT COUNT(*) FROM `files` WHERE `user_id` = :uid AND `deleted_at` IS NULL AND `category` = :cat";
            $params[':uid'] = $userId;
            $params[':cat'] = $category;

            if ($search !== '') {
                $sql .= " AND `original_name` LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
        } else {
            // Count combined Files + Notes
            $fileSql = "SELECT COUNT(*) FROM `files` WHERE `user_id` = :uid_f AND `deleted_at` IS NULL";
            $params[':uid_f'] = $userId;
            if ($search !== '') {
                $fileSql .= " AND `original_name` LIKE :search_f";
                $params[':search_f'] = '%' . $search . '%';
            }

            $noteSql = "SELECT COUNT(*) FROM `notes` WHERE `user_id` = :uid_n AND `deleted_at` IS NULL";
            $params[':uid_n'] = $userId;
            if ($search !== '') {
                $noteSql .= " AND (COALESCE(`title`, 'Untitled Note') LIKE :search_n1 OR `content` LIKE :search_n2)";
                $params[':search_n1'] = '%' . $search . '%';
                $params[':search_n2'] = '%' . $search . '%';
            }

            $sql = "SELECT (($fileSql) + ($noteSql))";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    // ── Rest of class holds standard FileRepository operations ──
    public function getUserStorageUsed(int $userId): int {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM `files` WHERE `user_id` = :uid');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
    public function getUserActiveFileCount(int $userId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `files` WHERE `user_id` = :uid AND `deleted_at` IS NULL');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
    public function getUserRecycleBinFileCount(int $userId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `files` WHERE `user_id` = :uid AND `deleted_at` IS NOT NULL');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
    public function getUserCategoryStats(int $userId): array {
        $stmt = $this->db->prepare('SELECT `category`, COUNT(*) as total_count, COALESCE(SUM(size_bytes), 0) as total_size FROM `files` WHERE `user_id` = :uid AND `deleted_at` IS NULL GROUP BY `category`');
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
        $stats = ['document' => ['count' => 0, 'size' => 0], 'image' => ['count' => 0, 'size' => 0], 'video' => ['count' => 0, 'size' => 0], 'other' => ['count' => 0, 'size' => 0]];
        foreach ($rows as $row) {
            $cat = $row['category'];
            if (isset($stats[$cat])) {
                $stats[$cat]['count'] = (int)$row['total_count'];
                $stats[$cat]['size']  = (int)$row['total_size'];
            }
        }
        return $stats;
    }
    public function getRecentFiles(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare('SELECT * FROM `files` WHERE `user_id` = :uid AND `deleted_at` IS NULL ORDER BY `created_at` DESC LIMIT :lim');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function getUserFileById(int $userId, int $fileId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM `files` WHERE `id` = :fid AND `user_id` = :uid AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute([':fid' => $fileId, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public function createFileRecord(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO `files` (`user_id`, `original_name`, `stored_name`, `storage_path`, `mime_type`, `extension`, `category`, `size_bytes`) VALUES (:uid, :orig, :stored, :path, :mime, :ext, :cat, :size)');
        $stmt->execute([':uid' => $data['user_id'], ':orig' => $data['original_name'], ':stored' => $data['stored_name'], ':path' => $data['storage_path'], ':mime' => $data['mime_type'], ':ext' => $data['extension'], ':cat' => $data['category'], ':size' => $data['size_bytes']]);
        return (int)$this->db->lastInsertId();
    }
    public function renameFile(int $userId, int $fileId, string $newName): bool {
        $stmt = $this->db->prepare('UPDATE `files` SET `original_name` = :name WHERE `id` = :fid AND `user_id` = :uid AND `deleted_at` IS NULL');
        return $stmt->execute([':name' => $newName, ':fid' => $fileId, ':uid' => $userId]);
    }
    public function softDeleteFile(int $userId, int $fileId, int $recycleBinDays = 7): bool {
        $now    = date('Y-m-d H:i:s');
        $expiry = date('Y-m-d H:i:s', time() + ($recycleBinDays * 86400));
        $stmt = $this->db->prepare('UPDATE `files` SET `deleted_at` = :now, `deletion_expiry_at` = :expiry WHERE `id` = :fid AND `user_id` = :uid AND `deleted_at` IS NULL');
        return $stmt->execute([':now' => $now, ':expiry' => $expiry, ':fid' => $fileId, ':uid' => $userId]);
    }
    public function getUserCategoryFileCount(int $userId, string $category): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `files` WHERE `user_id` = :uid AND `category` = :cat AND `deleted_at` IS NULL');
        $stmt->execute([':uid' => $userId, ':cat' => $category]);
        return (int)$stmt->fetchColumn();
    }
}