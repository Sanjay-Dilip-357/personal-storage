<?php
/**
 * PERSONAL STORAGE — Storage Settings Repository
 * Reads/writes global and per-user settings from DB.
 */

declare(strict_types=1);

class StorageSettingsRepository extends Repository
{
    protected string $table = 'storage_settings';

    /**
     * Get a global setting value with type casting.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $row = $this->findBy('setting_key', $key);
        if (!$row) {
            return $default;
        }

        return match ($row['setting_type']) {
            'int'    => (int)$row['setting_value'],
            'bool'   => in_array(strtolower($row['setting_value']), ['1', 'true', 'yes', 'on'], true),
            'json'   => json_decode($row['setting_value'], true) ?? [],
            default  => $row['setting_value'],
        };
    }

    /**
     * Update a global setting.
     */
    public function updateSetting(string $key, string $value): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE `storage_settings` SET `setting_value` = :val WHERE `setting_key` = :key'
        );
        return $stmt->execute([':val' => $value, ':key' => $key]);
    }

    /**
     * Get a user-specific override, falling back to global.
     * Resolution: user override → global → default
     */
    public function getUserSetting(int $userId, string $key, mixed $default = null): mixed
    {
        // Check user override first
        $stmt = $this->db->prepare(
            'SELECT `setting_value` FROM `user_storage_settings`
             WHERE `user_id` = :uid AND `setting_key` = :key LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':key' => $key]);
        $row = $stmt->fetch();

        if ($row) {
            // Determine type from global setting
            $globalRow = $this->findBy('setting_key', $key);
            $type = $globalRow ? $globalRow['setting_type'] : 'string';

            return match ($type) {
                'int'    => (int)$row['setting_value'],
                'bool'   => in_array(strtolower($row['setting_value']), ['1', 'true', 'yes', 'on'], true),
                'json'   => json_decode($row['setting_value'], true) ?? [],
                default  => $row['setting_value'],
            };
        }

        // Fall back to global
        return $this->getSetting($key, $default);
    }

    /**
     * Set a user-specific override.
     */
    public function setUserSetting(int $userId, string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `user_storage_settings` (`user_id`, `setting_key`, `setting_value`)
             VALUES (:uid, :key, :val)
             ON DUPLICATE KEY UPDATE `setting_value` = :val2'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':key'  => $key,
            ':val'  => $value,
            ':val2' => $value,
        ]);
    }

    /**
     * Remove a user-specific override (revert to global).
     */
    public function clearUserSetting(int $userId, string $key): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM `user_storage_settings` WHERE `user_id` = :uid AND `setting_key` = :key'
        );
        $stmt->execute([':uid' => $userId, ':key' => $key]);
    }
}