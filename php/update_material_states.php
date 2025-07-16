<?php
// Script to convert existing stock values to state values
require_once 'config.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get all materials
    $stmt = $pdo->query("SELECT NumSerie, stock FROM materiel");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateCount = 0;
    
    // Update each material
    foreach ($materials as $material) {
        $numSerie = $material['NumSerie'];
        $currentStock = $material['stock'];
        $newState = '';
        
        // Convert numeric values to state strings
        if (is_numeric($currentStock)) {
            if ($currentStock > 0) {
                $newState = 'en-stock';
            } else {
                $newState = 'en-service';
            }
        } else if (empty($currentStock)) {
            // Default to en-service if no value
            $newState = 'en-service';
        } else {
            // Already has a string value, leave it as is
            continue;
        }
        
        
        $updateStmt = $pdo->prepare("UPDATE materiel SET stock = ? WHERE NumSerie = ?");
        $updateStmt->execute([$newState, $numSerie]);
        $updateCount++;
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "Successfully updated $updateCount materials with new state values.";
    
} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
