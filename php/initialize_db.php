<?php
// This script ensures the 'inventaire' table is created if it doesn't exist.
// It's designed to be included at the start of the main application file.

// Include the main database configuration.
require_once 'config.php';

try {
    // Read the SQL command from the setup file.
    $sql = file_get_contents(__DIR__ . '/../sql/create_inventaire_table.sql');
    
    // If the file is readable, execute the SQL.
    if ($sql !== false) {
        $pdo->exec($sql);
    }
} catch (Exception $e) {
    // If there's an error (e.g., permissions, connection issue),
    // log it silently and let the application continue.
    // This prevents crashing if the DB isn't fully set up.
    error_log("Database initialization check failed: " . $e->getMessage());
}
?>
