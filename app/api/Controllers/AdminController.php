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

    public function uploadPdf(): array
    {
        $this->authorize();

        if (!isset($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
            throw new ApiException('PDF file is required', 400);
        }

        $file = $_FILES['pdf'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ApiException('PDF upload failed', 400);
        }
        if (($file['size'] ?? 0) < 1 || $file['size'] > 35 * 1024 * 1024) {
            throw new ApiException('PDF must be smaller than 35 MB', 400);
        }

        $temporaryPath = $file['tmp_name'] ?? '';
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $signature = file_get_contents($temporaryPath, false, null, 0, 5);
        if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
            throw new ApiException('Only valid PDF files can be uploaded', 415);
        }

        $directory = __DIR__ . '/../../../public/assets/pdfs';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ApiException('PDF storage is unavailable', 500);
        }

        $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
        $slug = $this->slugify(substr($title, 0, 500));
        $grade = filter_var($_POST['grade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 11]]);
        if ($grade !== false) {
            $slug = rtrim(substr($slug, 0, 72), '-') . '-' . $grade . '-klas';
        }
        do {
            $filename = $slug . '-' . bin2hex(random_bytes(3)) . '.pdf';
        } while (file_exists($directory . DIRECTORY_SEPARATOR . $filename));
        if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new ApiException('Could not store uploaded PDF', 500);
        }

        return ['pdf_url' => '/assets/pdfs/' . $filename];
    }

    private function slugify(string $value): string
    {
        $value = strtr($value, [
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'H', 'Ґ' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Є' => 'Ye', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'Y', 'І' => 'I',
            'Ї' => 'Yi', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch',
            'Ь' => '', 'Ю' => 'Yu', 'Я' => 'Ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd',
            'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i',
            'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ь' => '', 'ю' => 'iu', 'я' => 'ia', '’' => '', 'ʼ' => '', "'" => '',
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        $value = rtrim(substr($value !== '' ? $value : 'book', 0, 80), '-');

        return $value !== '' ? $value : 'book';
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
