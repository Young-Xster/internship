<?php
require_once 'php/config.php';

// Fetch all services and users, and left join materiel
$ste = $_GET['ste'] ?? 'prod'; 
$user_filter = isset($_GET['user']) ? $_GET['user'] : '';

// Fetch all users for the dropdown
try {
    $userStmt = $pdo->prepare("SELECT DISTINCT u.Compte, u.NomPrenom FROM utilisateur u WHERE u.STE = :ste ORDER BY u.NomPrenom");
    $userStmt->execute(['ste' => $ste]);
    $allUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $allUsers = [];
}

try {
    $query = "
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
    ";
    $params = ['ste' => $ste];
    if ($user_filter) {
        $query .= " AND u.Compte = :user ";
        $params['user'] = $user_filter;
    }
    $query .= " ORDER BY s.STE, s.Libelle, u.NomPrenom, m.NumSerie ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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
        .user-select-container {
            margin: 20px 0 10px 0;
            text-align: center;
        }
        .user-select-container label {
            font-weight: bold;
            margin-right: 8px;
        }
        .signature-block {
            margin-top: 40px;
            padding: 30px 0 0 0;
            border-top: 2px solid #333;
            width: 60%;
            margin-left: auto;
            margin-right: auto;
            text-align: left;
        }
        .signature-label {
            font-size: 15px;
            margin-bottom: 30px;
            display: block;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            width: 300px;
            height: 40px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="user-select-container no-print">
        <form method="get" action="export_pdf.php" style="display:inline-block;">
            <input type="hidden" name="ste" value="<?php echo htmlspecialchars($ste); ?>">
            <label for="user">Filtrer par utilisateur :</label>
            <select name="user" id="user" onchange="this.form.submit()">
                <option value="">-- Tous les utilisateurs --</option>
                <?php foreach ($allUsers as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['Compte']); ?>" <?php if ($user_filter == $user['Compte']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($user['NomPrenom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
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
                                            <th>Type</th>
                                            <th>Marque</th>
                                            <th>Modèle</th>
                                            <th>Date d'entrée</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($material['NumSerie']); ?></td>
                                                <td><?php echo htmlspecialchars($material['TypeLibelle']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Marque']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Model']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Dateentree']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if ($user_filter && $user_filter == ($materials[0]['Compte'] ?? '')): ?>
                                    <div class="signature-block">
                                        <span class="signature-label">Signature de l'utilisateur :</span>
                                        <div class="signature-line"></div>
                                    </div>
                                <?php endif; ?>
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
