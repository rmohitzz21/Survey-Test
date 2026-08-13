<?php
// Auto-detect environment — no manual swapping needed
$_host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
if ($_host === 'localhost' || $_host === '127.0.0.1') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sp_work');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} elseif ($_host === 'uat.surveypacific.com') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'survevap_uat');
    define('DB_USER', 'survevap_uat');
    define('DB_PASS', 'Abhishek@123');
} else {
    // Production
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'survevap_inquiry');
    define('DB_USER', 'survevap_inquiry');
    define('DB_PASS', 'Abhishek@123');
}



try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['ok' => false, 'message' => 'Database connection failed. Please try again later.']));
}
