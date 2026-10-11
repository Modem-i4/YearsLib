<?php

require_once __DIR__ . '/../../config.php';

class AdminController
{
    public function session(): array
    {
        return ['authenticated' => !empty($_SESSION['admin_authenticated'])];
    }

    public function login(): array
    {
        $input = $this->jsonInput();
        $providedKey = $input['key'] ?? null;
        $configuredKey = app_env('ADMIN_API_KEY');

        if ($configuredKey === null || $configuredKey === '') {
            throw new ApiException('Admin access is not configured', 503);
        }
        if (!is_string($providedKey) || !hash_equals($configuredKey, $providedKey)) {
            throw new ApiException('Неправильний ключ доступу', 401);
        }

        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_authenticated_at'] = time();

        return ['authenticated' => true];
    }

    public function logout(): array
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        session_destroy();

        return ['authenticated' => false];
    }

    public function uploadCover(): array
    {
        $this->authorize();

        if (!isset($_FILES['cover']) || !is_array($_FILES['cover'])) {
            throw new ApiException('Cover file is required', 400);
        }

        $file = $_FILES['cover'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ApiException('Cover upload failed', 400);
        }
        if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
            throw new ApiException('WebP cover must be smaller than 5 MB', 400);
        }

        $temporaryPath = $file['tmp_name'] ?? '';
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $imageInfo = @getimagesize($temporaryPath);
        if ($mime !== 'image/webp' || $imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/webp') {
            throw new ApiException('Cover must be converted to WebP before upload', 415);
        }

        $directory = __DIR__ . '/../../../public/assets/covers';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ApiException('Cover storage is unavailable', 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.webp';
        if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new ApiException('Could not store uploaded cover', 500);
        }

        return ['cover_path' => '/assets/covers/' . $filename];
    }

    public function authorize(): void
    {
        if (empty($_SESSION['admin_authenticated'])) {
            throw new ApiException('Unauthorized', 401);
        }
    }

    private function jsonInput(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input') ?: '', false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('Request body must be valid JSON', 400);
        }

        if (!$input instanceof stdClass) {
            throw new ApiException('Request body must be a JSON object', 400);
        }

        return (array)$input;
    }
}