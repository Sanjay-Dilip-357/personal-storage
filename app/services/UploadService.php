<?php
/**
 * PERSONAL STORAGE — Upload Security Engine
 * Allows all file types (*.*) categorized into doc/image/video/other,
 * permits extensionless files, and strictly blocks dangerous server scripts.
 */

declare(strict_types=1);

class UploadService
{
    private FileRepository $fileRepo;
    private StorageSettingsRepository $settingsRepo;

    // Dangerous executable and interpretable extensions strictly blocked
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar',
        'exe', 'bat', 'cmd', 'com', 'cpl', 'dll', 'hta', 'ins', 'isp',
        'jse', 'lnk', 'msi', 'msp', 'pif', 'ps1', 'ps2', 'psc1', 'psc2',
        'reg', 'rgs', 'scr', 'sct', 'sh', 'shb', 'shs', 'vb', 'vbe',
        'vbs', 'wsc', 'wsf', 'wsh', 'ws', 'jar', 'cgi', 'pl',
        'py', 'rb', 'asp', 'aspx', 'cer', 'csr'
    ];

    public function __construct()
    {
        $this->fileRepo     = new FileRepository();
        $this->settingsRepo = new StorageSettingsRepository();
    }

    /**
     * Process a single file upload with universal fallback support.
     */
    public function processUpload(int $userId, array $fileData): array
    {
        // 1. Check upload error code
        if (!isset($fileData['error']) || $fileData['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = match ($fileData['error'] ?? -1) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed upload size.',
                UPLOAD_ERR_PARTIAL     => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE     => 'No file was selected.',
                UPLOAD_ERR_NO_TMP_DIR  => 'Server temporary directory missing.',
                UPLOAD_ERR_CANT_WRITE  => 'Failed to write file to disk.',
                default                => 'Unknown upload error occurred.',
            };
            return ['success' => false, 'message' => $errorMsg];
        }

        $tmpPath = $fileData['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            return ['success' => false, 'message' => 'Invalid file upload source.'];
        }

        // 2. Sanitize original filename
        $originalName = $this->sanitizeFilename($fileData['name'] ?? 'unnamed_file');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 3. Block malicious executable extensions only (allow empty or any other extension)
        if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => "File extension '.{$extension}' is blocked for security reasons."];
        }

        // 4. Categorize file (doc, image, video, or fallback to 'other')
        $category = $this->detectCategory($extension);

        // 5. Detect real MIME type using finfo (magic bytes inspection)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpPath);
        if ($detectedMime === false || empty($detectedMime)) {
            $detectedMime = 'application/octet-stream';
        }

        // 6. Validate file size against category limits
        $fileSize = (int)$fileData['size'];
        $maxSize = $this->getMaxSizeForCategory($category);
        if ($fileSize > $maxSize) {
            return ['success' => false, 'message' => 'File too large. Maximum size for ' . $category . 's is ' . formatBytes($maxSize) . '.'];
        }
        if ($fileSize <= 0) {
            return ['success' => false, 'message' => 'Uploaded file is empty (0 bytes).'];
        }

        // 7. Validate category count limit
        $maxCount = (int)$this->settingsRepo->getUserSetting($userId, "max_{$category}_count", 1000);
        $currentCount = $this->fileRepo->getUserCategoryFileCount($userId, $category);
        if ($currentCount >= $maxCount) {
            return ['success' => false, 'message' => "Category file limit reached ({$maxCount} {$category}s)."];
        }

        // 8. Validate total user quota
        $quotaBytes = (int)$this->settingsRepo->getUserSetting($userId, 'default_storage_quota', 10737418240);
        $currentUsage = $this->fileRepo->getUserStorageUsed($userId);
        if (($currentUsage + $fileSize) > $quotaBytes) {
            $remaining = max(0, $quotaBytes - $currentUsage);
            return ['success' => false, 'message' => 'Storage quota exceeded. Remaining space: ' . formatBytes($remaining) . '.'];
        }

        // 9. Generate randomized storage filename
        $physicalExt = ($extension !== '') ? $extension : 'dat';
        $storedName = bin2hex(random_bytes(20)) . '.' . $physicalExt;

        // 10. Build storage directory: storage/private/uploads/{user_id}/{year}/{month}/
        $year  = date('Y');
        $month = date('m');
        $relativeDir = "{$userId}/{$year}/{$month}";
        $fullDir = UPLOAD_PATH . '/' . $relativeDir;

        if (!is_dir($fullDir)) {
            if (!mkdir($fullDir, 0750, true)) {
                return ['success' => false, 'message' => 'Failed to initialize private storage directory.'];
            }
        }

        $fullPath = $fullDir . '/' . $storedName;

        // 11. Move file to private physical storage
        if (!move_uploaded_file($tmpPath, $fullPath)) {
            return ['success' => false, 'message' => 'Failed to store file on disk.'];
        }

        // 12. Record metadata in MySQL
        try {
            $fileId = $this->fileRepo->createFileRecord([
                'user_id'       => $userId,
                'original_name' => $originalName,
                'stored_name'   => $storedName,
                'storage_path'  => $relativeDir . '/' . $storedName,
                'mime_type'     => $detectedMime,
                'extension'     => $extension,
                'category'      => $category,
                'size_bytes'    => $fileSize,
            ]);

            ActivityLogger::fileUploaded($userId, $fileId, $originalName, $fileSize);

            return [
                'success' => true,
                'message' => 'File uploaded successfully.',
                'file_id' => $fileId,
            ];
        } catch (\Throwable $e) {
            @unlink($fullPath); // Rollback file on DB error
            appLog('Upload DB Record Error: ' . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Failed to record file in database.'];
        }
    }

    // ── Private Helpers ──────────────────────

    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $name = str_replace("\0", '', $name);
        $name = preg_replace('/[^\w\s\-\.\(\)]/u', '_', $name);
        $name = preg_replace('/\.{2,}/', '.', $name);
        $name = preg_replace('/_{2,}/', '_', $name);
        $name = trim($name, " \t\n\r\0\x0B");

        if ($name === '' || $name === '.') {
            $name = 'unnamed_file';
        }

        if (mb_strlen($name) > 200) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 190);
            $name = $base . ($ext ? '.' . $ext : '');
        }
        return $name;
    }

    /**
     * Determines file category from extension.
     * Anything not matching doc/image/video safely maps to 'other'.
     */
    private function detectCategory(string $extension): string
    {
        if ($extension === '') {
            return 'other';
        }

        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'tar', 'gz', '7z', 'rar', 'rtf', 'odt', 'ods'];
        $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff', 'avif'];
        $vidExts = ['mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'm4v'];

        if (in_array($extension, $docExts, true)) return 'document';
        if (in_array($extension, $imgExts, true)) return 'image';
        if (in_array($extension, $vidExts, true)) return 'video';

        return 'other';
    }

    private function getMaxSizeForCategory(string $category): int
    {
        $key = "max_{$category}_size";
        $default = match ($category) {
            'document' => 20971520,  // 20 MB
            'image'    => 10485760,  // 10 MB
            'video'    => 31457280,  // 30 MB
            'other'    => 10485760,  // 10 MB
            default    => 10485760,
        };
        return (int)$this->settingsRepo->getUserSetting(0, $key, $default);
    }
}