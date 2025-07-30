<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $stmt = $pdo->prepare('
        SELECT h.*, 
               m.Model, t.Libelle as TypeLibelle,
               u_prev.NomPrenom as previous_username,
               u_new.NomPrenom as new_username
        FROM materiel_history h
        LEFT JOIN materiel m ON h.numserie = m.NumSerie
        LEFT JOIN type t ON m.CodeType = t.CodeType
        LEFT JOIN utilisateur u_prev ON h.previous_owner = u_prev.Compte
        LEFT JOIN utilisateur u_new ON h.new_owner = u_new.Compte
        ORDER BY h.date_change DESC
    ');
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'history' => $history]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}