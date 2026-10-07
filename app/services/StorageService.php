<?php
/**
 * PERSONAL STORAGE — Storage Calculation Service
 * Computes storage metrics, per-user limits, and usage percentages.
 */

declare(strict_types=1);

class StorageService
{
    private FileRepository $fileRepo;
    private NoteRepository $noteRepo;
    private StorageSettingsRepository $settingsRepo;

    public function __construct()
    {
        $this->fileRepo     = new FileRepository();
        $this->noteRepo     = new NoteRepository();
        $this->settingsRepo = new StorageSettingsRepository();
    }

    /**
     * Get complete storage and activity metrics for user dashboard.
     */
    public function getUserDashboardMetrics(int $userId): array
    {
        // 1. Quota resolution: User override -> Global DB setting -> Application Fallback (10GB)
        $quotaBytes = (int)$this->settingsRepo->getUserSetting($userId, 'default_storage_quota', 10737418240);

        // 2. Storage used by files (includes soft-deleted to prevent quota bypass)
        $filesUsedBytes = $this->fileRepo->getUserStorageUsed($userId);

        // 3. Optional note storage calculation
        $notesCountTowardStorage = (bool)$this->settingsRepo->getSetting('notes_count_toward_storage', false);
        $notesUsedBytes = $notesCountTowardStorage ? $this->noteRepo->getUserNotesStorageBytes($userId) : 0;

        $totalUsedBytes = $filesUsedBytes + $notesUsedBytes;
        $remainingBytes = max(0, $quotaBytes - $totalUsedBytes);
        $percentageUsed = ($quotaBytes > 0) ? min(100, round(($totalUsedBytes / $quotaBytes) * 100, 1)) : 0;

        // 4. Counts
        $activeFilesCount = $this->fileRepo->getUserActiveFileCount($userId);
        $activeNotesCount = $this->noteRepo->getUserActiveNoteCount($userId);
        $recycleFilesCount = $this->fileRepo->getUserRecycleBinFileCount($userId);
        $recycleNotesCount = $this->noteRepo->getUserRecycleBinNoteCount($userId);
        $totalRecycleBinCount = $recycleFilesCount + $recycleNotesCount;

        // 5. Category breakdown
        $categoryStats = $this->fileRepo->getUserCategoryStats($userId);

        return [
            'quota_bytes'         => $quotaBytes,
            'quota_formatted'     => formatBytes($quotaBytes),
            'used_bytes'          => $totalUsedBytes,
            'used_formatted'      => formatBytes($totalUsedBytes),
            'remaining_bytes'     => $remainingBytes,
            'remaining_formatted' => formatBytes($remainingBytes),
            'percentage_used'     => $percentageUsed,
            'total_files'         => $activeFilesCount,
            'total_notes'         => $activeNotesCount,
            'recycle_bin_count'   => $totalRecycleBinCount,
            'categories'          => $categoryStats,
        ];
    }
}