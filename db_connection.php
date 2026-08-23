<?php
// Database connection — PostgreSQL (Railway) or MySQL (local XAMPP)

// Upload directory from environment variable
$upload_dir = getenv('UPLOADS_DIR') ?: (__DIR__ . '/uploads');

// Railway provides DATABASE_URL automatically when you add a PostgreSQL service
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse DATABASE_URL (format: postgres://user:pass@host:port/dbname)
    $db_driver = 'pgsql';
    $url = parse_url($database_url);
    $db_host = $url['host'] ?? 'localhost';
    $db_port = $url['port'] ?? '5432';
    $db_name = ltrim($url['path'], '/');
    $db_user = $url['user'] ?? '';
    $db_pass = $url['pass'] ?? '';

    $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";
} elseif (getenv('DB_HOST')) {
    // PostgreSQL via individual env vars
    $db_driver = 'pgsql';
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME') ?: 'bondnest_db';
    $db_user = getenv('DB_USER') ?: 'postgres';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_port = getenv('DB_PORT') ?: '5432';
    $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";
} else {
    // MySQL (local XAMPP)
    $db_driver = 'mysql';
    $db_host = 'localhost';
    $db_name = getenv('DB_NAME') ?: 'bondnest_db';
    $db_user = 'root';
    $db_pass = '';
    $db_port = '3306';
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
}

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die("Connection failed: " . $e->getMessage());
}

// Create symlink for uploads if using a different upload directory (Railway volume)
$local_uploads = __DIR__ . '/uploads';
if ($upload_dir !== $local_uploads && !is_link($local_uploads)) {
    @symlink($upload_dir, $local_uploads);
}

// Backward-compatible alias
$connection = $pdo;
