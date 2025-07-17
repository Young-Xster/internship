<?php
require_once 'vendor/autoload.php';
require_once 'php/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Fetch data from the database
try {
    $stmt = $pdo->query("
        SELECT
            m.NumSerie, m.Model, m.classification, t.Libelle as TypeLibelle,
            u.NomPrenom, u.Compte,
            s.Libelle as ServiceLibelle,
            u.STE
        FROM
            materiel m
        JOIN
            utilisateur u ON m.CodeUtilisateur = u.Compte
        JOIN
            service s ON u.CodeService = s.CodeService
        LEFT JOIN
            type t ON m.CodeType = t.CodeType
        WHERE
            m.CodeUtilisateur IS NOT NULL AND m.CodeUtilisateur != ''
        ORDER BY
            u.STE, s.Libelle, u.NomPrenom, m.NumSerie
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Group data by STE -> Service -> User
$groupedData = [];
foreach ($data as $row) {
    $ste = $row['STE'] ?: 'Non spécifié';
    $service = $row['ServiceLibelle'] ?: 'Non spécifié';
    $user = $row['NomPrenom'] ?: 'Non spécifié';
    
    if (!isset($groupedData[$ste])) {
        $groupedData[$ste] = [];
    }
    if (!isset($groupedData[$ste][$service])) {
        $groupedData[$ste][$service] = [];
    }
    if (!isset($groupedData[$ste][$service][$user])) {
        $groupedData[$ste][$service][$user] = [];
    }
    
    $groupedData[$ste][$service][$user][] = $row;
}

// Get image path and encode it in base64
$logoPath = realpath('imgs/Logo_AAF.JPG');
$logoBase64 = '';
if ($logoPath) {
    $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
}

// Generate HTML content for the PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export Matériel</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-width: 150px; }
        .header h1 { margin: 0; }
        .societe-block { border: 2px solid #333; padding: 10px; margin-bottom: 20px; page-break-inside: avoid; }
        .societe-block h2 { background-color: #333; color: #fff; padding: 5px; margin-top: 0; }
        .service-block { margin-left: 20px; margin-bottom: 15px; page-break-inside: avoid; }
        .user-block { margin-left: 40px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        thead { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <?php if ($logoBase64): ?>
            <img src="<?php echo $logoBase64; ?>" alt="Logo AAF">
        <?php endif; ?>
        <h1>Inventaire Matériel - AAF</h1>
    </div>

    <?php if (empty($groupedData)): ?>
        <p>Aucun matériel attribué à un utilisateur n'a été trouvé.</p>
    <?php else: ?>
        <?php foreach ($groupedData as $ste => $services): ?>
            <div class="societe-block">
                <h2>Société: <?php echo strtoupper(htmlspecialchars($ste)); ?></h2>
                <?php foreach ($services as $service => $users): ?>
                    <div class="service-block">
                        <h3>Service: <?php echo htmlspecialchars($service); ?></h3>
                        <?php foreach ($users as $user => $materials): ?>
                            <div class="user-block">
                                <h4>Utilisateur: <?php echo htmlspecialchars($user); ?></h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>N° Série</th>
                                            <th>Type</th>
                                            <th>Modèle</th>
                                            <th>Classification</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($material['NumSerie']); ?></td>
                                                <td><?php echo htmlspecialchars($material['TypeLibelle']); ?></td>
                                                <td><?php echo htmlspecialchars($material['Model']); ?></td>
                                                <td><?php echo htmlspecialchars($material['classification']); ?></td>
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
</body>
</html>
<?php
$html = ob_get_clean();

// Configure Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

// Render the PDF
$dompdf->render();

// Stream the PDF to the browser for download
$dompdf->stream("inventaire_materiel_AAF.pdf", ["Attachment" => 1]);
