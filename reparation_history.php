<?php
require_once 'php/config.php';

$ste = $_GET['ste'] ?? 'prod';
$materiel_filter = isset($_GET['materiel']) ? $_GET['materiel'] : '';

try {
    $materielStmt = $pdo->prepare("SELECT DISTINCT NumSerie FROM materiel_repair_history WHERE STE = :ste ORDER BY NumSerie");
    $materielStmt->execute(['ste' => $ste]);
    $allMateriel = $materielStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $allMateriel = [];
}

$history = [];
if ($materiel_filter) {
    try {
        $historyStmt = $pdo->prepare("SELECT * FROM materiel_repair_history WHERE NumSerie = :numserie AND STE = :ste ORDER BY date_sent DESC");
        $historyStmt->execute(['numserie' => $materiel_filter, 'ste' => $ste]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $history = [];
    }
} else {
    // Show all materiel with all their repair history, grouped by NumSerie
    try {
        $historyStmt = $pdo->prepare("SELECT * FROM materiel_repair_history WHERE STE = :ste ORDER BY NumSerie, date_sent DESC");
        $historyStmt->execute(['ste' => $ste]);
        $allHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        // Group by NumSerie
        $history = [];
        foreach ($allHistory as $row) {
            $history[$row['NumSerie']][] = $row;
        }
    } catch (PDOException $e) {
        $history = [];
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="reparation_history_AAF_' . date('Y-m-d') . '.html"');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des Réparations - AAF</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
        }
        .header img {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 10px 0;
            color: #333;
            font-size: 24px;
        }
        .dropdown-container {
            margin: 20px 0 10px 0;
            text-align: center;
        }
        .dropdown-container label {
            font-weight: bold;
            margin-right: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
            font-size: 11px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover {
            background: #005a8b;
        }
        .no-history {
            text-align: center;
            color: #666;
            font-size: 15px;
            margin-top: 30px;
        }
        .materiel-title {
            margin-top: 30px;
            font-size: 16px;
            color: #222;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Imprimer PDF</button>
    <div class="header">
        <img src="imgs/Logo_AAF.JPG" alt="Logo AAF">
        <h1>Historique des Réparations - AAF</h1>
        <p><strong>Date d'export:</strong> <?php echo date('d/m/Y'); ?></p>
    </div>
    <div class="dropdown-container no-print">
        <form method="get" action="reparation_history.php" style="display:inline-block;">
            <input type="hidden" name="ste" value="<?php echo htmlspecialchars($ste); ?>">
            <label for="materiel">Filtrer par matériel :</label>
            <select name="materiel" id="materiel" onchange="this.form.submit()">
                <option value="">-- Tous les matériels --</option>
                <?php foreach ($allMateriel as $num): ?>
                    <option value="<?php echo htmlspecialchars($num); ?>" <?php if ($materiel_filter == $num) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($num); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if ($materiel_filter && empty($history)): ?>
        <div class="no-history">Aucun historique de réparation trouvé pour ce matériel.</div>
    <?php elseif (!$materiel_filter && empty($history)): ?>
        <div class="no-history">Aucun matériel n'a été envoyé en réparation.</div>
    <?php else: ?>
        <?php if ($materiel_filter): ?>
            <div class="materiel-title">Matériel N° Série : <?php echo htmlspecialchars($materiel_filter); ?></div>
            <table>
                <thead>
                    <tr>
                        <th>Date d'envoi en réparation</th>
                        <th>Date de récupération</th>
                        <th>Modèle</th>
                        <th>Classification</th>
                        <th>Type</th>
                        <th>Marque</th>
                        <th>Observation</th>
                        <th>Cause du dommage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date_sent']); ?></td>
                            <td><?php echo $row['date_recuperated'] ? htmlspecialchars($row['date_recuperated']) : '<span style="color:#c00;">n\'est pas récupéré</span>'; ?></td>
                            <td><?php echo htmlspecialchars($row['Model']); ?></td>
                            <td><?php echo htmlspecialchars($row['classification']); ?></td>
                            <td><?php echo htmlspecialchars($row['CodeType']); ?></td>
                            <td><?php echo htmlspecialchars($row['CodeMarque']); ?></td>
                            <td><?php echo htmlspecialchars($row['observation']); ?></td>
                            <td><?php echo htmlspecialchars($row['damage_cause']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php foreach ($history as $numserie => $rows): ?>
                <div class="materiel-title">Matériel N° Série : <?php echo htmlspecialchars($numserie); ?></div>
                <table>
                    <thead>
                        <tr>
                            <th>Date d'envoi en réparation</th>
                            <th>Date de récupération</th>
                            <th>Modèle</th>
                            <th>Classification</th>
                            <th>Type</th>
                            <th>Marque</th>
                            <th>Observation</th>
                            <th>Cause du dommage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['date_sent']); ?></td>
                                <td><?php echo $row['date_recuperated'] ? htmlspecialchars($row['date_recuperated']) : '<span style="color:#c00;">n\'est pas récupéré</span>'; ?></td>
                                <td><?php echo htmlspecialchars($row['Model']); ?></td>
                                <td><?php echo htmlspecialchars($row['classification']); ?></td>
                                <td><?php echo htmlspecialchars($row['CodeType']); ?></td>
                                <td><?php echo htmlspecialchars($row['CodeMarque']); ?></td>
                                <td><?php echo htmlspecialchars($row['observation']); ?></td>
                                <td><?php echo htmlspecialchars($row['damage_cause']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 10px;">
        Document généré automatiquement le <?php echo date('d/m/Y à H:i:s'); ?> - Système de Gestion de Matériel AAF
    </div>
</body>
</html>
