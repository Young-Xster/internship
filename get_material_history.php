<?php
require_once 'php/config.php';

// Check for GET parameter
if (!isset($_GET['numserie']) || empty($_GET['numserie'])) {
    die("Erreur: Numéro de série non fourni.");
}

$numserie = $_GET['numserie'];
$ste = $_GET['ste'] ?? 'prod'; // Default to 'prod' if not set

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch transfer history for the given serial number
    // We join with the 'utilisateurs' table twice to get the names of the previous and new users.
    $sql = "SELECT 
                h.date_change,
                h.prev_state,
                h.new_state,
                u_prev.NomPrenom as prev_user,
                u_new.NomPrenom as new_user,
                h.cause,
                h.notes
            FROM 
                materiel_history h
            LEFT JOIN 
                utilisateur u_prev ON h.prev_state = u_prev.Compte
            LEFT JOIN 
                utilisateur u_new ON h.new_state = u_new.Compte
            WHERE 
                h.numserie = :numserie
            ORDER BY 
                h.date_change DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['numserie' => $numserie]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get material details for the page header
    $stmt_materiel = $pdo->prepare("SELECT Model, NumSerie FROM materiel WHERE NumSerie = :numserie");
    $stmt_materiel->execute(['numserie' => $numserie]);
    $materiel = $stmt_materiel->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Transferts - <?= htmlspecialchars($numserie) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .history-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .history-header h1 {
            color: #343a40;
            font-weight: 600;
        }
        .history-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .history-timeline {
            list-style: none;
            padding: 0;
            position: relative;
        }
        .history-timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 15px;
            width: 3px;
            background: #e9ecef;
        }
        .timeline-item {
            margin-bottom: 25px;
            position: relative;
            padding-left: 45px;
        }
        .timeline-icon {
            position: absolute;
            left: 0;
            top: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 3px solid #fff;
        }
        .timeline-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .timeline-date {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }
        .transfer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.05rem;
        }
        .user-badge {
            padding: 5px 15px;
            border-radius: 20px;
            background-color: #e9ecef;
            color: #495057;
            font-weight: 500;
        }
        .transfer-arrow {
            font-size: 1.5rem;
            color: #6c757d;
        }
        .no-history {
            text-align: center;
            padding: 30px;
            font-size: 1.1rem;
            color: #6c757d;
        }
        .back-link {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="history-header">
            <h1>Historique des Transferts</h1>
            <?php if ($materiel): ?>
                <p><strong>Modèle:</strong> <?= htmlspecialchars($materiel['Model']) ?> | <strong>N° Série:</strong> <?= htmlspecialchars($materiel['NumSerie']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($history)): ?>
            <p class="no-history">Aucun historique de transfert pour ce matériel.</p>
        <?php else: ?>
            <ul class="history-timeline">
                <?php foreach ($history as $item): ?>
                    <li class="timeline-item">
                        <div class="timeline-icon">&#8644;</div>
                        <div class="timeline-content">
                            <p class="timeline-date"><?= date('d/m/Y à H:i', strtotime($item['date_change'])) ?></p>
                            <div class="transfer-info">
                                <span class="user-badge"><?= htmlspecialchars($item['prev_user'] ?? 'Ancien Utilisateur Inconnu') ?></span>
                                <span class="transfer-arrow">&rarr;</span>
                                <span class="user-badge"><?= htmlspecialchars($item['new_user'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="index.php?tab=materiel&ste=<?= htmlspecialchars($ste) ?>" class="back-link">Retour</a>
        </div>
    </div>
</body>
</html>
