<?php
// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('PHP_PATH', __DIR__);
define('ASSETS_PATH', BASE_PATH . '/assets');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('LOGS_PATH', BASE_PATH . '/logs');
define('IMAGES_PATH', BASE_PATH . '/images');

// URL paths for links
define('BASE_URL', '/projecten/PHP_0/toets');
define('ASSETS_URL', BASE_URL . '/assets');
define('IMAGES_URL', BASE_URL . '/images');

// MAMP MySQL configuratie
$DB_NAME = 'funko_webshop';
$DB_USER = 'root';
$DB_PASS = 'root';

try {
    // Gebruik socket connection voor MAMP
    $mysqli = mysqli_connect(
        'localhost',     // hostname
        $DB_USER,       // username
        $DB_PASS,       // password
        $DB_NAME,       // database
        3306,           // port (standaard MySQL poort)
        'C:/MAMP/tmp/mysql/mysql.sock'  // MAMP socket path
    );

    if (!$mysqli) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    // Set charset to UTF8
    mysqli_set_charset($mysqli, 'utf8mb4');

} catch (Exception $e) {
    die("Could not connect to the database: " . $e->getMessage());
}
?>