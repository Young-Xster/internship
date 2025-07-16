<?php
require_once 'config.php';


header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=export_' . date('Y-m-d_H-i-s') . '.csv');


$output = fopen('php://output', 'w');


fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));


$type = $_GET['type'] ?? '';
$ste = $_GET['ste'] ?? 'prod';


try {
    switch ($type) {
        case 'materiel':
            
            $headers = array(
                'N° Série', 
                'Utilisateur', 
                'Marque', 
                'Type', 
                'Modèle', 
                'Date Entrée', 
                'Stock', 
                'Observation'
            );
            fputcsv($output, $headers);
            
            $stmt = $pdo->prepare("SELECT m.*, u.NomPrenom, ma.Marque, t.Libelle as TypeLibelle FROM materiel m 
                                  LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte 
                                  LEFT JOIN marque ma ON m.CodeMarque = ma.Code 
                                  LEFT JOIN type t ON m.CodeType = t.CodeType 
                                  WHERE m.STE = ? 
                                  ORDER BY m.NumSerie DESC");
            $stmt->execute([$ste]);
            
        
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $exportRow = array(
                    $row['NumSerie'],
                    $row['NomPrenom'] ?? 'N/A',
                    $row['Marque'] ?? 'N/A',
                    $row['TypeLibelle'] ?? 'N/A',
                    $row['Model'] ?? 'N/A',
                    $row['Dateentree'] ?? 'N/A',
                    $row['stock'] ?? 'N/A',
                    $row['observation'] ?? 'N/A'
                );
                fputcsv($output, $exportRow);
            }
            break;
            
        case 'utilisateur':
           
            $headers = array(
                'Compte',
                'Nom et Prénom',
                'Email',
                'Téléphone',
                'Service'
            );
            fputcsv($output, $headers);
            
            $stmt = $pdo->prepare("SELECT u.*, s.Libelle as ServiceLibelle FROM utilisateur u 
                                  LEFT JOIN service s ON u.CodeService = s.CodeService 
                                  WHERE u.STE = ? 
                                  ORDER BY u.NomPrenom");
            $stmt->execute([$ste]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $exportRow = array(
                    $row['Compte'],
                    $row['NomPrenom'],
                    $row['Email'] ?? 'N/A',
                    $row['Tel'] ?? 'N/A',
                    $row['ServiceLibelle'] ?? 'N/A'
                );
                fputcsv($output, $exportRow);
            }
            break;
            
        case 'marque':
            // Define column headers
            $headers = array('Code', 'Marque');
            fputcsv($output, $headers);
            
            // Get data from database
            $stmt = $pdo->query("SELECT * FROM marque ORDER BY Marque");
            
            // Output each row of data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $exportRow = array($row['Code'], $row['Marque']);
                fputcsv($output, $exportRow);
            }
            break;
            
        case 'type':
            // Define column headers
            $headers = array('Code', 'Libellé');
            fputcsv($output, $headers);
            
            // Get data from database
            $stmt = $pdo->query("SELECT * FROM type ORDER BY Libelle");
            
            // Output each row of data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $exportRow = array($row['CodeType'], $row['Libelle']);
                fputcsv($output, $exportRow);
            }
            break;
            
        case 'service':
            // Define column headers
            $headers = array('Code', 'Libellé');
            fputcsv($output, $headers);
            
            // Get data from database
            $stmt = $pdo->prepare("SELECT * FROM service WHERE STE = ? ORDER BY Libelle");
            $stmt->execute([$ste]);
            
            // Output each row of data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $exportRow = array($row['CodeService'], $row['Libelle']);
                fputcsv($output, $exportRow);
            }
            break;
            
        case 'fournisseur':
            $headers = array(
                'Email',
                'Entreprise',
                'Nom et Prénom',
                'Adresse',
                'Tél. Fixe',
                'Tél. Mobile'
            );
            fputcsv($output, $headers);
            
          
            $stmt = $pdo->query("SELECT * FROM fournisseur ORDER BY CompanyName, NomComplet");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $exportRow = array(
                    $row['Email'],
                    $row['CompanyName'] ?? 'N/A',
                    $row['NomComplet'] ?? 'N/A',
                    $row['Adress'] ?? 'N/A',
                    $row['TelFix'] ?? 'N/A',
                    $row['TelMobile'] ?? 'N/A'
                );
                fputcsv($output, $exportRow);
            }
            break;
            
        default:
           
            die("Erreur: Type d'exportation non spécifié.");
    }
} catch (PDOException $e) {
    die("Erreur lors de l'exportation: " . $e->getMessage());
}

fclose($output);
exit;
?>
