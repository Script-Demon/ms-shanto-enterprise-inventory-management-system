<?php
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('Setup needed: copy config/config.php.example to config/config.php and fill in your database details.');
}
$config = require $configFile;

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Check config/config.php credentials. (' . $e->getMessage() . ')');
}
