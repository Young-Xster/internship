<?php
$host = 'localhost';
$db   = 'gestion-de-stock';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // First try normal connection
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (int)$e->getCode();
    // If database is missing (error 1049), attempt to create it then reconnect
    if ($code === 1049 || stripos($msg, 'Unknown database') !== false) {
        try {
            // Connect without specifying db to create it
            $pdoNoDb = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
            $dbEsc = str_replace('`', '``', $db);
            $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `$dbEsc` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            // Reconnect to the newly created database
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e2) {
            die('Database connection failed: ' . $e2->getMessage());
        }
    } else {
        die('Database connection failed: ' . $msg);
    }
}
