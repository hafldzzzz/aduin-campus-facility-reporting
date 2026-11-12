<?php
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requestString(array $source, string $key, string $default = ''): string
{
    $value = $source[$key] ?? $default;
    if (!is_string($value) || str_contains($value, "\0")) {
        http_response_code(400);
        exit('Input tidak valid.');
    }
    return $value;
}

function postString(string $key, string $default = ''): string
{
    return requestString($_POST, $key, $default);
}

function getString(string $key, string $default = ''): string
{
    return requestString($_GET, $key, $default);
}

function validId($value): int
{
    if (!is_int($value) && !is_string($value)) {
        return 0;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : $id;
}

function dbStatement(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function dbQuery(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result
{
    return dbStatement($conn, $sql, $types, $params)->get_result();
}

function dbOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    return dbQuery($conn, $sql, $types, $params)->fetch_assoc();
}

function dbTextLimit(mysqli $conn, string $table, string $column, int $fallback): int
{
    static $limits = [];
    $key = $table . '.' . $column;
    if (!isset($limits[$key])) {
        $row = dbOne($conn, 'SELECT CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', 'ss', [$table, $column]);
        $limits[$key] = isset($row['max_length']) ? (int)$row['max_length'] : $fallback;
    }
    return $limits[$key];
}

function clearSession(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $params['path'],
            'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}

function currentUser(): ?array
{
    global $conn;
    static $loaded = false, $user;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = validId($_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0);
    if (!$id) {
        return null;
    }
    $user = dbOne($conn, 'SELECT id_user,nama,email,password,no_wa,role,fakultas FROM users WHERE id_user = ? LIMIT 1', 'i', [$id]);
    if (!$user || !in_array($user['role'], ['admin', 'pengguna', 'petugas'], true)
        || empty($_SESSION['auth_version'])
        || !hash_equals($_SESSION['auth_version'], hash('sha256', $user['password']))) {
        clearSession();
        $user = null;
        return null;
    }
    foreach (['nama','email','no_wa','role','fakultas'] as $field) {
        $_SESSION[$field] = $user[$field];
    }
    return $user;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: ' . appPath('login.php'));
        exit;
    }
    return $user;
}

function forbid(): void
{
    http_response_code(403);
    exit('Akses ditolak.');
}

function requireRole(string $role): array
{
    $user = requireLogin();
    if ($user['role'] !== $role) {
        forbid();
    }
    return $user;
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($token)
        || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        forbid();
    }
}

function rateLimit(string $scope, string $identity, int $limit, int $window): bool
{
    // Cross-session throttling on one host, using private files and exclusive locks.
    $directory = (string) appConfig('RATE_LIMIT_DIR', sys_get_temp_dir() . '/aduin-ratelimit-' . substr(hash('sha256', dirname(__DIR__)), 0, 16));
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Limiter unavailable.');
    }
    $handle = fopen($directory . '/' . hash('sha256', $scope . '|' . $identity) . '.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Limiter unavailable.');
    }
    $data = json_decode(stream_get_contents($handle), true);
    $now = time();
    if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
        $data = ['count' => 0, 'reset' => $now + $window];
    }
    $allowed = $data['count'] < $limit;
    if ($allowed) {
        ++$data['count'];
    }
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $allowed;
}

function clientAddress(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');
}

function validateImageUpload(array $file): ?string
{
    foreach (['error','name','tmp_name','size'] as $key) {
        if (!isset($file[$key]) || !is_scalar($file[$key])) {
            throw new InvalidArgumentException('Foto tidak valid.');
        }
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Foto gagal diunggah. Silakan coba lagi.');
    }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }
    $name = (string) $file['name'];
    if (str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\')
        || preg_match('/\.(?:php\d*|phtml|phar|html?|svg|js)(?:\.|$)/i', $name)) {
        throw new InvalidArgumentException('Nama atau format foto tidak valid.');
    }
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $image = getimagesize($file['tmp_name']);
    if (!isset($allowed[$extension]) || $mime !== $allowed[$extension] || !$image || ($image['mime'] ?? '') !== $mime
        || $image[0] < 1 || $image[1] < 1 || $image[0] > 10000 || $image[1] > 10000
        || $image[0] * $image[1] > 20000000) {
        throw new InvalidArgumentException('Format foto harus JPG, JPEG, PNG, atau WEBP yang valid.');
    }
    return $extension === 'jpeg' ? 'jpg' : $extension;
}

function storeImageUpload(?array $file, string $subdir): ?string
{
    if ($file === null) {
        return null;
    }
    if (!in_array($subdir, ['pengaduan', 'perbaikan'], true)) {
        throw new RuntimeException('Invalid image directory.');
    }
    $extension = validateImageUpload($file);
    if ($extension === null) {
        return null;
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('GD is required for safe image uploads.');
    }
    // Decode/re-encode removes script polyglots and private EXIF metadata.
    $image = imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$image) {
        throw new InvalidArgumentException('File gambar tidak dapat dibaca.');
    }
    $directory = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Image storage unavailable.');
    }
    $name = bin2hex(random_bytes(24)) . '.' . $extension;
    $path = $directory . '/' . $name;
    $handle = fopen($path, 'x+b');
    if (!$handle) {
        imagedestroy($image);
        throw new RuntimeException('Image storage unavailable.');
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $saved = match ($extension) {
        'jpg' => imagejpeg($image, $handle, 90),
        'png' => imagepng($image, $handle, 6),
        'webp' => imagewebp($image, $handle, 90),
    };
    fclose($handle);
    imagedestroy($image);
    chmod($path, 0644);
    if (!$saved || filesize($path) > 5 * 1024 * 1024) {
        unlink($path); // Only a newly created file; never a legacy upload.
        throw new InvalidArgumentException('Foto tidak dapat disimpan dalam batas 5 MB.');
    }
    return $name;
}

function uploadUrl($filename, string $subdir, string $basePath = '../'): string
{
    $filename = (string) $filename;
    if (!in_array($subdir, ['pengaduan', 'perbaikan'], true) || $filename !== basename(str_replace('\\', '/', $filename))
        || !preg_match('/\.(jpe?g|png|webp)$/i', $filename) || preg_match('/[\x00-\x1f\x7f:?#&]/', $filename)
        || preg_match('/\.(php[0-9]*|phtml|phar|cgi|pl|py|shtml|html?|svg|js)(\.|$)/i', $filename)) {
        return '';
    }
    return $basePath . 'uploads/' . $subdir . '/' . rawurlencode($filename);
}
