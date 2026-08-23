<?php
// Database connection — PostgreSQL (Railway) or MySQL (local XAMPP)

// Upload directory from environment variable
$upload_dir = getenv('UPLOADS_DIR') ?: (__DIR__ . '/uploads');

// Detect database type from environment
$db_type = getenv('DB_TYPE') ?: (getenv('DATABASE_URL') ? 'pgsql' : 'mysql');

if ($db_type === 'pgsql' || getenv('DB_HOST')) {
    // PostgreSQL (Railway or local PostgreSQL)
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: 'bondnest_db';
    $db_user = getenv('DB_USER') ?: 'postgres';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_port = getenv('DB_PORT') ?: '5432';

    $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";
} else {
    // MySQL (local XAMPP)
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: 'bondnest_db';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_port = getenv('DB_PORT') ?: '3306';

    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
}

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Set the global $db_driver so files can check 'pgsql' vs 'mysql' if needed
    $db_driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Backward-compatible alias
$connection = $pdo;
