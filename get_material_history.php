<?php

require_once 'php/config.php';

// State mapping for readable display
$stockLabelMap = [
    0 => 'En service',
    1 => 'En stock',
    2 => 'Endommagé',
    3 => 'Cassé',
    'en-service' => 'En service',
    'en-stock' => 'En stock',
    'endommage' => 'Endommagé',
    'casse' => 'Cassé'
];

$numSerie = $_GET['numserie'] ?? '';
$ste = $_GET['ste'] ?? 'prod';

if (empty($numSerie)) {
    echo "Numéro de série manquant.";
    exit;
}

try {
    // Get material basic info first
    $material_stmt = $pdo->prepare("SELECT m.*, ma.Marque, t.Libelle as TypeLibelle 
                                   FROM materiel m 
                                   LEFT JOIN marque ma ON m.CodeMarque = ma.Code 
                                   LEFT JOIN type t ON m.CodeType = t.CodeType 
                                   WHERE m.NumSerie = ?");
    $material_stmt->execute([$numSerie]);
    $material = $material_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        echo "Matériel non trouvé.";
        exit;
    }

    // Get history with user names
    $history_stmt = $pdo->prepare("
        SELECT 
            h.*, 
            u_prev.NomPrenom as previous_username,
            u_new.NomPrenom as new_username,
            u_user.NomPrenom as changed_by_name
        FROM 
            materiel_history h
        LEFT JOIN 
            utilisateur u_prev ON h.previous_owner = u_prev.Compte
        LEFT JOIN 
            utilisateur u_new ON h.new_owner = u_new.Compte
        LEFT JOIN 
            utilisateur u_user ON h.user_id = u_user.Compte
        WHERE 
            h.numserie = ?
        ORDER BY 
            h.date_change DESC
    ");
    $history_stmt->execute([$numSerie]);
    $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also get legacy history records (if table structure changed)
    $legacy_history_stmt = $pdo->prepare("
        SELECT * FROM materiel_history 
        WHERE numserie = ? AND prev_state IS NOT NULL
        ORDER BY date_change DESC
    ");
    $legacy_history_stmt->execute([$numSerie]);
    $legacy_history = $legacy_history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge all history records if needed
    if (!empty($legacy_history)) {
        $history = array_merge($history, $legacy_history);
        // Sort by date
        usort($history, function($a, $b) {
            return strtotime($b['date_change']) - strtotime($a['date_change']);
        });
    }
    // Remove duplicate records (same numserie, prev_state, new_state, date_change)
    $unique = [];
    foreach ($history as $rec) {
        $key = $rec['numserie'] . '|' . $rec['prev_state'] . '|' . $rec['new_state'] . '|' . $rec['date_change'];
        if (!isset($unique[$key])) {
            $unique[$key] = $rec;
        }
    }
    $history = array_values($unique);

} catch (PDOException $e) {
    echo "Erreur lors de la récupération de l'historique : " . $e->getMessage();
    exit;
}

// HTML content starts here
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique du Matériel</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .history-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .history-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .history-item {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            background-color: #f8f8f8;
            position: relative;
        }
        .history-date {
            color: #666;
            font-size: 0.9em;
        }
        .history-details {
            margin-top: 10px;
        }
        .transfer-icon {
            font-size: 24px;
            color: #4285F4;
            margin: 0 10px;
        }
        .user-change, .state-change {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .user-box, .state-box {
            padding: 8px 15px;
            background: #e9eef6;
            border-radius: 20px;
            display: inline-block;
        }
        .notes {
            margin-top: 10px;
            font-style: italic;
            color: #666;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 20px auto;
            cursor: pointer;
            border-radius: 4px;
            display: block;
            width: 120px;
        }
        .timeline-icon {
            background: #4285F4;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            left: -20px;
            top: 15px;
        }
    </style>
</head>
<body class="theme-<?= htmlspecialchars($ste) ?>">
    <div class="history-container">
        <div class="history-title">
            <h1>Historique des Transferts</h1>
            <p>Modèle: <?= htmlspecialchars($material['Model'] ?? 'N/A') ?> | N° Série: <?= htmlspecialchars($numSerie) ?></p>
        </div>

        <?php if (empty($history)): ?>
            <p>Aucun historique disponible pour ce matériel.</p>
        <?php else: ?>
            <?php foreach ($history as $record): ?>
                <div class="history-item">
                    <div class="timeline-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                        </svg>
                    </div>
                    <div class="history-date">
                        <?= date('d/m/Y à H:i', strtotime($record['date_change'])) ?>
                    </div>
                    <div class="history-details">
                        <?php if (isset($record['previous_owner']) && isset($record['new_owner']) && $record['previous_owner'] !== $record['new_owner']): ?>
                            <div class="user-change">
                                <span class="user-box"><?= htmlspecialchars($record['previous_username'] ?? $record['previous_owner'] ?? 'Ancien Utilisateur') ?></span>
                                <span class="transfer-icon">→</span>
                                <span class="user-box"><?= htmlspecialchars($record['new_username'] ?? $record['new_owner'] ?? 'Nouvel Utilisateur') ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($record['prev_state']) && isset($record['new_state']) && $record['prev_state'] !== $record['new_state']): ?>
                            <div class="state-change">
                                <span class="state-box"><?= htmlspecialchars($stockLabelMap[$record['prev_state']] ?? $record['prev_state']) ?></span>
                                <span class="transfer-icon">→</span>
                                <span class="state-box"><?= htmlspecialchars($stockLabelMap[$record['new_state']] ?? $record['new_state']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['notes'])): ?>
                            <div class="notes">
                                <?= htmlspecialchars($record['notes']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($record['cause'])): ?>
                            <div class="cause">
                                <strong>Cause:</strong> <?= htmlspecialchars($record['cause']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="changed-by">
                            <small>Modifié par: <?= htmlspecialchars($record['changed_by_name'] ?? $record['user_id'] ?? 'Système') ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <button class="btn-back" onclick="window.location.href='index.php?tab=materiel&ste=<?= urlencode($ste) ?>'">Retour</button>
    </div>
</body>
</html>

