<?php
// Script to initialize materiel_history with current assignments
require_once 'config.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get all materials with user information
    $stmt = $pdo->prepare("
        SELECT m.NumSerie, m.stock, m.CodeUtilisateur, u.NomPrenom 
        FROM materiel m 
        LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte
    ");
    $stmt->execute();
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $insertCount = 0;
    
    // Create history entries for each material
    foreach ($materials as $material) {
        $numSerie = $material['NumSerie'];
        $state = $material['stock'];
        $userId = $material['CodeUtilisateur'];
        $userName = $material['NomPrenom'] ?? 'Non attribué';
        
        // Create initial history entry
        $historyStmt = $pdo->prepare("
            INSERT INTO materiel_history 
            (numserie, prev_state, new_state, date_change, user_id, notes) 
            VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        
        $historyStmt->execute([
            $numSerie,
            $state, // Use current state as previous state for initial entry
            $state,
            $userId,
            "État initial: $state, Utilisateur: $userName (Généré automatiquement)"
        ]);
        
        $insertCount++;
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "Successfully created $insertCount initial history entries.";
    
} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
