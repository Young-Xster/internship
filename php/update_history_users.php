<?php
require_once 'config.php';

try {
    // Get all history records that have notes but empty previous_owner/new_owner
    $stmt = $pdo->prepare("SELECT * FROM materiel_history WHERE notes IS NOT NULL AND (previous_owner IS NULL OR new_owner IS NULL)");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    
    foreach ($records as $record) {
        $notes = $record['notes'];
        
        // Parse notes like "Modification: Utilisateur changé de cr7 à fedi."
        if (preg_match('/Utilisateur changé de (.+?) à (.+?)\./', $notes, $matches)) {
            $previous_user = trim($matches[1]);
            $new_user = trim($matches[2]);
            
            // Update the record with the extracted user information
            $update_stmt = $pdo->prepare("UPDATE materiel_history SET previous_owner = ?, new_owner = ? WHERE id = ?");
            $update_stmt->execute([$previous_user, $new_user, $record['id']]);
            
            $updated++;
            echo "Updated record {$record['id']}: previous_owner = '$previous_user', new_owner = '$new_user'\n";
        }
    }
    
    echo "Successfully updated $updated records.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 