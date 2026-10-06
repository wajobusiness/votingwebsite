<?php
/**
 * Security, Upload Sanitization, and Request Protection Helpers
 */

require_once __DIR__ . '/Env.php';

class Security {

    /**
     * Start secure PHP session with universal server/proxy compatibility
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                @ini_set('session.use_cookies', '1');
                @ini_set('session.use_only_cookies', '1');
                @ini_set('session.cookie_httponly', '1');
                @ini_set('session.cookie_path', '/');
                $lifetime = (int)Env::get('SESSION_LIFETIME', 86400);
                @ini_set('session.gc_maxlifetime', (string)$lifetime);
                @ini_set('session.cookie_lifetime', (string)$lifetime);
            }

            @session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
            }
        }
    }

    /**
     * Generate or fetch current CSRF token
     */
    public static function csrfToken(): string {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
            }
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Generate hidden HTML CSRF input field
     */
    public static function csrfField(): string {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Validate incoming CSRF token from POST or headers
     */
    public static function validateCsrf(?string $token = null): bool {
        self::startSession();
        
        $csrfEnabled = Env::get('CSRF_PROTECTION', false);
        if ($csrfEnabled === false || $csrfEnabled === 'false' || $csrfEnabled === '0' || $csrfEnabled === 0) {
            return true;
        }

        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return true;
        }

        return hash_equals($_SESSION['csrf_token'], (string)$token);
    }

    /**
     * Enforce CSRF protection or terminate request with 403 Forbidden
     */
    public static function requireCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::validateCsrf()) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Security session expired. Please refresh the page.']);
                    exit();
                }
                http_response_code(403);
                die("Security Validation Failed: Invalid or missing CSRF token. Please refresh the page.");
            }
        }
    }

    /**
     * Safely escape output for HTML rendering
     */
    public static function e(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Secure File Upload Handler
     *
     * Validates extension, MIME type, file size, generates cryptographically safe
     * random UUID filename, and safely moves to upload destination.
     *
     * @param array $file $_FILES['input_name']
     * @param string $targetDir Target directory relative or absolute
     * @param array $allowedExts Allowed file extensions
     * @param int|null $maxSizeMb Max size in megabytes
     * @return array ['success' => bool, 'filename' => string|null, 'path' => string|null, 'error' => string|null]
     */
    public static function handleFileUpload(
        array $file,
        string $targetDir = 'uploads/',
        array $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        ?int $maxSizeMb = null
    ): array {
        $maxSizeMb = $maxSizeMb ?? (int)Env::get('UPLOAD_MAX_FILESIZE_MB', 5);
        $maxBytes = $maxSizeMb * 1024 * 1024;

        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file upload parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No file was uploaded.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => "File exceeds the maximum upload limit of {$maxSizeMb}MB."];
            default:
                return ['success' => false, 'error' => 'Unknown upload error occurred.'];
        }

        if ($file['size'] > $maxBytes) {
            return ['success' => false, 'error' => "File size exceeds the allowable limit of {$maxSizeMb}MB."];
        }

        // Validate Extension
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            return [
                'success' => false,
                'error'   => 'Invalid file extension. Allowed formats: ' . implode(', ', $allowedExts)
            ];
        }

        // Validate real MIME Type using finfo or mime_content_type
        $allowedMimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/x-png',
            'image/webp',
            'image/gif'
        ];

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if ($mime && !in_array(strtolower($mime), $allowedMimes, true)) {
                return ['success' => false, 'error' => 'File contents do not match a valid image type (' . htmlspecialchars($mime) . ').'];
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']);
            if ($mime && !in_array(strtolower($mime), $allowedMimes, true)) {
                return ['success' => false, 'error' => 'File contents do not match a valid image type.'];
            }
        }

        // Validate image dimensions using getimagesize (if GD/image library is available)
        if (function_exists('getimagesize')) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false && !in_array($ext, ['webp', 'svg'])) {
                return ['success' => false, 'error' => 'Uploaded file is not a valid or readable image.'];
            }
        }

        // Ensure upload destination directory exists
        $normalizedDir = rtrim($targetDir, '/') . '/';
        if (!is_dir($normalizedDir)) {
            if (!@mkdir($normalizedDir, 0777, true) && !is_dir($normalizedDir)) {
                return ['success' => false, 'error' => 'Upload directory could not be created. Please check folder permissions.'];
            }
        }

        // Generate cryptographically unique safe file name
        $safeFileName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destinationPath = $normalizedDir . $safeFileName;

        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file to destination.'];
        }

        // Set safe file permissions (non-executable)
        chmod($destinationPath, 0644);

        return [
            'success'  => true,
            'filename' => $safeFileName,
            'path'     => $destinationPath,
            'error'    => null
        ];
    }

    /**
     * Get Client IP Address (respecting proxies if verified)
     */
    public static function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Global short-hand helper function
function e(?string $str): string {
    return Security::e($str);
}
