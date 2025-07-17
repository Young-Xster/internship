<?php
require_once 'php/config.php';

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

// Set headers for CSV export
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventaire_materiel_AAF_' . date('Y-m-d') . '.csv"');

// Create output
$output = fopen('php://output', 'w');

// Write BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header
fputcsv($output, ['Société', 'Service', 'Utilisateur', 'N° Série', 'Type', 'Modèle', 'Classification'], ';');

// Write data
foreach ($groupedData as $ste => $services) {
    foreach ($services as $service => $users) {
        foreach ($users as $user => $materials) {
            foreach ($materials as $material) {
                fputcsv($output, [
                    $ste,
                    $service,
                    $user,
                    $material['NumSerie'],
                    $material['TypeLibelle'],
                    $material['Model'],
                    $material['classification']
                ], ';');
            }
        }
    }
}

fclose($output);
?>
