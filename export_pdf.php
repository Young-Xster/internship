<?php
require_once 'php/config.php';

// Fetch all services and users, and left join materiel
$ste = $_GET['ste'] ?? 'prod'; 

try {
    $stmt = $pdo->prepare("
        SELECT
            s.Libelle as ServiceLibelle,
            s.CodeService,
            s.STE,
            u.NomPrenom, u.Compte, u.CodeService as UserCodeService,
            m.NumSerie, m.Model, m.classification, m.Dateentree,
            t.Libelle as TypeLibelle,
            ma.Marque as Marque
        FROM
            service s
        LEFT JOIN utilisateur u ON u.CodeService = s.CodeService
        LEFT JOIN materiel m ON m.CodeUtilisateur = u.Compte
        LEFT JOIN type t ON m.CodeType = t.CodeType
        LEFT JOIN marque ma ON m.CodeMarque = ma.Code
        WHERE s.STE = :ste
        ORDER BY
            s.STE, s.Libelle, u.NomPrenom, m.NumSerie
    ");
    $stmt->execute(['ste' => $ste]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Group data by STE -> Service -> User
$groupedData = [];
foreach ($data as $row) {
    $ste = $row['STE'] ?: 'Non spécifié';
    $service = $row['ServiceLibelle'] ?: 'Non spécifié';
    $user = $row['NomPrenom'] ?: ($row['Compte'] ? $row['Compte'] : 'Non spécifié');
    if (!isset($groupedData[$ste])) {
        $groupedData[$ste] = [];
    }
    if (!isset($groupedData[$ste][$service])) {
        $groupedData[$ste][$service] = [];
    }
    if (!isset($groupedData[$ste][$service][$user])) {
        $groupedData[$ste][$service][$user] = [];
    }
    // Only add materiel if it exists
    if ($row['NumSerie']) {
        $groupedData[$ste][$service][$user][] = $row;
    }
}

// Set headers for HTML export that looks like PDF
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="inventaire_materiel_AAF_' . date('Y-m-d') . '.html"');

// Generate HTML content
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inventaire Matériel - AAF</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
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
        .societe-block {
            border: 2px solid #333;
            padding: 15px;
            margin-bottom: 25px;
            page-break-inside: avoid;
            background: #f9f9f9;
        }
        .societe-block h2 {
            background-color: #333;
            color: #fff;
            padding: 8px;
            margin: -15px -15px 15px -15px;
            font-size: 18px;
        }
        .service-block {
            margin-left: 20px;
            margin-bottom: 20px;
            border-left: 3px solid #666;
            padding-left: 15px;
        }
        .service-block h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .user-block {
            margin-left: 20px;
            margin-bottom: 15px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .user-block h4 {
            color: #444;
            margin-bottom: 10px;
            font-size: 14px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
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
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Imprimer PDF</button>
    
    <div class="header">
        <img src="imgs/Logo_AAF.JPG" alt="Logo AAF">
        <h1>Inventaire Matériel - AAF</h1>
        <p><strong>Date d'export:</strong> <?php echo date('d/m/Y'); ?></p>
    </div>

    <?php if (empty($groupedData)): ?>
        <p style="text-align: center; font-size: 16px; color: #666;">
            Aucun matériel attribué à un utilisateur n'a été trouvé.
        </p>
    <?php else: ?>
        <?php foreach ($groupedData as $ste => $services): ?>
            <div class="societe-block">
                <h2>Société: AAF- <?php echo strtoupper($ste == "prod" ? "PRODUCTION" : "COMMUNICATION"); ?></h2>
                <?php foreach ($services as $service => $users): ?>
                    <div class="service-block">
                        <h3>📁 Service: <?php echo htmlspecialchars($service); ?></h3>
                        <?php foreach ($users as $user => $materials): ?>
                            <div class="user-block">
                                <h4>👤 Utilisateur: <?php echo htmlspecialchars($user); ?></h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>N° Série</th>
                                            <th>Marque</th>
                                            <th>Type</th>
                                            <th>Modèle</th>
                                            <th>Date d'entrée</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($material['NumSerie']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Marque']); ?></td>
                                                <td><?php echo htmlspecialchars($material['TypeLibelle']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Model']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Dateentree']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 10px;">
        Document généré automatiquement le <?php echo date('d/m/Y à H:i:s'); ?> - Système de Gestion de Matériel AAF
    </div>
</body>
</html>
