<?php
// This script ensures the 'inventaire' table is created if it doesn't exist.
// It's designed to be included at the start of the main application file.

// Include the main database configuration.
require_once 'config.php';

try {
    // Create inventaire table if missing
    $sqlInventaire = file_get_contents(__DIR__ . '/../sql/create_inventaire_table.sql');
    if ($sqlInventaire !== false) {
        $pdo->exec($sqlInventaire);
    }

    // Create materiel_history table if missing
    $sqlHist = file_get_contents(__DIR__ . '/../sql/create_materiel_history.sql');
    if ($sqlHist !== false) {
        $pdo->exec($sqlHist);
    }

    // Apply optional updates to materiel table (safe to run repeatedly)
    $sqlUpdateMateriel = file_get_contents(__DIR__ . '/../sql/update_materiel_table.sql');
    if ($sqlUpdateMateriel !== false) {
        try {
            $pdo->exec($sqlUpdateMateriel);
        } catch (Exception $ignore) {
            // Some MySQL versions may not support IF NOT EXISTS in ALTER; ignore if it fails
        }
    }
} catch (Exception $e) {
    // Log and continue without breaking the app
    error_log("Database initialization check failed: " . $e->getMessage());
}
?>
