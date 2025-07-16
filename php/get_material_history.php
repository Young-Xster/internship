<?php
header('Content-Type: application/json');
require_once 'config.php';

$numserie = $_GET['numserie'] ?? $_POST['numserie'] ?? null;

if (!$numserie) {
    echo json_encode([
        'error' => "Numéro de série manquant.",
        'debug' => [
            'received_numserie' => $numserie
        ]
    ]);
    exit;
}

try {
    $sql = "SELECT * FROM materiel_history WHERE numserie = ? ORDER BY date_change DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$numserie]);
    $history = $stmt->fetchAll();

    if (!$history) {
        echo json_encode([
            'success' => true,
            'history' => [],
            'debug' => [
                'message' => 'No history found for this serial number.',
                'numserie' => $numserie,
                'sql' => $sql,
                'params' => [$numserie]
            ]
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'history' => $history,
        'debug' => [
            'numserie' => $numserie,
            'sql' => $sql,
            'params' => [$numserie],
            'row_count' => count($history)
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'error' => "Erreur lors de la récupération de l'historique: " . $e->getMessage(),
        'debug' => [
            'numserie' => $numserie
        ]
    ]);
}
