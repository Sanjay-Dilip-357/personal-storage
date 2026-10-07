<?php
/**
 * PERSONAL STORAGE — User Repository
 * All user-related database queries.
 */

declare(strict_types=1);

class UserRepository extends Repository
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', mb_strtolower(trim($email)));
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        return $this->exists('email', mb_strtolower(trim($email)), $excludeId);
    }

    public function create(array $data): int
    {
        $data['email'] = mb_strtolower(trim($data['email']));
        return $this->insert($data);
    }

    public function updateLastLogin(int $userId): void
    {
        $this->update($userId, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        return $this->update($userId, ['password_hash' => $hash]);
    }

    public function activateAccount(int $userId): bool
    {
        return $this->update($userId, [
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Email Verifications ──────────────────

    public function createEmailVerification(int $userId, string $otpHash, int $expirySeconds): int
    {
        // Compute date in PHP to avoid native PDO binding errors inside MySQL INTERVAL functions
        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);

        $stmt = $this->db->prepare(
            'INSERT INTO `email_verifications` (`user_id`, `otp_hash`, `expires_at`)
             VALUES (:user_id, :otp_hash, :expires_at)'
        );
        $stmt->execute([
            ':user_id'  => $userId,
            ':otp_hash' => $otpHash,
            ':expires_at' => $expiresAt,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getLatestVerification(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `email_verifications`
             WHERE `user_id` = :uid AND `used` = 0
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markVerificationUsed(int $verificationId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `email_verifications` SET `used` = 1 WHERE `id` = :id'
        );
        $stmt->execute([':id' => $verificationId]);
    }

    public function incrementVerificationAttempts(int $verificationId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE `email_verifications` SET `attempts` = `attempts` + 1 WHERE `id` = :id'
        );
        $stmt->execute([':id' => $verificationId]);

        $stmt = $this->db->prepare('SELECT `attempts` FROM `email_verifications` WHERE `id` = :id');
        $stmt->execute([':id' => $verificationId]);
        return (int)$stmt->fetchColumn();
    }

    public function invalidateOldVerifications(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `email_verifications` SET `used` = 1 WHERE `user_id` = :uid AND `used` = 0'
        );
        $stmt->execute([':uid' => $userId]);
    }

    // ── Password Resets ──────────────────────

    public function createPasswordReset(int $userId, string $otpHash, int $expirySeconds): int
    {
        // Compute date in PHP
        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);

        $stmt = $this->db->prepare(
            'INSERT INTO `password_resets` (`user_id`, `otp_hash`, `expires_at`)
             VALUES (:user_id, :otp_hash, :expires_at)'
        );
        $stmt->execute([
            ':user_id'  => $userId,
            ':otp_hash' => $otpHash,
            ':expires_at' => $expiresAt,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getLatestPasswordReset(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `password_resets`
             WHERE `user_id` = :uid AND `used` = 0
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markPasswordResetUsed(int $resetId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `password_resets` SET `used` = 1 WHERE `id` = :id'
        );
        $stmt->execute([':id' => $resetId]);
    }

    public function incrementResetAttempts(int $resetId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE `password_resets` SET `attempts` = `attempts` + 1 WHERE `id` = :id'
        );
        $stmt->execute([':id' => $resetId]);

        $stmt = $this->db->prepare('SELECT `attempts` FROM `password_resets` WHERE `id` = :id');
        $stmt->execute([':id' => $resetId]);
        return (int)$stmt->fetchColumn();
    }

    public function invalidateOldResets(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `password_resets` SET `used` = 1 WHERE `user_id` = :uid AND `used` = 0'
        );
        $stmt->execute([':uid' => $userId]);
    }
}