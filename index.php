<?php
session_start();
if (!isset($_SESSION['userName'])) {
    header('Location: login.php');
    exit;
}
// Debug: log all POST requests and tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . ' POST: ' . json_encode($_POST) . ' TAB: ' . ($_GET['tab'] ?? '') . "\n", FILE_APPEND);
}
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
// Hey there! This is the main config import. Gotta have our DB and settings ready.
require_once 'php/config.php';
require_once 'php/initialize_db.php';
// Email functionality removed to improve performance

// This is a handy map to translate stock numbers to readable states
$stockMap = [
    0 => 'en-service',
    1 => 'en-stock',
    2 => 'endommage',
    3 => 'casse'
];
$stockLabelMap = [
    0 => 'En service',
    1 => 'En stock',
    2 => 'Endommagé',
    3 => 'Cassé'
];
$addStockLabelMap = [
    0 => 'en service',
    1 => 'en stock',
];
$editStockLabelMap = $stockLabelMap; 
$stateToStock = [
    'en-service' => 0,
    'en-stock' => 1,
    'endommage' => 2,
    'casse' => 3
];

// Just logging all POST requests for debugging. Super useful if something goes wrong!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/material_notifications.log', date('Y-m-d H:i:s') . ' POST: ' . json_encode($_POST) . "\n", FILE_APPEND);
}

// Initialize session variables for multi-materiel if needed
if (!isset($_SESSION['multi_materiel'])) {
    $_SESSION['multi_materiel'] = [];
}
if (!isset($_SESSION['multi_materiel_last'])) {
    $_SESSION['multi_materiel_last'] = [];
}

// If there's a POST, let's see what action the user wants to do
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $tab = $_GET['tab'] ?? 'materiel';
    $ste = $_POST['STE'] ?? 'prod';

    try {
        switch ($action) {
            case 'add_materiel_multiple':
                // Get the current count and total count from the form
                $count = $_POST['count'] ?? 1;
                $current = $_POST['current'] ?? 1;
                
                // Save the form data to session for reuse in next iterations
                if (!isset($_SESSION['multi_materiel_last'])) {
                    $_SESSION['multi_materiel_last'] = [];
                }
                
                // Store all form data except NumSerie for next iteration
                $formData = $_POST;
                unset($formData['NumSerie']); // Don't save NumSerie for next form
                $_SESSION['multi_materiel_last'] = $formData;
                
                // Process this material addition (same logic as add_materiel)
                $serial = $_POST['NumSerie'] ?? null;
                try {
                    $stmt = $pdo->prepare("INSERT INTO MATERIEL (NumSerie, Dateentree, Model, CodeType, CodeMarque, CodeFournisseur, STE, CodeUtilisateur, Processeur, graphique, disqdur, mhtz, mo, memoire, ip, ecran, pouce, observation, stock, classification, damage_cause, datefinservice) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    // Grab all the form data, or set to null if missing
                    $codeUtilisateur = !empty($_POST['CodeUtilisateur']) ? $_POST['CodeUtilisateur'] : NULL;
                    $codeMarque = !empty($_POST['CodeMarque']) ? $_POST['CodeMarque'] : NULL;
                    $codeType = !empty($_POST['CodeType']) ? $_POST['CodeType'] : NULL;
                    $codeFournisseur = !empty($_POST['CodeFournisseur']) ? $_POST['CodeFournisseur'] : NULL;
                    $dateentree = !empty($_POST['Dateentree']) ? $_POST['Dateentree'] : date('Y-m-d');

                    // Only include damage_cause if state requires it
                    $damageCause = null;
                    if (isset($_POST['stock']) && ($_POST['stock'] === 'endommage' || $_POST['stock'] === 'casse')) {
                        $damageCause = $_POST['damage_cause'] ?? null;
                    }

                    $stock = $_POST['stock'] ?? 'en-service';
                    $stockValue = isset($stateToStock[$stock]) ? $stateToStock[$stock] : 0;
                    
                    // Date fin service handling
                    if (isset($_POST['datefinservice']) && !empty($_POST['datefinservice'])) {
                        $datefinservice = $_POST['datefinservice'];
                    } else {
                        $datefinservice = ($stockValue == 3) ? date('Y-m-d H:i:s') : null;
                    }

                    $stmt->execute([
                        $serial,
                        $dateentree,
                        $_POST['Model'],
                        $codeType,
                        $codeMarque,
                        $codeFournisseur,
                        $_POST['STE'],
                        $codeUtilisateur,
                        $_POST['Processeur'],
                        $_POST['graphique'],
                        $_POST['disqdur'],
                        $_POST['mhtz'],
                        $_POST['mo'],
                        $_POST['memoire'],
                        $_POST['ip'],
                        $_POST['ecran'],
                        $_POST['pouce'],
                        $_POST['observation'],
                        $stockValue,
                        $_POST['classification'],
                        $damageCause,
                        $datefinservice
                    ]);
                    
                    // Check if we need to continue with more items
                    $remainingCount = $count - 1;
                    if ($remainingCount > 0) {
                        // Redirect to the form for the next material
                        header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&showForm=ajouter_plusieurs&count=$remainingCount&success=add_materiel_multiple");
                        exit();
                    } else {
                        // All materials added, clear session and redirect
                        unset($_SESSION['multi_materiel_last']);
                        header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&success=add_all_materiels");
                        exit();
                    }
                } catch (PDOException $e) {
                    // Oops, something went wrong with the DB insert
                    $error_message = "Une erreur est survenue lors de l'ajout du matériel multiple: " . $e->getMessage();
                }
                break;
            

                
            case 'add_materiel':
                // Check if the serial number exists in inventaire
                $serial = $_POST['NumSerie'] ?? null;
                $checkInventaireStmt = $pdo->prepare("SELECT * FROM inventaire WHERE NumSerie = ?");
                $checkInventaireStmt->execute([$serial]);
                $inventaireMateriel = $checkInventaireStmt->fetch(PDO::FETCH_ASSOC);
                if ($inventaireMateriel && !isset($_POST['recuperer_inventaire_confirm']) && !isset($_GET['force_add']) && !isset($_POST['force_add'])) {
                    // Show a minimal HTML page with two buttons for user choice
                    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Numéro déjà en inventaire</title></head><body style="font-family:sans-serif;text-align:center;padding:40px;">';
                    echo '<h2>Ce numéro de série existe déjà en inventaire.</h2>';
                    echo '<p>Voulez-vous le récupérer ?</p>';
                    echo '<form method="POST" style="display:inline;">';
                    foreach ($_POST as $k => $v) {
                        $v = htmlspecialchars($v, ENT_QUOTES);
                        echo "<input type='hidden' name='".htmlspecialchars($k, ENT_QUOTES)."' value='".$v."'>";
                    }
                    echo '<input type="hidden" name="recuperer_inventaire_confirm" value="1">';
                    echo '<button type="submit" style="margin:10px;padding:10px 20px;">Récupérer</button>';
                    echo '</form>';
                    echo '<form method="GET" action="index.php" style="display:inline;">';
                    echo '<input type="hidden" name="tab" value="materiel">';
                    echo '<input type="hidden" name="ste" value="' . htmlspecialchars($ste, ENT_QUOTES) . '">';
                    echo '<input type="hidden" name="showForm" value="materiel">';
                    echo '<input type="hidden" name="force_add" value="1">';
                    echo '<button type="submit" style="margin:10px;padding:10px 20px;">Ajouter comme nouveau</button>';
                    echo '</form>';
                    echo '</body></html>';
                    exit();
                } elseif ($inventaireMateriel && isset($_POST['recuperer_inventaire_confirm'])) {
                    // User confirmed to recover from inventaire
                    // Move from inventaire to materiel
                    $pdo->beginTransaction();
                    try {
                        $insert_stmt = $pdo->prepare(
                            "INSERT INTO materiel (
                                NumSerie, CodeMarque, CodeType, Model, CodeUtilisateur, Dateentree,
                                stock, observation, Processeur, memoire, disqdur, graphique, 
                                pouce, ecran, mhtz, mo, ip, classification, STE, CodeFournisseur, damage_cause
                            ) VALUES (
                                :NumSerie, :CodeMarque, :CodeType, :Model, :CodeUtilisateur, :Dateentree,
                                :stock, :observation, :Processeur, :memoire, :disqdur, :graphique,
                                :pouce, :ecran, :mhtz, :mo, :ip, :classification, :STE, NULL, NULL
                            )"
                        );
                        $params = [
                            'NumSerie' => $inventaireMateriel['NumSerie'],
                            'CodeMarque' => $inventaireMateriel['CodeMarque'],
                            'CodeType' => $inventaireMateriel['CodeType'],
                            'Model' => $inventaireMateriel['Model'],
                            'CodeUtilisateur' => $inventaireMateriel['CodeUtilisateur'],
                            'Dateentree' => $inventaireMateriel['Dateentree'],
                            'stock' => $inventaireMateriel['stock'],
                            'observation' => $inventaireMateriel['observation'],
                            'Processeur' => $inventaireMateriel['Processeur'],
                            'memoire' => $inventaireMateriel['memoire'],
                            'disqdur' => $inventaireMateriel['disqdur'],
                            'graphique' => $inventaireMateriel['graphique'],
                            'pouce' => $inventaireMateriel['pouce'],
                            'ecran' => $inventaireMateriel['ecran'],
                            'mhtz' => $inventaireMateriel['mhtz'],
                            'mo' => $inventaireMateriel['mo'],
                            'ip' => $inventaireMateriel['ip'],
                            'classification' => $inventaireMateriel['classification'],
                            'STE' => $inventaireMateriel['STE']
                        ];
                        $insert_stmt->execute($params);
                        $delete_stmt = $pdo->prepare("DELETE FROM inventaire WHERE NumSerie = ?");
                        $delete_stmt->execute([$inventaireMateriel['NumSerie']]);
                        $pdo->commit();
                        header('Location: index.php?tab=materiel&ste=' . urlencode($_POST['STE']) . '&success=1');
                        exit();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "Erreur lors de la récupération du matériel: " . $e->getMessage();
                    }
                }
                    // Debug: log the POST data and present_serials
                    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Fin Inventaire Debug: POST present[] = " . json_encode($present_serials) . "\n", FILE_APPEND);
                // This block adds a new materiel to the database
                try {

                    $stmt = $pdo->prepare("INSERT INTO MATERIEL (NumSerie, Dateentree, Model, CodeType, CodeMarque, CodeFournisseur, STE, CodeUtilisateur, Processeur, graphique, disqdur, mhtz, mo, memoire, ip, ecran, pouce, observation, stock, classification, damage_cause, datefinservice) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    // Grab all the form data, or set to null if missing
                    $codeUtilisateur = !empty($_POST['CodeUtilisateur']) ? $_POST['CodeUtilisateur'] : NULL;
                    $codeMarque = !empty($_POST['CodeMarque']) ? $_POST['CodeMarque'] : NULL;
                    $codeType = !empty($_POST['CodeType']) ? $_POST['CodeType'] : NULL;
                    $codeFournisseur = !empty($_POST['CodeFournisseur']) ? $_POST['CodeFournisseur'] : NULL;
                    $dateentree = !empty($_POST['Dateentree']) ? $_POST['Dateentree'] : date('Y-m-d');
                    $serial = $_POST['NumSerie'] ?? null;

                    // Only include damage_cause if state requires it
                    $damageCause = null;
                    if (isset($_POST['stock']) && ($_POST['stock'] === 'endommage' || $_POST['stock'] === 'casse')) {
                        $damageCause = $_POST['damage_cause'] ?? null;
                    }

                    $stock = $_POST['stock'] ?? 'en-service';
                    $stockValue = isset($stateToStock[$stock]) ? $stateToStock[$stock] : 0;
                    // Always set datefinservice to current date/time if stockValue == 3 (fin de service), else NULL
                    // Accept datefinservice from POST (JS), fallback to PHP if not provided
                    if (isset($_POST['datefinservice']) && !empty($_POST['datefinservice'])) {
                        $datefinservice = $_POST['datefinservice'];
                    } else {
                        $datefinservice = ($stockValue == 3) ? date('Y-m-d H:i:s') : null;
                    }
                    error_log('DEBUG: datefinservice value: ' . var_export($datefinservice, true));

                    $stmt->execute([
                        $serial,
                        $dateentree,
                        $_POST['Model'],
                        $codeType,
                        $codeMarque,
                        $codeFournisseur,
                        $_POST['STE'],
                        $codeUtilisateur,
                        $_POST['Processeur'],
                        $_POST['graphique'],
                        $_POST['disqdur'],
                        $_POST['mhtz'],
                        $_POST['mo'],
                        $_POST['memoire'],
                        $_POST['ip'],
                        $_POST['ecran'],
                        $_POST['pouce'],
                        $_POST['observation'],
                        $stockValue,
                        $_POST['classification'],
                        $damageCause,
                        $datefinservice
                    ]);

                    // Now let's grab all the details (including user name, type, etc.) for the email
                    $materielStmt = $pdo->prepare("SELECT m.*, u.NomPrenom, ma.Marque, t.Libelle as TypeLibelle, f.CompanyName, f.NomComplet as FournisseurNom FROM materiel m LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte LEFT JOIN marque ma ON m.CodeMarque = ma.Code LEFT JOIN type t ON m.CodeType = t.CodeType LEFT JOIN fournisseur f ON m.CodeFournisseur = f.Email WHERE m.NumSerie = ?");
                    $materielStmt->execute([$serial]);
                    $mat = $materielStmt->fetch(PDO::FETCH_ASSOC);

                    // Compose a nice email with all the details
                    $to = 'amarahelmi81@gmail.com';
                    $subject = 'Nouveau matériel ajouté: ' . htmlspecialchars($mat['NumSerie']);
                    $body = '<h3>Un nouveau matériel a été ajouté</h3>' .
                        '<ul>' .
                        '<li><strong>Numéro de série:</strong> ' . htmlspecialchars($mat['NumSerie']) . '</li>' .
                        '<li><strong>Modèle:</strong> ' . htmlspecialchars($mat['Model']) . '</li>' .
                        '<li><strong>Type:</strong> ' . htmlspecialchars($mat['TypeLibelle']) . '</li>' .
                        '<li><strong>Marque:</strong> ' . htmlspecialchars($mat['Marque']) . '</li>' .
                        '<li><strong>Date d\'entrée:</strong> ' . htmlspecialchars($mat['Dateentree']) . '</li>' .
                        '<li><strong>Processeur:</strong> ' . htmlspecialchars($mat['Processeur']) . '</li>' .
                        // Debug: log how many items were not present and moved
                        file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Fin Inventaire Debug: not_present_count = $not_present_count, moved_count = $moved_count\n", FILE_APPEND);
                        if ($moved_count === 0) {
                            file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Fin Inventaire Debug: No items moved. Check present[] and form submission.\n", FILE_APPEND);
                        }
                        '<li><strong>Carte Graphique:</strong> ' . htmlspecialchars($mat['graphique']) . '</li>' .
                        '<li><strong>Disque Dur:</strong> ' . htmlspecialchars($mat['disqdur']) . '</li>' .
                        '<li><strong>Fréquence (MHz):</strong> ' . htmlspecialchars($mat['mhtz']) . '</li>' .
                        '<li><strong>MO:</strong> ' . htmlspecialchars($mat['mo']) . '</li>' .
                        '<li><strong>Mémoire:</strong> ' . htmlspecialchars($mat['memoire']) . '</li>' .
                        '<li><strong>Adresse IP:</strong> ' . htmlspecialchars($mat['ip']) . '</li>' .
                        '<li><strong>Écran:</strong> ' . htmlspecialchars($mat['ecran']) . '</li>' .
                        '<li><strong>Pouces:</strong> ' . htmlspecialchars($mat['pouce']) . '</li>' .
                        '<li><strong>Classification:</strong> ' . htmlspecialchars($mat['classification']) . '</li>' .
                        '<li><strong>État:</strong> ' . htmlspecialchars($mat['stock']) . '</li>' .
                        '<li><strong>Cause du dommage:</strong> ' . htmlspecialchars($mat['damage_cause']) . '</li>' .
                        '<li><strong>Observation:</strong> ' . htmlspecialchars($mat['observation']) . '</li>' .
                        '<li><strong>Utilisateur:</strong> ' . htmlspecialchars($mat['NomPrenom']) . '</li>' .
                        '<li><strong>Fournisseur:</strong> ' . htmlspecialchars($mat['CompanyName'] ?: $mat['FournisseurNom']) . '</li>' .
                        '</ul>';
                    require_once __DIR__ . '/lib/mail_helper.php';
                    sendNewMaterielEmail($to, $subject, $body);
                    
                    // Save form data for next material if "plusieurs" is checked
                    if (isset($_POST['plusieurs']) && $_POST['plusieurs'] == '1') {
                        // Store all form data except NumSerie for next iteration
                        $formData = $_POST;
                        unset($formData['NumSerie']); // Don't save NumSerie for next form
                        $_SESSION['multi_materiel_last'] = $formData;
                        
                        // Show the form again with a special success message for multiple material addition
                        header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&showForm=materiel&success=add_materiel_multiple");
                    } else {
                        // Clean up any saved form data
                        $_SESSION['multi_materiel_last'] = [];
                        
                        // All done! Redirect back to the main page with a standard success message
                        header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&success=add_materiel");
                    }
                    exit();
                } catch (PDOException $e) {
                    // Oops, something went wrong with the DB insert
                    $error_message = "Une erreur est survenue lors de l'ajout du matériel: " . $e->getMessage();
                }
                break;
    
            case 'add_utilisateur':
                try {
                    // Check if user already exists in ANY environment
                    $checkStmt = $pdo->prepare("SELECT Compte, STE FROM utilisateur WHERE Compte = ?");
                    $checkStmt->execute([$_POST['Compte']]);
                    if ($existing_user = $checkStmt->fetch()) {
                        $existing_dept = strtoupper(htmlspecialchars($existing_user['STE']));
                        $error_message = "Ce compte utilisateur existe déjà dans l'environnement " . $existing_dept . ". Un utilisateur ne peut exister que dans un seul environnement.";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO utilisateur (Compte, CodeService, Email, NomPrenom, Tel, STE) VALUES (?, ?, ?, ?, ?, ?)");
                    $codeService = !empty($_POST['CodeService']) ? $_POST['CodeService'] : NULL;
                    $stmt->execute([$_POST['Compte'], $codeService, $_POST['Email'], $_POST['NomPrenom'], $_POST['Tel'], $_POST['STE']]);
                    header("Location: index.php?tab=utilisateur&ste=" . urlencode($_POST['STE']) . "&success=add_user");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout de l'utilisateur: " . $e->getMessage();
                }
                break;
            case 'modify_utilisateur':
                try {
                    $stmt = $pdo->prepare("UPDATE utilisateur SET CodeService = ?, Email = ?, NomPrenom = ?, Tel = ?, STE = ? WHERE Compte = ?");
                    $codeService = !empty($_POST['CodeService']) ? $_POST['CodeService'] : NULL;
                    $stmt->execute([
                        $codeService,
                        $_POST['Email'],
                        $_POST['NomPrenom'],
                        $_POST['Tel'],
                        $_POST['STE'],
                        $_POST['Compte']
                    ]);
                    header("Location: index.php?tab=utilisateur&ste=" . urlencode($_POST['STE']) . "&success=modify_user");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification de l'utilisateur: " . $e->getMessage();
                }
                break;
                
            case 'add_marque':
                try {
                    
                    $maxCodeStmt = $pdo->query("SELECT MAX(Code) as max_code FROM marque");
                    $maxCode = $maxCodeStmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO marque (Code, Marque) VALUES (?, ?)");
                    $stmt->execute([$maxCode + 1, $_POST['Marque']]);
                    header("Location: index.php?tab=marque&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=add_marque");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout de la marque. Veuillez réessayer.";
                }
                break;
                
            case 'add_type':
                try {
         
                    $maxCodeStmt = $pdo->query("SELECT MAX(CodeType) as max_code FROM type");
                    $maxCode = $maxCodeStmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO type (CodeType, Libelle) VALUES (?, ?)");
                    $stmt->execute([$maxCode + 1, $_POST['Libelle']]);
                    header("Location: index.php?tab=type&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=add_type");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout du type. Veuillez réessayer.";
                }
                break;
                
            case 'add_service':
                try {
                    // Auto-generate the next CodeService
                    $maxCodeStmt = $pdo->query("SELECT MAX(CodeService) as max_code FROM service");
                    $maxCode = $maxCodeStmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO service (CodeService, Libelle, STE) VALUES (?, ?, ?)");
                    $stmt->execute([$maxCode + 1, $_POST['Libelle'], $_POST['STE']]);
                    header("Location: index.php?tab=service&ste=" . urlencode($_POST['STE']) . "&success=add_service");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout du service: " . $e->getMessage();
                }
                break;
                
            case 'add_fournisseur':
                try {
                    $stmt = $pdo->prepare("INSERT INTO fournisseur (Email, CompanyName, NomComplet, Adress, TelFix, TelMobile) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_POST['Email'], $_POST['CompanyName'], $_POST['NomComplet'], $_POST['Adress'], $_POST['TelFix'], $_POST['TelMobile']]);
                    header("Location: index.php?tab=fournisseurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=add_fournisseur");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout du fournisseur. Veuillez vérifier les informations et réessayer.";
                }
                break;
                
            case 'delete_materiel':
                    try {
                        $stmt = $pdo->prepare("DELETE FROM materiel WHERE NumSerie = ?");
                        $stmt->execute([$_POST['NumSerie']]);
                        header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_materiel");
                        exit();
                    } catch (PDOException $e) {
                        $error_message = "Une erreur est survenue lors de la suppression du matériel. Il se peut qu'il soit encore lié à d'autres enregistrements.";
                    }
                    break;
            case 'delete_utilisateur':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeUtilisateur = ?");
                    $checkStmt->execute([$_POST['Compte']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header("Location: index.php?tab=utilisateurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=linked_user");
                        exit();
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE Compte = ?");
                        $stmt->execute([$_POST['Compte']]);
                        header("Location: index.php?tab=utilisateurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_user");
                        exit();
                    }
                } catch (PDOException $e) {
                    header("Location: index.php?tab=utilisateurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=delete_user");
                    exit();
                }
                break;
            case 'delete_marque':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeMarque = ?");
                    $checkStmt->execute([$_POST['Code']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header("Location: index.php?tab=marques&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=linked_marque");
                        
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM marque WHERE Code = ?");
                        $stmt->execute([$_POST['Code']]);
                    header("Location: index.php?tab=marque&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_marque");
                    exit();
                    }
                } catch (PDOException $e) {
                    header("Location: index.php?tab=marques&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=delete_marque");
                    
                }
                break;
            case 'delete_type':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeType = ?");
                    $checkStmt->execute([$_POST['CodeType']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header("Location: index.php?tab=types&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=linked_type");
                        exit();
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM type WHERE CodeType = ?");
                        $stmt->execute([$_POST['CodeType']]);
                    header("Location: index.php?tab=type&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_type");
                        exit();
                    }
                } catch (PDOException $e) {
                    header("Location: index.php?tab=types&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=delete_type");
                    exit();
                }
                break;
            case 'delete_service':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE CodeService = ?");
                    $checkStmt->execute([$_POST['CodeService']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header("Location: index.php?tab=services&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=linked_service");
                        exit();
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM service WHERE CodeService = ?");
                        $stmt->execute([$_POST['CodeService']]);
                    header("Location: index.php?tab=service&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_service");
                        exit();
                    }
                } catch (PDOException $e) {
                    header("Location: index.php?tab=services&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=delete_service");
                    exit();
                }
                break;
                    
            case 'delete_fournisseur':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeFournisseur = ?");
                    $checkStmt->execute([$_POST['Email']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header("Location: index.php?tab=fournisseurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=linked_fournisseur");
                        exit();
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM fournisseur WHERE Email = ?");
                        $stmt->execute([$_POST['Email']]);
                        header("Location: index.php?tab=fournisseurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_fournisseur");
                        exit();
                    }
                } catch (PDOException $e) {
                    header("Location: index.php?tab=fournisseurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=delete_fournisseur");
                    exit();
                }
                break;
            
          
            case 'modify_materiel':
                try {
                    // Get original material data for comparison
                    $original_materiel_stmt = $pdo->prepare("SELECT CodeUtilisateur, STE, stock FROM materiel WHERE NumSerie = ?");
                    $original_materiel_stmt->execute([trim($_POST['NumSerie'])]);
                    $original_materiel = $original_materiel_stmt->fetch(PDO::FETCH_ASSOC);
                    $original_user = $original_materiel['CodeUtilisateur'];
                    $original_ste = $original_materiel['STE'];
                    $original_stock = $original_materiel['stock'];

                    // Handle form data
                    $codeUtilisateur = !empty($_POST['CodeUtilisateur']) ? $_POST['CodeUtilisateur'] : NULL;
                    $codeMarque = !empty($_POST['CodeMarque']) ? $_POST['CodeMarque'] : NULL;
                    $codeType = !empty($_POST['CodeType']) ? $_POST['CodeType'] : NULL;
                    $codeFournisseur = !empty($_POST['CodeFournisseur']) ? $_POST['CodeFournisseur'] : NULL;
                    $dateentree = !empty($_POST['Dateentree']) ? $_POST['Dateentree'] : NULL;
                    $serial = trim($_POST['NumSerie']);

                    // Handle damage cause
                    $damageCause = null;
                    if (isset($_POST['stock']) && ($_POST['stock'] === 'endommage' || $_POST['stock'] === 'casse')) {
                        $damageCause = $_POST['damage_cause'] ?? null;
                    }

                    // Use the stock value directly as string
                    $stock = $_POST['stock'] ?? 'en-service';
                    $stockValue = isset($stateToStock[$stock]) ? $stateToStock[$stock] : 0;
                    // Set datefinservice only when transitioning to casse, otherwise do not update it
                    $datefinservice = null;
                    $updateDateFinService = false;
                    if ($original_stock != 3 && $stockValue == 3) {
                        $datefinservice = date('Y-m-d H:i:s');
                        $updateDateFinService = true;
                    }
                            
                    // Update material first
                    if ($updateDateFinService) {
                        $stmt = $pdo->prepare("UPDATE materiel SET 
                            Dateentree = ?, Model = ?, CodeType = ?, CodeMarque = ?, CodeFournisseur = ?, 
                            STE = ?, CodeUtilisateur = ?, Processeur = ?, graphique = ?, disqdur = ?, 
                            mhtz = ?, mo = ?, memoire = ?, ip = ?, ecran = ?, pouce = ?, 
                            observation = ?, stock = ?, classification = ?, damage_cause = ?, datefinservice = ?
                            WHERE NumSerie = ?");
                        $stmt->execute([
                            $dateentree, $_POST['Model'], $codeType, $codeMarque, $codeFournisseur,
                            $_POST['STE'], $codeUtilisateur, $_POST['Processeur'], $_POST['graphique'], $_POST['disqdur'],
                            $_POST['mhtz'], $_POST['mo'], $_POST['memoire'], $_POST['ip'], $_POST['ecran'], $_POST['pouce'],
                            $_POST['observation'], $stockValue, $_POST['classification'], $damageCause, $datefinservice !== null ? $datefinservice : null, $serial
                        ]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE materiel SET 
                            Dateentree = ?, Model = ?, CodeType = ?, CodeMarque = ?, CodeFournisseur = ?, 
                            STE = ?, CodeUtilisateur = ?, Processeur = ?, graphique = ?, disqdur = ?, 
                            mhtz = ?, mo = ?, memoire = ?, ip = ?, ecran = ?, pouce = ?, 
                            observation = ?, stock = ?, classification = ?, damage_cause = ?
                            WHERE NumSerie = ?");
                        $stmt->execute([
                            $dateentree, $_POST['Model'], $codeType, $codeMarque, $codeFournisseur,
                            $_POST['STE'], $codeUtilisateur, $_POST['Processeur'], $_POST['graphique'], $_POST['disqdur'],
                            $_POST['mhtz'], $_POST['mo'], $_POST['memoire'], $_POST['ip'], $_POST['ecran'], $_POST['pouce'],
                            $_POST['observation'], $stockValue, $_POST['classification'], $damageCause, $serial
                        ]);
                    }

                    
                try {
                    $new_user = $_POST['CodeUtilisateur'];
                    $new_ste = $_POST['STE'];
                    
                    // Get the user names
                    $prev_user_stmt = $pdo->prepare("SELECT NomPrenom FROM utilisateur WHERE Compte = ?");
                    $new_user_stmt = $pdo->prepare("SELECT NomPrenom FROM utilisateur WHERE Compte = ?");
                    
                    $prev_user_name = 'Utilisateur inconnu';
                    $new_user_name = 'Utilisateur inconnu';
                    
                    if ($original_user) {
                        $prev_user_stmt->execute([$original_user]);
                        $prev_user_result = $prev_user_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($prev_user_result) {
                            $prev_user_name = $prev_user_result['NomPrenom'];
                        }
                    }
                    
                    if ($new_user) {
                        $new_user_stmt->execute([$new_user]);
                        $new_user_result = $new_user_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($new_user_result) {
                            $new_user_name = $new_user_result['NomPrenom'];
                        }
                    }
                    
                    // Log changes in user or state
                    $normalized_old = strtolower(str_replace([' ', '-'], '', $stockLabelMap[$original_stock] ?? $original_stock));
                    $normalized_new = strtolower(str_replace([' ', '-'], '', $stockLabelMap[$stockValue] ?? $stock));
                    if ((($original_user !== $new_user) || ($normalized_old !== $normalized_new)) && $stockValue !== $stateToStock['en_reparation'] && $original_stock !== $stateToStock['en_reparation']) {
                        $history_stmt = $pdo->prepare("INSERT INTO materiel_history 
                            (numserie, prev_state, new_state, previous_owner , new_owner, date_change, user_id, notes) 
                            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                        $notes = "Modification: ";
                        if ($original_user !== $new_user) {
                            $notes .= "Utilisateur changé de $prev_user_name à $new_user_name. ";
                        }
                        if ($normalized_old !== $normalized_new) {
                            $notes .= "État changé de " . ($stockLabelMap[$original_stock] ?? $original_stock) . " à " . ($stockLabelMap[$stockValue] ?? $stock) . ".";
                        }
                        $history_stmt->execute([
                            $serial,
                            $stockLabelMap[$original_stock] ?? $original_stock,
                            $stockLabelMap[$stockValue] ?? $stock,
                            $original_user,
                            $new_user,
                            $_SESSION['userName'] ?? 'system',
                            $notes
                        ]);
                    }
                } catch (Exception $historyEx) {
                    // Just log history error but continue with redirect
                    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - History Error: " . $historyEx->getMessage() . "\n", FILE_APPEND);
                }

                    // Always redirect after successful update
                    header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&success=modify_materiel");
                    exit();
                } catch (PDOException $e) {
                    // Log detailed error
                    $log_message = date('Y-m-d H:i:s') . " - Modify Materiel Error: " . $e->getMessage() . "\n";
                    file_put_contents(__DIR__ . '/error.log', $log_message, FILE_APPEND);
                    
                    $error_message = "Une erreur est survenue lors de la modification du matériel. Veuillez vérifier les informations et réessayer.";
                }
                break;
            case 'modify_marque':
                try {
                    $stmt = $pdo->prepare("UPDATE marque SET Marque = ? WHERE Code = ?");
                    $stmt->execute([$_POST['Marque'], $_POST['Code']]);
                    header("Location: index.php?tab=marque&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=modify_marque");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification de la marque. Veuillez réessayer.";
                     header("Location: index.php?tab=marque&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=modify_marque");
                }
                break;
            case 'modify_type':
                try {
                    $stmt = $pdo->prepare("UPDATE type SET Libelle = ? WHERE CodeType = ?");
                    $stmt->execute([$_POST['Libelle'], $_POST['CodeType']]);
                    header("Location: index.php?tab=type&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=modify_type");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification du type. Veuillez réessayer.";
                    header("Location: index.php?tab=types&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&error=modify_types");
                    exit();
                }
                break;
            case 'modify_service':
                try {
                    $stmt = $pdo->prepare("UPDATE service SET Libelle = ?, STE = ? WHERE CodeService = ?");
                    $stmt->execute([$_POST['Libelle'], $_POST['STE'], $_POST['CodeService']]);
                    header("Location: index.php?tab=service&ste=" . urlencode($_POST['STE']) . "&success=modify_service");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification du service. Veuillez réessayer.";
                }
                break;
                
            case 'modify_fournisseur':
                try {
                    $stmt = $pdo->prepare("UPDATE fournisseur SET CompanyName = ?, NomComplet = ?, Adress = ?, TelFix = ?, TelMobile = ? WHERE Email = ?");
                    $stmt->execute([$_POST['CompanyName'], $_POST['NomComplet'], $_POST['Adress'], $_POST['TelFix'], $_POST['TelMobile'], $_POST['Email']]);
                    header("Location: index.php?tab=fournisseurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=modify_fournisseur");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification du fournisseur. Veuillez vérifier les informations et réessayer.";
                }
                break;
                case 'transfer_materiel':
                    try {
                        $num_serie = $_POST['NumSerie'];
                        $code_utilisateur = $_POST['CodeUtilisateur'];
                        $current_ste = $_POST['STE'];
                        $target_ste = $_POST['target_STE'];
                        
                        // Get current material info before update
                        $prevStmt = $pdo->prepare("SELECT m.*, u.NomPrenom FROM materiel m LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte WHERE m.NumSerie = ?");
                        $prevStmt->execute([$num_serie]);
                        $prevMaterial = $prevStmt->fetch();
                        $oldUserName = $prevMaterial['NomPrenom'] ?? 'Non attribué';
                        
                        // Get new user info
                        $newUserStmt = $pdo->prepare("SELECT NomPrenom FROM utilisateur WHERE Compte = ?");
                        $newUserStmt->execute([$code_utilisateur]);
                        $newUserName = $newUserStmt->fetchColumn() ?? 'Non attribué';
                        
                        // Update the material record with the new user and change STE
                        $stmt = $pdo->prepare("UPDATE materiel SET CodeUtilisateur = ?, STE = ? WHERE NumSerie = ?");
                        $stmt->execute([$code_utilisateur, $target_ste, $num_serie]);
                        
                        // Log the transfer in materiel_history
                        $history_stmt = $pdo->prepare("INSERT INTO materiel_history (numserie, prev_state, new_state, previous_owner, new_owner, date_change, user_id, notes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                        $history_stmt->execute([
                            $num_serie,
                            $prevMaterial['stock'], // previous state
                            $prevMaterial['stock'], // new state (no state change)
                            $prevMaterial['CodeUtilisateur'], // previous owner
                            $code_utilisateur, // new owner
                            'system', // or the actual user performing the transfer
                            "Transfert de $oldUserName à $newUserName"
                        ]);
                        
                        header("Location: index.php?tab=materiel&ste=" . urlencode($current_ste) . "&success=transfer_materiel");
                        exit();
                    } catch (PDOException $e) {
                        $error_message = "Erreur lors du transfert du matériel: " . $e->getMessage();
                    }
                    break;
                case 'transfer_utilisateur':
                    try {
                        $compte = $_POST['Compte'];
                        $code_service = $_POST['CodeService'];
                        $target_ste = $_POST['target_STE'];
                        
                        // Update the user with the new service and change STE
                        $stmt = $pdo->prepare("UPDATE utilisateur SET CodeService = ?, STE = ? WHERE Compte = ?");
                        $stmt->execute([$code_service, $target_ste, $compte]);
                        
                        header("Location: index.php?tab=utilisateur&ste=" . urlencode($target_ste) . "&success=transfer_user");
                        exit();
                    } catch (PDOException $e) {
                        $error_message = "Erreur lors du transfert de l'utilisateur: " . $e->getMessage();
                    }
                    break;
            
                case 'change_state':
                    $numSerie = $_POST['NumSerie'] ?? '';
                    $stock = $_POST['stock'] ?? 'en-service';
                    $stockValue = isset($stateToStock[$stock]) ? $stateToStock[$stock] : 0;
                    $redirectState = $_POST['redirect_state'] ?? $selected_state ?? 'en-service';
                    $redirectSte = $_POST['STE'] ?? $ste_filter ?? 'prod';
                    $datefinservice = $_POST['datefinservice'] ?? null;

                    // Fetch original state and user before update
                    $original_materiel_stmt = $pdo->prepare("SELECT CodeUtilisateur, stock FROM materiel WHERE NumSerie = ?");
                    $original_materiel_stmt->execute([$numSerie]);
                    $original_materiel = $original_materiel_stmt->fetch(PDO::FETCH_ASSOC);
                    $original_user = $original_materiel['CodeUtilisateur'] ?? null;
                    $original_stock = $original_materiel['stock'] ?? null;

                    // Update state in materiel table
                    if ($numSerie !== '') {
                        if ($stockValue == 3) { // fin de service
                            if (!$datefinservice) {
                                $datefinservice = date('Y-m-d H:i:s');
                            }
                            $stmt = $pdo->prepare('UPDATE materiel SET stock = ?, datefinservice = ? WHERE NumSerie = ?');
                            $stmt->execute([$stockValue, $datefinservice, $numSerie]);
                        } else {
                            $stmt = $pdo->prepare('UPDATE materiel SET stock = ?, datefinservice = NULL WHERE NumSerie = ?');
                            $stmt->execute([$stockValue, $numSerie]);
                        }
                    }

                    // Get user names for history log
                    $prev_user_stmt = $pdo->prepare("SELECT NomPrenom FROM utilisateur WHERE Compte = ?");
                    $new_user_stmt = $pdo->prepare("SELECT NomPrenom FROM utilisateur WHERE Compte = ?");
                    $prev_user_name = 'Utilisateur inconnu';
                    $new_user_name = 'Utilisateur inconnu';
                    $new_user = $_POST['CodeUtilisateur'] ?? $original_user;

                    if ($original_user) {
                        $prev_user_stmt->execute([$original_user]);
                        $prev_user_result = $prev_user_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($prev_user_result) {
                            $prev_user_name = $prev_user_result['NomPrenom'];
                        }
                    }
                    if ($new_user) {
                        $new_user_stmt->execute([$new_user]);
                        $new_user_result = $new_user_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($new_user_result) {
                            $new_user_name = $new_user_result['NomPrenom'];
                        }
                    }

                    // Log changes in user or state
                    $normalized_old = strtolower(str_replace([' ', '-'], '', $stockLabelMap[$original_stock] ?? $original_stock));
                    $normalized_new = strtolower(str_replace([' ', '-'], '', $stockLabelMap[$stockValue] ?? $stock));
                    if ((($original_user !== $new_user) || ($normalized_old !== $normalized_new)) && $stockValue !== $stateToStock['en_reparation'] && $original_stock !== $stateToStock['en_reparation']) {
                        $history_stmt = $pdo->prepare("INSERT INTO materiel_history 
                            (numserie, prev_state, new_state, previous_owner , new_owner, date_change, user_id, notes) 
                            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                        $notes = "Modification: ";
                        if ($original_user !== $new_user) {
                            $notes .= "Utilisateur changé de $prev_user_name à $new_user_name. ";
                        }
                        if ($normalized_old !== $normalized_new) {
                            $notes .= "État changé de " . ($stockLabelMap[$original_stock] ?? $original_stock) . " à " . ($stockLabelMap[$stockValue] ?? $stock) . ".";
                        }
                        $history_stmt->execute([
                            $numSerie,
                            $stockLabelMap[$original_stock] ?? $original_stock,
                            $stockLabelMap[$stockValue] ?? $stock,
                            $original_user,
                            $new_user,
                            $_SESSION['userName'] ?? 'system',
                            $notes
                        ]);
                    }

                    header('Location: index.php?tab=materiel&ste=' . urlencode($redirectSte) . '&state=' . urlencode($redirectState));
                    exit;
                case 'fin_inventaire':
                    $present_serials = isset($_POST['present']) ? $_POST['present'] : [];
                    $ste_filter = $_POST['ste'] ?? 'prod';

                    // Get all materials for the current STE to compare against the 'present' list
                    $stmt = $pdo->prepare("SELECT * FROM materiel WHERE STE = ?");
                    $stmt->execute([$ste_filter]);
                    $all_materiels = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $pdo->beginTransaction();
                    try {
                        $moved_count = 0;
                        foreach ($all_materiels as $materiel) {
                            $numSerie = $materiel['NumSerie'];
                            if (!in_array($numSerie, $present_serials)) {
                                // This item was not checked, so move it to inventaire
                                // Match the actual inventaire table structure from create_inventaire_table.sql
                                $insert_stmt = $pdo->prepare(
                                    "INSERT INTO inventaire (
                                        NumSerie, CodeMarque, CodeType, Model, CodeUtilisateur, Dateentree,
                                        stock, observation, Processeur, memoire, disqdur, graphique, 
                                        pouce, ecran, mhtz, mo, ip, classification, STE, 
                                        dateinvent
                                    ) VALUES (
                                        :NumSerie, :CodeMarque, :CodeType, :Model, :CodeUtilisateur, :Dateentree,
                                        :stock, :observation, :Processeur, :memoire, :disqdur, :graphique,
                                        :pouce, :ecran, :mhtz, :mo, :ip, :classification, :STE,
                                        NOW()
                                    )"
                                );
                                
                                // Only use valid columns for inventaire
                                $params = [
                                    'NumSerie' => $materiel['NumSerie'],
                                    'CodeMarque' => $materiel['CodeMarque'],
                                    'CodeType' => $materiel['CodeType'],
                                    'Model' => $materiel['Model'],
                                    'CodeUtilisateur' => $materiel['CodeUtilisateur'],
                                    'Dateentree' => $materiel['Dateentree'],
                                    'stock' => $materiel['stock'],
                                    'observation' => $materiel['observation'],
                                    'Processeur' => $materiel['Processeur'],
                                    'memoire' => $materiel['memoire'],
                                    'disqdur' => $materiel['disqdur'],
                                    'graphique' => $materiel['graphique'],
                                    'pouce' => $materiel['pouce'],
                                    'ecran' => $materiel['ecran'],
                                    'mhtz' => $materiel['mhtz'],
                                    'mo' => $materiel['mo'],
                                    'ip' => $materiel['ip'],
                                    'classification' => $materiel['classification'],
                                    'STE' => $materiel['STE']
                                ];
                                $insert_stmt->execute($params);

                                // Delete from the main materiel table
                                $delete_stmt = $pdo->prepare("DELETE FROM materiel WHERE NumSerie = ?");
                                $delete_stmt->execute([$numSerie]);
                                
                                $moved_count++;
                            }
                        }

                    
                        $pdo->commit();
                        
                        // Log the operation details for debugging
                        file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Fin Inventaire Success: Moved $moved_count items to inventaire table for STE: $ste_filter\n", FILE_APPEND);
                        
                        header('Location: index.php?tab=inventaire&ste=' . urlencode($ste_filter) . '&success=1');
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        // Log the actual error to a file for debugging, including stack trace and all exception details
                        $error_details = date('Y-m-d H:i:s') . " - Fin Inventaire Error: " . $e->getMessage() . "\n";
                        $error_details .= "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
                        $error_details .= "Trace: " . $e->getTraceAsString() . "\n";
                        file_put_contents(__DIR__ . '/error.log', $error_details, FILE_APPEND);
                        
                        header('Location: index.php?tab=inventaire&ste=' . urlencode($ste_filter) . '&error=1');
                        exit;
                    }
                case 'recuperer_inventaire':
                    $numSerie = $_POST['NumSerie'] ?? '';
                    $current_ste = $_POST['STE'] ?? 'prod';
                    if ($numSerie !== '') {
                        $pdo->beginTransaction();
                        try {
                            // Get the record from inventaire table
                            $select_stmt = $pdo->prepare("SELECT * FROM inventaire WHERE NumSerie = ?");
                            $select_stmt->execute([$numSerie]);
                            $inventaire_item = $select_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($inventaire_item) {
                                // Insert back into materiel table with correct structure
                                $insert_stmt = $pdo->prepare(
                                    "INSERT INTO materiel (
                                        NumSerie, CodeMarque, CodeType, Model, CodeUtilisateur, Dateentree,
                                        stock, observation, Processeur, memoire, disqdur, graphique, 
                                        pouce, ecran, mhtz, mo, ip, classification, STE, CodeFournisseur, damage_cause
                                    ) VALUES (
                                        :NumSerie, :CodeMarque, :CodeType, :Model, :CodeUtilisateur, :Dateentree,
                                        :stock, :observation, :Processeur, :memoire, :disqdur, :graphique,
                                        :pouce, :ecran, :mhtz, :mo, :ip, :classification, :STE, NULL, NULL
                                    )"
                                );
                                
                                $params = [
                                    'NumSerie' => $inventaire_item['NumSerie'],
                                    'CodeMarque' => $inventaire_item['CodeMarque'],
                                    'CodeType' => $inventaire_item['CodeType'],
                                    'Model' => $inventaire_item['Model'],
                                    'CodeUtilisateur' => $inventaire_item['CodeUtilisateur'],
                                    'Dateentree' => $inventaire_item['Dateentree'],
                                    'stock' => $inventaire_item['stock'],
                                    'observation' => $inventaire_item['observation'],
                                    'Processeur' => $inventaire_item['Processeur'],
                                    'memoire' => $inventaire_item['memoire'],
                                    'disqdur' => $inventaire_item['disqdur'],
                                    'graphique' => $inventaire_item['graphique'],
                                    'pouce' => $inventaire_item['pouce'],
                                    'ecran' => $inventaire_item['ecran'],
                                    'mhtz' => $inventaire_item['mhtz'],
                                    'mo' => $inventaire_item['mo'],
                                    'ip' => $inventaire_item['ip'],
                                    'classification' => $inventaire_item['classification'],
                                    'STE' => $inventaire_item['STE']
                                ];
                                
                                $insert_stmt->execute($params);

                                // Delete from inventaire table
                                $delete_stmt = $pdo->prepare("DELETE FROM inventaire WHERE NumSerie = ?");
                                $delete_stmt->execute([$numSerie]);
                            }
                            
                            $pdo->commit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Recuperer Inventaire Error: " . $e->getMessage() . "\n", FILE_APPEND);
                            $error_message = "Erreur lors de la récupération du matériel: " . $e->getMessage();
                            header('Location: index.php?tab=inventaire&ste=' . urlencode($current_ste) . '&error=1');
                            exit;
                        }
                    }
                    // Redirect back to the inventaire tab to see the list update
                    header('Location: index.php?tab=inventaire&ste=' . urlencode($current_ste) . '&success=1');
                    exit;
            case 'recuperer_reparation':
                $date_recuperated = date('Y-m-d H:i:s');
                $numserie = $_POST['NumSerie'] ?? '';
                if($numserie !== ''){
                    // 1. Update repair history
                    $select = $pdo->prepare("SELECT id FROM materiel_repair_history WHERE NumSerie = ? AND date_recuperated IS NULL ORDER BY date_sent DESC LIMIT 1");
                    $select->execute([$numserie]);
                    $row = $select->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['id'])) {
                        $update = $pdo->prepare("UPDATE materiel_repair_history SET date_recuperated = ? WHERE id = ?");
                        $update->execute([$date_recuperated, $row['id']]);
                    }

                    // 2. Move from materiel_en_reparation back to materiel
                    $selectMat = $pdo->prepare("SELECT * FROM materiel_en_reparation WHERE NumSerie = ?");
                    $selectMat->execute([$numserie]);
                    $mat = $selectMat->fetch(PDO::FETCH_ASSOC);
                    if ($mat) {
                        // Insert into materiel (adjust columns as needed)
                        $fields = [
                            'NumSerie', 'CodeMarque', 'CodeType', 'Model', 'CodeUtilisateur', 'Dateentree',
                            'stock', 'observation', 'Processeur', 'memoire', 'disqdur', 'graphique',
                            'pouce', 'ecran', 'mhtz', 'mo', 'ip', 'classification', 'STE', 'CodeFournisseur', 'damage_cause'
                        ];
                        $insert_fields = implode(", ", $fields);
                        $insert_placeholders = ":" . implode(", :", $fields);
                        $insert = $pdo->prepare("INSERT INTO materiel ($insert_fields) VALUES ($insert_placeholders)");
                        $params = [];
                        foreach ($fields as $f) {
                            $params[$f] = $mat[$f] ?? null;
                        }
                        $insert->execute($params);

                        // Delete from materiel_en_reparation
                        $del = $pdo->prepare("DELETE FROM materiel_en_reparation WHERE NumSerie = ?");
                        $del->execute([$numserie]);
                    }

                    $success_message = "Le matériel a été récupéré dans la liste principale.";
                }
                header('Location: index.php?tab=maintenance&ste=' . urlencode($ste_filter));
                exit;
        }
    } catch (Exception $e) {
        $error_message = "Erreur lors du traitement de la demande: " . $e->getMessage();
    }
}

$success_messages = [
    'add_materiel' => 'Le matériel a été ajouté avec succès.',
    'add_materiel_multiple' => 'Le matériel a été ajouté avec succès. Veuillez ajouter un autre matériel.',
    'add_user' => "L'utilisateur a été ajouté avec succès.",
    'add_marque' => "La marque a été ajoutée avec succès.",
    'add_type' => "Le type a été ajouté avec succès.",
    'add_service' => "Le service a été ajouté avec succès.",
    'add_fournisseur' => "Le fournisseur a été ajouté avec succès.",
    'delete_materiel' => "Le matériel a été supprimé avec succès.",
    'delete_user' => "L'utilisateur a été supprimé avec succès.",
    'delete_marque' => "La marque a été supprimée avec succès.",
    'delete_type' => "Le type a été supprimé avec succès.",
    'delete_service' => "Le service a été supprimé avec succès.",
    'delete_fournisseur' => "Le fournisseur a été supprimé avec succès.",
    'modify_materiel' => "Le matériel a été modifié avec succès.",
    'modify_user' => "L'utilisateur a été modifié avec succès.",
    'modify_marque' => "La marque a été modifiée avec succès.",
    'modify_type' => "Le type a été modifié avec succès.",
    'modify_service' => "Le service a été modifié avec succès.",
    'modify_fournisseur' => "Le fournisseur a été modifié avec succès.",
    'transfer_materiel' => "Le matériel a été transféré avec succès.",
    'transfer_user' => "L'utilisateur a été transféré avec succès.",
    '1' => "L'inventaire a été finalisé avec succès!"
];

$error_messages = [
    '1' => "Erreur lors de la finalisation de l'inventaire. Consultez les logs pour plus de détails.",
    'linked_user' => "L'utilisateur ne peut pas être supprimé car il est lié à un ou plusieurs matériels.",
    'linked_marque' => "La marque ne peut pas être supprimée car elle est liée à un ou plusieurs matériels.",
    'linked_type' => "Le type ne peut pas être supprimé car il est lié à un ou plusieurs matériels.",
    'linked_service' => "Le service ne peut pas être supprimé car il est lié à un ou plusieurs utilisateurs.",
    'linked_fournisseur' => "Le fournisseur ne peut pas être supprimé car il est lié à un ou plusieurs matériels.",
    'delete_user' => "Une erreur est survenue lors de la suppression de l'utilisateur.",
    'delete_marque' => "Une erreur est survenue lors de la suppression de la marque.",
    'delete_type' => "Une erreur est survenue lors de la suppression du type.",
    'delete_service' => "Une erreur est survenue lors de la suppression du service.",
    'delete_fournisseur' => "Une erreur est survenue lors de la suppression du fournisseur."
];

$success_code = $_GET['success'] ?? null;
$error_code = $_GET['error'] ?? null;
$success_message = null;
$error_message = null;

if ($success_code && isset($success_messages[$success_code])) {
    $success_message = $success_messages[$success_code];
} elseif ($error_code && isset($error_messages[$error_code])) {
    $error_message = $error_messages[$error_code];
}

$editMode = false;
$transferMode = false;
$editMateriel = null;
$editUtilisateur = null;
$editMarque = null;
$editTypeEntity = null;
$editService = null;
$editFournisseur = null;
$editEntity = null;
$transferMateriel = null;
$transferUtilisateur = null;
$transferEntity = null;

$showFormParam = isset($_GET['showForm']) ? $_GET['showForm'] : null;

// Check if we're in multi-materiel mode and have previous data
$previousMaterielData = null;
if ($showFormParam === 'ajouter_plusieurs' && isset($_SESSION['multi_materiel_last'])) {
    $previousMaterielData = $_SESSION['multi_materiel_last'];
}

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editMode = true;
    $editId = $_GET['edit'];
    $editType = $_GET['type'] ?? 'materiel';
    
    try {
        switch ($editType) {
            case 'materiel':
                $stmt = $pdo->prepare("SELECT * FROM materiel WHERE NumSerie = ?");
                $stmt->execute([$editId]);
                $editMateriel = $stmt->fetch();
                $editEntity = $editMateriel;
                break;
            case 'utilisateur':
                $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE Compte = ?");
                $stmt->execute([$editId]);
                $editUtilisateur = $stmt->fetch();
                $editEntity = $editUtilisateur;
                break;
            case 'marque':
                $stmt = $pdo->prepare("SELECT * FROM marque WHERE Code = ?");
                $stmt->execute([$editId]);
                $editMarque = $stmt->fetch();
                $editEntity = $editMarque;
                break;
            case 'type':
                $stmt = $pdo->prepare("SELECT * FROM type WHERE CodeType = ?");
                $stmt->execute([$editId]);
                $editTypeEntity = $stmt->fetch();
                $editEntity = $editTypeEntity;
                break;
            case 'service':
                $stmt = $pdo->prepare("SELECT * FROM service WHERE CodeService = ?");
                $stmt->execute([$editId]);
                $editService = $stmt->fetch();
                $editEntity = $editService;
                break;
            case 'fournisseur':
                $stmt = $pdo->prepare("SELECT * FROM fournisseur WHERE Email = ?");
                $stmt->execute([$editId]);
                $editFournisseur = $stmt->fetch();
                $editEntity = $editFournisseur;
                break;
        }
        
        if (!$editEntity) {
            $error_message = "Élément non trouvé.";
            $editMode = false;
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors du chargement des données pour la modification.";
        $editMode = false;
    }
}

// Transfer mode detection
if (isset($_GET['transfer']) && !empty($_GET['transfer'])) {
    $transferMode = true;
    $transferId = $_GET['transfer'];
    $transferType = $_GET['type'] ?? 'materiel';
    
    try {
        switch ($transferType) {
            case 'materiel':
                $stmt = $pdo->prepare("SELECT m.*, u.NomPrenom, ma.Marque, t.Libelle as TypeLibelle FROM materiel m LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte LEFT JOIN marque ma ON m.CodeMarque = ma.Code LEFT JOIN type t ON m.CodeType = t.CodeType WHERE m.NumSerie = ?");
                $stmt->execute([$transferId]);
                $transferMateriel = $stmt->fetch();
                $transferEntity = $transferMateriel;
                break;
            case 'utilisateur':
                $stmt = $pdo->prepare("SELECT u.*, s.Libelle as ServiceLibelle FROM utilisateur u LEFT JOIN service s ON u.CodeService = s.CodeService WHERE u.Compte = ?");
                $stmt->execute([$transferId]);
                $transferUtilisateur = $stmt->fetch();
                $transferEntity = $transferUtilisateur;
                break;
        }
        
        if (!$transferEntity) {
            $error_message = "Élément non trouvé pour le transfert.";
            $transferMode = false;
        }
    } catch (PDOException $e) {
        $error_message = "Erreur lors du chargement des données pour le transfert.";
        $transferMode = false;
    }
}

$activeTab = 'materiel';
if ($editMode || $transferMode) {
    
    $tabMapping = [
        'materiel' => 'materiel',
        'inventaire' => 'inventaire',
        'utilisateur' => 'utilisateurs',
        'marque' => 'marques',
        'type' => 'types',
        'service' => 'services',
        'fournisseur' => 'fournisseurs',
        'maintenance' => 'maintenance'
    ];
    $activeTab = $tabMapping[$editType ?? $transferType ?? 'materiel'] ?? 'materiel';
} elseif (isset($_GET['tab'])) {
    
    $tabMapping = [
        'materiel' => 'materiel',
        'inventaire' => 'inventaire',
        'utilisateur' => 'utilisateurs',
        'marque' => 'marques',
        'type' => 'types',
        'service' => 'services',
        'fournisseurs' => 'fournisseurs',
        'maintenance' => 'maintenance'
    ];
    $activeTab = $tabMapping[$_GET['tab']] ?? 'materiel';
}

$isFormOpen = $editMode || $transferMode || $showFormParam;

$ste_filter = isset($_GET['ste']) && $_GET['ste'] ? $_GET['ste'] : 'prod';

try {
    // Fetch users for the current environment filter
    $utilisateurs_stmt = $pdo->prepare("SELECT u.*, s.Libelle as ServiceLibelle FROM utilisateur u LEFT JOIN service s ON u.CodeService = s.CodeService WHERE u.STE = ? ORDER BY u.NomPrenom");
    $utilisateurs_stmt->execute([$ste_filter]);
    $utilisateurs = $utilisateurs_stmt->fetchAll();

    // If in edit mode for a material, ensure the correct user list is loaded for that material's STE
    if ($editMode && $editType === 'materiel' && $editMateriel && $editMateriel['STE'] !== $ste_filter) {
        $utilisateurs_stmt->execute([$editMateriel['STE']]);
        $utilisateurs = $utilisateurs_stmt->fetchAll();
    }

    // Load users from the opposite department for transfers
    $other_ste = ($ste_filter === 'prod') ? 'comm' : 'prod';
    $transfer_utilisateurs_stmt = $pdo->prepare("SELECT u.*, s.Libelle as ServiceLibelle FROM utilisateur u LEFT JOIN service s ON u.CodeService = s.CodeService WHERE u.STE = ? ORDER BY u.NomPrenom");
    $transfer_utilisateurs_stmt->execute([$other_ste]);
    $transfer_utilisateurs = $transfer_utilisateurs_stmt->fetchAll();

    $marques = $pdo->query("SELECT * FROM marque ORDER BY Marque")->fetchAll();
    $types = $pdo->query("SELECT * FROM type ORDER BY Libelle")->fetchAll();

    $services_stmt = $pdo->prepare("SELECT * FROM service WHERE STE = ? ORDER BY Libelle");
    $services_stmt->execute([$ste_filter]);
    $services = $services_stmt->fetchAll();

    $materiels_stmt = $pdo->prepare("SELECT m.*, u.NomPrenom, ma.Marque, t.Libelle as TypeLibelle FROM materiel m LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte LEFT JOIN marque ma ON m.CodeMarque = ma.Code LEFT JOIN type t ON m.CodeType = t.CodeType WHERE m.STE = ? ORDER BY m.NumSerie DESC");
    $materiels_stmt->execute([$ste_filter]);
    $materiels = $materiels_stmt->fetchAll();

    $fournisseurs = $pdo->query("SELECT * FROM fournisseur ORDER BY CompanyName, NomComplet")->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur critique: Impossible de charger les données de la base de données. Veuillez contacter un administrateur.";
    $utilisateurs = $marques = $types = $services = $materiels = $fournisseurs = [];
}

$state_map = [
    0 => 'en-service',
    1 => 'en-stock',
    2 => 'endommage',
    3 => 'casse',
    'en-service' => 0,
    'en-stock' => 1,
    'endommage' => 2,
    'casse' => 3
];

$selected_state = isset($_GET['state']) ? $_GET['state'] : 'all';

// Filter materiels to exclude those in inventaire
$materiels = array_filter($materiels, function($m) { return empty($m['inventair']) || $m['inventair'] == 0; });

// Detect inventaire mode from GET
$inventaire_mode = isset($_GET['inventaire_mode']) && $_GET['inventaire_mode'] == '1';

// Apply state filtering only if not in inventory mode or if specific state is selected
if (!$inventaire_mode && $selected_state !== 'all' && in_array($selected_state, ['en-service','en-stock','endommage','casse'])) {
    $materiels = array_filter($materiels, function($m) use ($selected_state, $state_map) {
        $stock = $m['stock'];
        if (is_numeric($stock)) {
            $stock = $state_map[(int)$stock] ?? 'en-stock';
        }
        return $stock === $selected_state;
    });
} elseif ($inventaire_mode && $selected_state !== 'all' && in_array($selected_state, ['en-service','en-stock','endommage','casse'])) {
    // In inventory mode, filter but keep all materials available for inventory
    $materiels = array_filter($materiels, function($m) use ($selected_state, $state_map) {
        $stock = $m['stock'];
        if (is_numeric($stock)) {
            $stock = $state_map[(int)$stock] ?? 'en-stock';
        }
        return $stock === $selected_state;
    });
}

// Handle global history state
$global_history_data = [];
if ($selected_state === 'global-history') {
    try {
        $history_stmt = $pdo->prepare('
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
        $history_stmt->execute();
        $global_history_data = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $global_history_data = [];
        error_log("Erreur lors du chargement de l'historique global: " . $e->getMessage());
    }
}

$inventaire_materiels = [];
if ($activeTab === 'inventaire') {
    try {
        // Now fetching from the dedicated 'inventaire' table
        $inventaire_stmt = $pdo->prepare(
            "SELECT i.*, u.NomPrenom, ma.Marque, t.Libelle as TypeLibelle 
             FROM inventaire i 
             LEFT JOIN utilisateur u ON i.CodeUtilisateur = u.Compte 
             LEFT JOIN marque ma ON i.CodeMarque = ma.Code 
             LEFT JOIN type t ON i.CodeType = t.CodeType 
             WHERE i.STE = ? 
             ORDER BY i.dateinvent DESC"
        );
        $inventaire_stmt->execute([$ste_filter]);
        $inventaire_materiels = $inventaire_stmt->fetchAll();
    } catch (PDOException $e) {
        $inventaire_materiels = [];
        // Silently log error as we cannot show it to the user without a proper setup
        error_log("Erreur lors du chargement du matériel en inventaire: " . $e->getMessage());
    }
}

// Handle recuperer_inventaire POST action
if ($_POST && ($_POST['action'] ?? '') === 'recuperer_inventaire') {
    $numSerie = $_POST['NumSerie'] ?? '';
    if ($numSerie !== '') {
        $stmt = $pdo->prepare('UPDATE materiel SET inventair = 0, dateinvent = NULL WHERE NumSerie = ?');
        $stmt->execute([$numSerie]);
        $success_message = "Le matériel a été récupéré dans la liste principale.";
    }
    header('Location: index.php?tab=materiel&ste=' . urlencode($ste_filter) . '&success=1');
    exit;
}

require_once 'php/initialize_db.php';

// Determine the default state for the add form
$default_state = ($selected_state !== 'all' && in_array($selected_state, ['en-service','en-stock','endommage','casse']))
    ? $selected_state
    : 'en-service';

// Count materiels for display summary
$materiel_count = isset($materiels) ? count($materiels) : 0;

if($_POST && $_POST['action'] === 'recuperer_reparation'){
    $date_recuperated = date('Y-m-d H:i:s');
    $numserie = $_POST['NumSerie'] ?? '';
    if($numserie !== ''){
        // 1. Update repair history
        $select = $pdo->prepare("SELECT id FROM materiel_repair_history WHERE NumSerie = ? AND date_recuperated IS NULL ORDER BY date_sent DESC LIMIT 1");
        $select->execute([$numserie]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            $update = $pdo->prepare("UPDATE materiel_repair_history SET date_recuperated = ? WHERE id = ?");
            $update->execute([$date_recuperated, $row['id']]);
        }

        // 2. Move from materiel_en_reparation back to materiel
        $selectMat = $pdo->prepare("SELECT * FROM materiel_en_reparation WHERE NumSerie = ?");
        $selectMat->execute([$numserie]);
        $mat = $selectMat->fetch(PDO::FETCH_ASSOC);
        if ($mat) {
            // Insert into materiel (adjust columns as needed)
            $fields = [
                'NumSerie', 'CodeMarque', 'CodeType', 'Model', 'CodeUtilisateur', 'Dateentree',
                'stock', 'observation', 'Processeur', 'memoire', 'disqdur', 'graphique',
                'pouce', 'ecran', 'mhtz', 'mo', 'ip', 'classification', 'STE', 'CodeFournisseur', 'damage_cause'
            ];
            $insert_fields = implode(", ", $fields);
            $insert_placeholders = ":" . implode(", :", $fields);
            $insert = $pdo->prepare("INSERT INTO materiel ($insert_fields) VALUES ($insert_placeholders)");
            $params = [];
            foreach ($fields as $f) {
                $params[$f] = $mat[$f] ?? null;
            }
            $insert->execute($params);

            // Delete from materiel_en_reparation
            $del = $pdo->prepare("DELETE FROM materiel_en_reparation WHERE NumSerie = ?");
            $del->execute([$numserie]);
        }

        $success_message = "Le matériel a été récupéré dans la liste principale.";
    }
    header('Location: index.php?tab=maintenance&ste=' . urlencode($ste_filter));
    exit;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Matériel</title>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/materiel_state.css">
    <link rel="stylesheet" href="css/export_styles.css">
    <style>
        .state-filters {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 10px;
        }
        .state-filters a {
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            border: 1px solid transparent;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
            position: relative;
            bottom: -1px;
            background-color: #f1f1f1;
            font-weight: normal;
        }
        .state-filters a.active {
            font-weight: bold;
            background-color: #fff;
            border-color: #ccc #ccc transparent #ccc;
            color: black;
        }
        .state-filters a:hover {
            background-color: #e9e9e9;
        }
        /* Remove underline from Exporter en PDF link */
        a.btn-export-pdf {
            text-decoration: none !important;
        }
        /* Excel button color for prod/comm */
        .btn-excel-prod {
            background-color: #28a745 !important; 
            color: #fff !important;
        }
        .btn-excel-comm {
            background-color: #007bff !important; 
            color: #fff !important;
        }
        .maintenance-tabs {
            margin-bottom: 10px;
        }
        .maintenance-tab-btn {
            background: linear-gradient(90deg, #6a82fb 0%, #fc5c7d 100%);
            color: #fff;
            border: none;
            border-radius: 6px 6px 0 0;
            padding: 10px 22px;
            margin-right: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            outline: none;
            box-shadow: 0 2px 6px rgba(100,100,100,0.08);
        }
        .maintenance-tab-btn.active, .maintenance-tab-btn:focus {
            background: linear-gradient(90deg, #fc5c7d 0%, #6a82fb 100%);
            color: #fff;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(100,100,100,0.12);
        }
        .maintenance-tab-btn:hover {
            background: linear-gradient(90deg, #6a82fb 0%, #fc5c7d 100%);
            color: #fff;
            opacity: 0.92;
        }
        .user-profile-icon {
            position: fixed;
            top: 24px;
            right: 32px;
            width: 44px;
            height: 44px;
            background: #e0e7ef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            cursor: pointer;
            z-index: 1000;
            transition: background 0.18s;
        }
        .user-profile-icon:hover {
            background: #c7d2e5;
        }
        .user-profile-icon img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="icon" type="image/jpeg" href="imgs/Logo_AAF.JPG">
</head>
<body class="theme-<?= htmlspecialchars($ste_filter) ?>">
    <div class="container">
        <header>
            <div class="header-content">
                <div class="logo-section">
                    <img src="imgs/Logo_AAF.JPG" alt="AAF Logo" class="header-logo">
                </div>
                <div class="title-section">
                    <h1>Système de Gestion de Matériel</h1>
                    <p class="header-subtitle">Administration et Suivi des Équipements</p>
                </div>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <strong>Succès!</strong> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <strong>Erreur!</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="switch-button">   

            <label class="theme-switch-label">
                <input type="checkbox" id="theme-toggle" <?= ($ste_filter === 'comm') ? 'checked' : '' ?>>
                <span class="slider">
                    <span class="slider-text text-prod">PROD</span>
                    <span class="slider-text text-comm">COMM</span>
                </span>
            </label>
        </div>

        <nav class="tabs">
            <button class="tab-btn <?= ($activeTab === 'materiel') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'materiel') ? 'disabled' : '' ?>>
                Matériel
            </button>
            <button class="tab-btn <?= ($activeTab === 'inventaire') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=inventaire&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'inventaire') ? 'disabled' : '' ?>>
                Inventaire
            </button>
            <button class="tab-btn <?= ($activeTab === 'utilisateurs') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=utilisateur&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'utilisateurs') ? 'disabled' : '' ?>>
                Utilisateurs
            </button>
            <button class="tab-btn <?= ($activeTab === 'marques') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=marque&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'marques') ? 'disabled' : '' ?>>
                Marques
            </button>
            <button class="tab-btn <?= ($activeTab === 'types') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=type&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'types') ? 'disabled' : '' ?>>
                Types
            </button>
            <button class="tab-btn <?= ($activeTab === 'services') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=service&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'services') ? 'disabled' : '' ?>>
                Services
            </button>
            <button class="tab-btn <?= ($activeTab === 'fournisseurs') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=fournisseurs&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'fournisseurs') ? 'disabled' : '' ?>>
                Fournisseurs
            </button>
            <button class="tab-btn <?= ($activeTab === 'maintenance') ? 'active' : '' ?>" 
                    onclick="window.location.href='index.php?tab=maintenance&ste=<?= urlencode($ste_filter) ?>'" 
                    <?= ($isFormOpen && $activeTab !== 'maintenance') ? 'disabled' : '' ?>>
                Maintenance
            </button>
        </nav>

        <?php if ($activeTab === 'materiel'): ?>
        <div class="state-filters">
            <?php if ($inventaire_mode): ?>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=all" class="<?= $selected_state === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=en-service" class="<?= $selected_state === 'en-service' ? 'active' : '' ?>">En service</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=en-stock" class="<?= $selected_state === 'en-stock' ? 'active' : '' ?>">En stock</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=endommage" class="<?= $selected_state === 'endommage' ? 'active' : '' ?>">Endommagé</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=casse" class="<?= $selected_state === 'casse' ? 'active' : '' ?>">Casse</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=global-history" class="<?= ($selected_state === 'global-history') ? 'active' : '' ?>">Global History</a>
            <?php else: ?>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=all" class="<?= $selected_state === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=en-service" class="<?= $selected_state === 'en-service' ? 'active' : '' ?>">En service</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=en-stock" class="<?= $selected_state === 'en-stock' ? 'active' : '' ?>">En stock</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=endommage" class="<?= $selected_state === 'endommage' ? 'active' : '' ?>">Endommagé</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=casse" class="<?= $selected_state === 'casse' ? 'active' : '' ?>">Casse</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=global-history" class="<?= ($selected_state === 'global-history') ? 'active' : '' ?>">Global History</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Matériel Tab -->
        <div id="materiel" class="mat-section tab-content <?= ($activeTab === 'materiel') ? 'active' : '' ?>">
            
            <!-- Add Materiel Form -->
            <div class="section materiel-form <?= ($editMode && $editType === 'materiel') || $showFormParam === 'materiel' ? '' : 'hide' ?>">
                <div class="form-header form-annuler">
                    <h2><?= $editMode && $editType === 'materiel' ? 'Modifier le Matériel' : 'Ajouter un Matériel' ?></h2>
                    <?php if (!$editMode): ?>
                        <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-close btn-cancel">Annuler</a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="form-grid" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" value="<?= $editMode && $editType === 'materiel' ? 'modify_materiel' : 'add_materiel' ?>">
                    <input type="hidden" name="STE" value="<?= $editMode ? htmlspecialchars($editMateriel['STE']) : (isset($previousMaterielData['STE']) ? htmlspecialchars($previousMaterielData['STE']) : $ste_filter) ?>">
                    <input type="hidden" name="datefinservice" id="datefinservice-input" value="<?= $editMode ? htmlspecialchars($editMateriel['datefinservice'] ?? '') : (isset($previousMaterielData['datefinservice']) ? htmlspecialchars($previousMaterielData['datefinservice']) : '') ?>">

                    <!-- Identification Section -->
                    <h3 class="form-section-title">Identification</h3>
                    <div class="section-divider"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Numéro de Série:<span style="color:red">*</span></label>
                            <input type="text" name="NumSerie" value="<?= $editMode ? htmlspecialchars($editMateriel['NumSerie']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?> pattern="[^\s].*" title="Le numéro de série ne peut pas être vide ou contenir uniquement des espaces" <?= ($showFormParam === 'ajouter_plusieurs') ? 'autofocus' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label>Type:<span style="color:red">*</span></label>
                            <select name="CodeType" required>
                                <option value="">Sélectionner un type</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= $type['CodeType'] ?>" 
                                        <?= $editMode && $type['CodeType'] == $editMateriel['CodeType'] ? 'selected' : 
                                            (isset($previousMaterielData['CodeType']) && $type['CodeType'] == $previousMaterielData['CodeType'] ? 'selected' : '') ?>
                                    ><?= $type['Libelle'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Marque:<span style="color:red">*</span></label>
                            <select name="CodeMarque" required>
                                <option value="">Sélectionner une marque</option>
                                <?php foreach ($marques as $marque): ?>
                                    <option value="<?= $marque['Code'] ?>" 
                                        <?= $editMode && $marque['Code'] == $editMateriel['CodeMarque'] ? 'selected' : 
                                            (isset($previousMaterielData['CodeMarque']) && $marque['Code'] == $previousMaterielData['CodeMarque'] ? 'selected' : '') ?>
                                    ><?= $marque['Marque'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Modèle:<span style="color:red">*</span></label>
                            <input type="text" name="Model" value="<?= $editMode ? htmlspecialchars($editMateriel['Model']) : (isset($previousMaterielData['Model']) ? htmlspecialchars($previousMaterielData['Model']) : '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Utilisateur:<span style="color:red">*</span></label>
                            <select name="CodeUtilisateur" required>
                                <option value="">Sélectionner un utilisateur</option>
                                <?php foreach ($utilisateurs as $user): ?>
                                    <option value="<?= $user['Compte'] ?>" 
                                        <?= $editMode && $user['Compte'] == $editMateriel['CodeUtilisateur'] ? 'selected' : 
                                            (isset($previousMaterielData['CodeUtilisateur']) && $user['Compte'] == $previousMaterielData['CodeUtilisateur'] ? 'selected' : '') ?>
                                    ><?= $user['NomPrenom'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fournisseur:<span style="color:red">*</span></label>
                            <select name="CodeFournisseur" <?= $editMode ? '' : '' ?> required>
                                <option value="">Sélectionner un fournisseur</option>
                                <?php foreach ($fournisseurs as $four):
                                    $isSelected = $editMode && isset($editMateriel['CodeFournisseur']) && $four['Email'] == $editMateriel['CodeFournisseur'];
                                    $isSelectedPrevious = !$editMode && isset($previousMaterielData['CodeFournisseur']) && $four['Email'] == $previousMaterielData['CodeFournisseur'];
                                    $displayName = !empty($four['CompanyName']) ? $four['CompanyName'] : $four['NomComplet'];
                                ?>
                                    <option value="<?= htmlspecialchars($four['Email']) ?>" <?= $isSelected ? 'selected' : ($isSelectedPrevious ? 'selected' : '') ?>><?= htmlspecialchars($displayName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date d'entrée:</label>
                            <input type="date" name="Dateentree" value="<?= $editMode ? htmlspecialchars($editMateriel['Dateentree']) : (isset($previousMaterielData['Dateentree']) ? htmlspecialchars($previousMaterielData['Dateentree']) : '') ?>">
                        </div>
                    </div>

                    <!-- Caractéristiques PC Section -->
                    <h3 class="form-section-title">Caractéristiques PC</h3>
                    <div class="section-divider"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Processeur:</label>
                            <input type="text" name="Processeur" value="<?= $editMode ? htmlspecialchars($editMateriel['Processeur']) : (isset($previousMaterielData['Processeur']) ? htmlspecialchars($previousMaterielData['Processeur']) : '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Carte Graphique:</label>
                            <input type="text" name="graphique" value="<?= $editMode ? htmlspecialchars($editMateriel['graphique']) : (isset($previousMaterielData['graphique']) ? htmlspecialchars($previousMaterielData['graphique']) : '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Disque Dur:</label>
                            <input type="text" name="disqdur" value="<?= $editMode ? htmlspecialchars($editMateriel['disqdur']) : (isset($previousMaterielData['disqdur']) ? htmlspecialchars($previousMaterielData['disqdur']) : '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fréquence (MHz):</label>
                            <input type="text" name="mhtz" value="<?= $editMode ? htmlspecialchars($editMateriel['mhtz']) : (isset($previousMaterielData['mhtz']) ? htmlspecialchars($previousMaterielData['mhtz']) : '') ?>">
                        </div>
                        <div class="form-group">
                            <label>MO:</label>
                            <input type="text" name="mo" value="<?= $editMode ? htmlspecialchars($editMateriel['mo']) : (isset($previousMaterielData['mo']) ? htmlspecialchars($previousMaterielData['mo']) : '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Mémoire:</label>
                            <input type="text" name="memoire" value="<?= $editMode ? htmlspecialchars($editMateriel['memoire']) : (isset($previousMaterielData['memoire']) ? htmlspecialchars($previousMaterielData['memoire']) : '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                    <div class="form-group">
                        <label>Adresse IP:</label>
                        <input type="text" name="ip" value="<?= $editMode ? htmlspecialchars($editMateriel['ip']) : (isset($previousMaterielData['ip']) ? htmlspecialchars($previousMaterielData['ip']) : '') ?>">
                    </div>
                    </div>

                    <h3 class="form-section-title">Caractéristiques Ecran</h3>
                    <div class="section-divider"></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Écran:</label>
                            <input type="text" name="ecran" value="<?= $editMode ? htmlspecialchars($editMateriel['ecran']) : (isset($previousMaterielData['ecran']) ? htmlspecialchars($previousMaterielData['ecran']) : '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Pouces:</label>
                            <input type="text" name="pouce" value="<?= $editMode ? htmlspecialchars($editMateriel['pouce']) : (isset($previousMaterielData['pouce']) ? htmlspecialchars($previousMaterielData['pouce']) : '') ?>">
                        </div>
                    </div>

                    <!-- Others Section -->
                    <h3 class="form-section-title">Autres</h3>
                    <div class="section-divider"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Classification:</label>
                             <select name="classification" id="materiel-classification-select" onchange="toggleDamageCause(this.value)">
                                <option value="interne" <?= ($editMode && $editMateriel['stock'] === 'interne') || 
                                                          (!$editMode && isset($previousMaterielData['classification']) && $previousMaterielData['classification'] === 'interne') || 
                                                          (!$editMode && !isset($previousMaterielData['classification']) && $default_state === 'interne') ? 'selected' : '' ?>>Interne</option>
                                <option value="confidentiel" <?= ($editMode && $editMateriel['stock'] === 'confidentiel') || 
                                                             (!$editMode && isset($previousMaterielData['classification']) && $previousMaterielData['classification'] === 'confidentiel') || 
                                                             (!$editMode && !isset($previousMaterielData['classification']) && $default_state === 'confidentiel') ? 'selected' : '' ?>>Confidentiel</option>
                                <option value="secret" <?= ($editMode && $editMateriel['stock'] === 'secret') || 
                                                       (!$editMode && isset($previousMaterielData['classification']) && $previousMaterielData['classification'] === 'secret') || 
                                                       (!$editMode && !isset($previousMaterielData['classification']) && $default_state === 'secret') ? 'selected' : '' ?>>Secret</option>
                                <option value="public" <?= ($editMode && $editMateriel['stock'] === 'public') || 
                                                        (!$editMode && isset($previousMaterielData['classification']) && $previousMaterielData['classification'] === 'public') || 
                                                        (!$editMode && !isset($previousMaterielData['classification']) && $default_state === 'public') ? 'selected' : '' ?>>Public</option>
                               
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>État:</label>
                            <select name="stock" id="materiel-state-select" onchange="toggleDamageCause(this.value)">
                                <?php
                                $options = $editMode ? $editStockLabelMap : $addStockLabelMap;
                                foreach ($options as $val => $label):
                                    $stringKey = isset($stockMap[$val]) ? $stockMap[$val] : $val;
                                    $selected = '';
                                    if ($editMode && isset($materiel['stock'])) {
                                        if ($materiel['stock'] == $val || $materiel['stock'] === $stringKey) {
                                            $selected = 'selected';
                                        }
                                    } elseif (!$editMode && isset($previousMaterielData['stock'])) {
                                        if ($previousMaterielData['stock'] == $stringKey) {
                                            $selected = 'selected';
                                        }
                                    }
                                ?>
                                    <option value="<?= $stringKey ?>" <?= $selected ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Observation:</label>
                            <textarea name="observation" rows="3"><?= $editMode ? htmlspecialchars($editMateriel['observation']) : (isset($previousMaterielData['observation']) ? htmlspecialchars($previousMaterielData['observation']) : '') ?></textarea>
                        </div>
                    </div>
                    <div id="damage-cause-group" class="form-group full-width" style="display: <?= ($editMode && ($editMateriel['stock'] === 'endommage' || $editMateriel['stock'] === 'casse')) || (!$editMode && isset($previousMaterielData['stock']) && ($previousMaterielData['stock'] === 'endommage' || $previousMaterielData['stock'] === 'casse')) ? 'block' : 'none' ?>;">
                        <label>Cause du dommage:</label>
                        <textarea name="damage_cause" rows="2"><?= $editMode ? htmlspecialchars($editMateriel['damage_cause'] ?? '') : (isset($previousMaterielData['damage_cause']) ? htmlspecialchars($previousMaterielData['damage_cause']) : '') ?></textarea>
                    </div>
                    <?php if (!$editMode): ?>
                    <div class="form-group">
                        <label for="plusieurs" class="checkbox-label" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <input type="checkbox" id="plusieurs" name="plusieurs" value="1" <?= isset($previousMaterielData) ? 'checked' : '' ?> style="margin-right: 8px;">
                            <span>Plusieurs (cocher pour ajouter plusieurs matériels)</span>
                        </label>
                    </div>
                    <?php endif; ?>
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary">
                            <?php if ($editMode): ?>
                                Modifier le Matériel
                            <?php else: ?>
                                Ajouter le Matériel
                            <?php endif; ?>
                        </button>
                        <?php if ($editMode): ?>
                            <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Transfer Materiel Form -->
            <div class="section materiel-transfer-form <?= ($transferMode && $transferType === 'materiel') ? '' : 'hide' ?>">
            <!-- Display datefinservice in Casse tab -->
            <?php if ($activeTab === 'materiel' && $selected_state === 'casse'): ?>
                <div class="casse-datefinservice-list">
                    <h3>Date de fin de service</h3>
                    <ul>
                    <?php foreach ($materiels as $mat): ?>
                        <?php if ($mat['stock'] == 3 && !empty($mat['datefinservice'])): ?>
                            <li>
                                <strong><?= htmlspecialchars($mat['NumSerie']) ?>:</strong>
                                <?= htmlspecialchars($mat['datefinservice']) ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
                <div class="form-header">
                    <h2>Transférer le Matériel</h2>
                </div>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="transfer_materiel">
                    <input type="hidden" name="NumSerie" value="<?= ($transferMode && $transferType === 'materiel') ? htmlspecialchars($transferMateriel['NumSerie']) : '' ?>">
                    <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                    
                    <div class="form-group">
                        <label>Numéro de Série:</label>
                        <input type="text" value="<?= ($transferMode && $transferType === 'materiel') ? htmlspecialchars($transferMateriel['NumSerie']) : '' ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Type:</label>
                        <input type="text" value="<?= ($transferMode && $transferType === 'materiel') ? htmlspecialchars($transferMateriel['TypeLibelle']) : '' ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Transférer vers:</label>
                        <input type="text" name="target_STE" value="<?= ($ste_filter === 'prod') ? 'COMM' : 'PROD' ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Nouveau Propriétaire:<span style="color:red">*</span></label>
                        <select name="CodeUtilisateur" required>
                            <option value="">Sélectionner un utilisateur</option>
                            <?php foreach ($transfer_utilisateurs as $user): ?>
                                <option value="<?= $user['Compte'] ?>"><?= $user['NomPrenom'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary">Confirmer le Transfert</button>
                        <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                    </div>
                </form>
            </div>

            <!-- Section header with Ajouter button -->
            <div class="section-header">
                <h2>Liste du Matériel</h2>
                <div class="button-group">
                    <?php if (!$inventaire_mode): ?>
                    <button class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?> onclick="window.location.href='index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>&state=<?= urlencode($selected_state) ?>&showForm=materiel'">Ajouter Matériel</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('materiel-table', 'materiel_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                    <a href="export_pdf.php?ste=<?= urlencode($ste_filter) ?>" class="btn btn-primary btn-export-pdf">
                        <i class="fas fa-file-pdf"></i> Exporter en PDF
                    </a>
                    <?php else: ?>
                    <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler l'Inventaire</a>
                    <?php endif; ?>
                </div>
            </div>
</script>

            <!-- Restored search bar -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-materiel"
                            class="search-input"
                            placeholder="Rechercher dans le matériel..."
                            autocomplete="off"
                        />
                        <script>
                        // Update search functionality to handle both materiel and global history tables
                        document.addEventListener('DOMContentLoaded', function() {
                            const searchInput = document.getElementById('search-materiel');
                            if (searchInput) {
                                searchInput.addEventListener('input', function() {
                                    const searchTerm = this.value.toLowerCase();
                                    const isGlobalHistory = window.location.search.includes('state=global-history');
                                    
                                    if (isGlobalHistory) {
                                        // Search in global history table
                                        const rows = document.querySelectorAll('#global-history-table tbody tr');
                                        rows.forEach(row => {
                                            const text = row.textContent.toLowerCase();
                                            row.style.display = text.includes(searchTerm) ? '' : 'none';
                                        });
                                    } else {
                                        // Existing search logic for materiel table
                                        if (typeof performSearch === 'function') {
                                            performSearch('materiel');
                                        }
                                    }
                                });
                            }
                        });
                        </script>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="NumSerie"> N° Série
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="TypeLibelle"> Type
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Marque"> Marque
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Model"> Modèle
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="NomPrenom"> Utilisateur
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Dateentree"> Date Entrée
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="classification"> Classification
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="observation"> Observation
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="État"> État
                        </label>
                    </div>
                </div>
            </div>
            <!-- End search bar -->

            <!-- Display count of materiels -->
            <div id="materiel-count-summary" class="materiel-count-summary" style="margin: 10px 0 10px 0; font-weight: bold; color: #333;">
                <?php if ($selected_state === 'global-history'): ?>
                    Nombre d'historiques affichés : <?= count($global_history_data) ?>
                <?php else: ?>
                    Nombre de matériels affichés : <?= $materiel_count ?>
                <?php endif; ?>
            </div>
            <form method="POST" id="fin-inventaire-form">
                <input type="hidden" name="action" value="fin_inventaire">
                <input type="hidden" name="ste" value="<?= htmlspecialchars($ste_filter) ?>">
                <input type="hidden" name="state" value="<?= htmlspecialchars($selected_state) ?>">
                <div class="table-container">
                    <?php if ($selected_state === 'global-history'): ?>
                        <!-- Global History Table -->
                        <table id="global-history-table" class="table-materiel">
                            <thead>
                                <tr>
                                    <th>N° Série</th>
                                    <th>Type</th>
                                    <th>Modèle</th>
                                    <th>Date</th>
                                    <th>Ancien Utilisateur</th>
                                    <th>Nouveau Utilisateur</th>
                                    <th>Ancienne Etat</th>
                                    <th>Nouvelle Etat</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($global_history_data)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">Aucun historique disponible.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($global_history_data as $history): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($history['numserie'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['TypeLibelle'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['Model'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['date_change'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['previous_username'] ?? $history['previous_owner'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['new_username'] ?? $history['new_owner'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['prev_state'] ?? $history['prev_state'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['new_state'] ?? $history['new_state'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($history['notes'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <!-- Regular Materiel Table -->
                        <table id="materiel-table" class="table-materiel">
                            <thead>
                                <tr>
                                    <?php if ($inventaire_mode): ?>
                                    <th>Présent</th>
                                    <?php endif; ?>
                                    <th>Numéro de Série</th>
                                    <th>Type</th>
                                    <th>Marque</th>
                                    <th>Modèle</th>
                                    <th>Utilisateur</th>
                                    <th>Date Entrée</th>
                                    <th>Classification</th>
                                    <th>État</th>
                                    <?php if ($selected_state === 'casse'): ?>
                                        <th>Date de fin de service</th>
                                    <?php else: ?>
                                        <th>Observation</th>
                                    <?php endif; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>        
                            <tbody>
                                <?php foreach ($materiels as $materiel): ?>
                                <tr>
                                    <?php if ($inventaire_mode): ?>
                                    <td><input type="checkbox" name="present[]" value="<?= $materiel['NumSerie'] ?>"></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($materiel['NumSerie']) ?></td>
                                    <td><?= htmlspecialchars($materiel['TypeLibelle'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Marque'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Model'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['NomPrenom'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Dateentree'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['classification'] ?? 'N/A') ?></td>
                                    <td class="materiel-state">
                                        <?php if (!$inventaire_mode): ?>
                                        <form method="POST" style="display:inline; margin:0;" onsubmit="return handleInlineStateChange(this)">
                                            <input type="hidden" name="action" value="change_state">
                                            <input type="hidden" name="NumSerie" value="<?= $materiel['NumSerie'] ?>">
                                            <input type="hidden" name="STE" value="<?= htmlspecialchars($ste_filter) ?>">
                                            <input type="hidden" name="redirect_state" value="<?= htmlspecialchars($selected_state) ?>">
                                            <input type="hidden" name="datefinservice" value="" class="datefinservice-inline">
                                            <select name="stock" onchange="handleInlineStateSelect(this)">
                                                <?php foreach ($stockLabelMap as $val => $label): ?>
                                                <option value="<?= $val ?>" <?= (isset($materiel['stock']) && $materiel['stock'] == $val) ? 'selected' : '' ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <script>
                                        function handleInlineStateSelect(select) {
                                            var form = select.form;
                                            var dateInput = form.querySelector('.datefinservice-inline');
                                            if (select.value == '3') {
                                                var now = new Date();
                                                var formatted = now.getFullYear() + '-' +
                                                    String(now.getMonth()+1).padStart(2,'0') + '-' +
                                                    String(now.getDate()).padStart(2,'0') + ' ' +
                                                    String(now.getHours()).padStart(2,'0') + ':' +
                                                    String(now.getMinutes()).padStart(2,'0') + ':' +
                                                    String(now.getSeconds()).padStart(2,'0');
                                                dateInput.value = formatted;
                                            } else {
                                                dateInput.value = '';
                                            }
                                            form.submit();
                                        }
                                        function handleInlineStateChange(form) {
                                            // Always allow submit
                                            return true;
                                        }
                                        </script>
                                        <?php else: ?>
                                            <?php 
                                                $stockVal = $materiel['stock'] ?? 0;
                                                if (is_numeric($stockVal)) {
                                                    $stateLabel = $stockLabelMap[$stockVal] ?? '';
                                                } else {
                                                    $stateLabel = $stockLabelMap[$stateToStock[$stockVal] ?? 0] ?? '';
                                                }
                                                $stateClass = 'state-' . ($stockVal ?? 'en-service');
                                            ?>
                                            <span class="<?= $stateClass ?>" style="margin-left:8px;"> <?= $stateLabel ?> </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (($selected_state === 'casse') || (isset($materiel['stock']) && ($materiel['stock'] == 3 || $materiel['stock'] === 'casse'))): ?>
                                        <td><?= ($materiel['datefinservice'] && $materiel['datefinservice'] != '0000-00-00 00:00:00') ? $materiel['datefinservice'] : '' ?></td>
                                    <?php else: ?>
                                        <td><?= $materiel['observation'] ?? 'N/A' ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!$inventaire_mode): ?>
                                        <div class="action-buttons">
                                            <a href="index.php?edit=<?= $materiel['NumSerie'] ?>&type=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>
                                                <img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce matériel ?');">
                                                <input type="hidden" name="action" value="delete_materiel">
                                                <input type="hidden" name="NumSerie" value="<?= $materiel['NumSerie'] ?>">
                                                <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                                <button type="submit" class="btn-delete" title="Supprimer" <?= !$is_admin ? 'disabled' : '' ?>>
                                                    <img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/>
                                                </button>
                                            </form>
                                            <a href="index.php?transfer=<?= $materiel['NumSerie'] ?>&type=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-transfer" title="Transférer" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>><img width="20px" height="20px" src="imgs/transfer.png" alt="transférer"/></a>
                                            <a href="get_material_history.php?numserie=<?= $materiel['NumSerie'] ?>&ste=<?= urlencode($ste_filter) ?>" class="btn-history" title="Historique"><img width="20px" height="20px" src="imgs/history.png" alt="historique"/></a>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php if ($inventaire_mode): ?>
                <div class="form-group full-width" style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn-primary">Finaliser l'Inventaire</button>
                    <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                </div>
                <?php endif; ?>
            </form>
        </div>


        <!-- Inventaire Tab -->
        <div id="inventaire" class="tab-content <?= ($activeTab === 'inventaire') ? 'active' : '' ?>">
            <div class="section-header">
                <h2>Matériel en Inventaire</h2>
                <div class="button-group">
                    <?php if (!$inventaire_mode): ?>
                    <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>&inventaire_mode=1" class="btn btn-primary btn-export-pdf" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>Début Inventaire</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table id="inventaire-table" class="table-materiel">
                    <thead>
                        <tr>
                            <th>Numéro de Série</th>
                            <th>Marque</th>
                            <th>Type</th>
                            <th>Modèle</th>
                            <th>Date de mise en inventaire</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventaire_materiels)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Aucun matériel en cours d'inventaire.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventaire_materiels as $materiel): ?>
                                <tr>
                                    <td><?= htmlspecialchars($materiel['NumSerie']) ?></td>
                                    <td><?= htmlspecialchars($materiel['Marque'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['TypeLibelle'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Model'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($materiel['dateinvent']))) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="recuperer_inventaire">
                                            <input type="hidden" name="NumSerie" value="<?= $materiel['NumSerie'] ?>">
                                            <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                            <button type="submit" class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?>>Récupérer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Utilisateurs Tab -->
        <div id="utilisateurs" class="tab-content <?= ($activeTab === 'utilisateurs') ? 'active' : '' ?>">
            
            <!-- Add/Modify Utilisateur Form -->
            <div class="section utilisateur-form <?= ($editMode && $editType === 'utilisateur') || $showFormParam === 'utilisateur' ? '' : 'hide' ?>">
                <div class="form-header form-annuler">
                    <h2><?= $editMode && $editType === 'utilisateur' ? 'Modifier l\'Utilisateur' : 'Ajouter un Utilisateur' ?></h2>
                    <?php if (!$editMode): ?>
                        <a href="index.php?tab=utilisateur&ste=<?= urlencode($ste_filter) ?>" class="btn-close btn-cancel">Annuler</a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="form-grid" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" value="<?= ($editMode && $editType === 'utilisateur') ? 'modify_utilisateur' : 'add_utilisateur' ?>">
                    <input type="hidden" name="STE" value="<?= $editMode && isset($editUtilisateur['STE']) ? htmlspecialchars($editUtilisateur['STE']) : htmlspecialchars($ste_filter) ?>">

                    <div class="form-group">
                        <label>Compte:<span style="color:red">*</span></label>
                        <input type="text" name="Compte" value="<?= $editMode && isset($editUtilisateur['Compte']) ? htmlspecialchars($editUtilisateur['Compte']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?> >
                    </div>

                    <div class="form-group">
                        <label>Nom et Prénom:<span style="color:red">*</span></label>
                        <input type="text" name="NomPrenom" value="<?= $editMode && isset($editUtilisateur['NomPrenom']) ? htmlspecialchars($editUtilisateur['NomPrenom']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Service:<span style="color:red">*</span></label>
                        <select name="CodeService" required>
                            <option value="">Non spécifié</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['CodeService'] ?>" <?= $editMode && isset($editUtilisateur['CodeService']) && $service['CodeService'] == $editUtilisateur['CodeService'] ? 'selected' : '' ?>><?= $service['Libelle'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="Email" value="<?= $editMode && isset($editUtilisateur['Email']) ? htmlspecialchars($editUtilisateur['Email']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone:</label>
                        <input type="text" name="Tel" value="<?= $editMode ? htmlspecialchars($editUtilisateur['Tel']) : '' ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary"><?= $editMode ? 'Modifier' : 'Ajouter' ?></button>
                        <?php if ($editMode): ?>
                            <a href="index.php?tab=utilisateur&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Section header with Ajouter button -->
            <div class="section-header">
                <h2>Liste des Utilisateurs</h2>
                <div class="button-group">
                    <button class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?> onclick="window.location.href='index.php?tab=utilisateur&ste=<?= urlencode($ste_filter) ?>&showForm=utilisateur'">Ajouter Utilisateur</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('utilisateurs-table', 'utilisateurs_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                </div>
            </div>
            <!-- Search bar for Utilisateurs -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-utilisateurs"
                            class="search-input"
                            placeholder="Rechercher dans les utilisateurs..."
                            autocomplete="off"
                        />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Compte"> Compte
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="NomPrenom"> Nom et Prénom
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="ServiceLibelle"> Service
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Email"> Email
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Tel"> Téléphone
                        </label>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table id="utilisateurs-table" class="table-materiel">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Nom et Prénom</th>
                            <th>Service</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $utilisateur): ?>
                        <tr>
                            <td><?= htmlspecialchars($utilisateur['Compte']) ?></td>
                            <td><?= htmlspecialchars($utilisateur['NomPrenom']) ?></td>
                            <td><?= htmlspecialchars($utilisateur['ServiceLibelle'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($utilisateur['Email'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($utilisateur['Tel'] ?? 'N/A') ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?edit=<?= $utilisateur['Compte'] ?>&type=utilisateur&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>
                                        <img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                        <input type="hidden" name="action" value="delete_utilisateur">
                                        <input type="hidden" name="Compte" value="<?= $utilisateur['Compte'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer" <?= !$is_admin ? 'disabled' : '' ?>>
                                            <img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Marques Tab -->
        <div id="marques" class="tab-content <?= ($activeTab === 'marques') ? 'active' : '' ?>">
            
            <!-- Add/Modify Marque Form -->
            <div class="section marque-form <?= ($editMode && $editType === 'marque') || $showFormParam === 'marque' ? '' : 'hide' ?>">
                <div class="form-header form-annuler">
                    <h2><?= $editMode && $editType === 'marque' ? 'Modifier la Marque' : 'Ajouter une Marque' ?></h2>
                    <?php if (!$editMode): ?>
                        <a href="index.php?tab=marque&ste=<?= urlencode($ste_filter) ?>" class="btn-close btn-cancel">Annuler</a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="form-grid" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" value="<?= $editMode && $editType === 'marque' ? 'modify_marque' : 'add_marque' ?>">
                    <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="Code" value="<?= htmlspecialchars($editMarque['Code']) ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Marque:<span style="color:red">*</span></label>
                        <input type="text" name="Marque" value="<?= $editMode ? htmlspecialchars($editMarque['Marque']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary"><?= $editMode ? 'Modifier' : 'Ajouter' ?></button>
                        <?php if ($editMode): ?>
                            <a href="index.php?tab=marque&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Section header with Ajouter button -->
            <div class="section-header">
                <h2>Liste des Marques</h2>
                <div class="button-group">
                    <button class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?> onclick="window.location.href='index.php?tab=marque&showForm=marque'">Ajouter Marque</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('marques-table', 'marques_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                </div>
            </div>
            <!-- Search bar for Marques -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-marques"
                            class="search-input"
                            placeholder="Rechercher dans les marques..."
                            autocomplete="off"
                        />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Code"> Code
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Marque"> Marque
                        </label>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table id="marques-table" class="table-materiel">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Marque</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marques as $marque): ?>
                        <tr>
                            <td><?= htmlspecialchars($marque['Code']) ?></td>
                            <td><?= htmlspecialchars($marque['Marque']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?edit=<?= $marque['Code'] ?>&type=marque&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>
                                        <img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette marque ?');">
                                        <input type="hidden" name="action" value="delete_marque">
                                        <input type="hidden" name="Code" value="<?= $marque['Code'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer" <?= !$is_admin ? 'disabled' : '' ?>>
                                            <img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Types Tab -->
        <div id="types" class="tab-content <?= ($activeTab === 'types') ? 'active' : '' ?>">
            
            <!-- Add/Modify Type Form -->
            <div class="section type-form <?= ($editMode && $editType === 'type') || $showFormParam === 'type' ? '' : 'hide' ?>">
                <div class="form-header form-annuler">
                    <h2><?= $editMode && $editType === 'type' ? 'Modifier le Type' : 'Ajouter un Type' ?></h2>
                    <?php if (!$editMode): ?>
                        <a href="index.php?tab=type&ste=<?= urlencode($ste_filter) ?>" class="btn-close btn-cancel">Annuler</a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="form-grid" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" value="<?= $editMode && $editType === 'type' ? 'modify_type' : 'add_type' ?>">
                    <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="CodeType" value="<?= htmlspecialchars($editTypeEntity['CodeType']) ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Libellé:<span style="color:red">*</span></label>
                        <input type="text" name="Libelle" value="<?= $editMode ? htmlspecialchars($editTypeEntity['Libelle']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary"><?= $editMode ? 'Modifier' : 'Ajouter' ?></button>
                        <?php if ($editMode): ?>
                            <a href="index.php?tab=type&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Section header with Ajouter button -->
            <div class="section-header">
                <h2>Liste des Types</h2>
                <div class="button-group">
                    <button class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?> onclick="window.location.href='index.php?tab=type&showForm=type'">Ajouter Type</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('types-table', 'types_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                </div>
            </div>
            <!-- Search bar for Types -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-types"
                            class="search-input"
                            placeholder="Rechercher dans les types..."
                            autocomplete="off"
                        />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="CodeType"> Code
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Libelle"> Libellé
                        </label>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table id="types-table" class="table-materiel">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $type): ?>
                        <tr>
                            <td><?= htmlspecialchars($type['CodeType']) ?></td>
                            <td><?= htmlspecialchars($type['Libelle']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?edit=<?= $type['CodeType'] ?>&type=type&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>
                                        <img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce type ?');">
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="CodeType" value="<?= $type['CodeType'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer" <?= !$is_admin ? 'disabled' : '' ?>>
                                            <img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Services Tab -->
        <div id="services" class="tab-content <?= ($activeTab === 'services') ? 'active' : '' ?>">
            
            <!-- Add/Modify Service Form -->
            <div class="section service-form <?= ($editMode && $editType === 'service') || $showFormParam === 'service' ? '' : 'hide' ?>">
                <div class="form-header form-annuler">
                    <h2><?= $editMode && $editType === 'service' ? 'Modifier le Service' : 'Ajouter un Service' ?></h2>
                    <?php if (!$editMode): ?>
                        <a href="index.php?tab=service&ste=<?= urlencode($ste_filter) ?>" class="btn-close btn-cancel">Annuler</a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="form-grid" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" value="<?= $editMode && $editType === 'service' ? 'modify_service' : 'add_service' ?>">
                    <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="CodeService" value="<?= htmlspecialchars($editService['CodeService']) ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Libellé:<span style="color:red">*</span></label>
                        <input type="text" name="Libelle" value="<?= $editMode ? htmlspecialchars($editService['Libelle']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary"><?= $editMode ? 'Modifier' : 'Ajouter' ?></button>
                        <?php if ($editMode): ?>
                            <a href="index.php?tab=service&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Section header with Ajouter button -->
            <div class="section-header">
                <h2>Liste des Services</h2>
                <div class="button-group">
                    <button class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?> onclick="window.location.href='index.php?tab=service&ste=<?= urlencode($ste_filter) ?>&showForm=service'">Ajouter Service</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('services-table', 'services_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                </div>
            </div>
            <!-- Search bar for Services -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-services"
                            class="search-input"
                            placeholder="Rechercher dans les services..."
                            autocomplete="off"
                        />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="CodeService"> Code
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Libelle"> Libellé
                        </label>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table id="services-table" class="table-materiel">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?= htmlspecialchars($service['CodeService']) ?></td>
                            <td><?= htmlspecialchars($service['Libelle']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?edit=<?= $service['CodeService'] ?>&type=service&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>
                                        <img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="CodeService" value="<?= $service['CodeService'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer" <?= !$is_admin ? 'disabled' : '' ?>>
                                            <img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fournisseurs Tab -->
        <div id="fournisseurs" class="tab-content <?= ($activeTab === 'fournisseurs') ? 'active' : '' ?>">
            
            <!-- Add/Modify Fournisseur Form -->
            <div class="section fournisseur-form <?= ($editMode && $editType === 'fournisseur') || $showFormParam === 'fournisseur' ? '' : 'hide' ?>">
                <div class="form-header form-annuler">
                    <h2><?= $editMode && $editType === 'fournisseur' ? 'Modifier le Fournisseur' : 'Ajouter un Fournisseur' ?></h2>
                    <?php if (!$editMode): ?>
                        <a href="index.php?tab=fournisseurs&ste=<?= urlencode($ste_filter) ?>" class="btn-close btn-cancel">Annuler</a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="form-grid" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" value="<?= $editMode && $editType === 'fournisseur' ? 'modify_fournisseur' : 'add_fournisseur' ?>">
                    <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                    
                    <div class="form-group">
                        <label>Email:<span style="color:red">*</span></label>
                        <input type="email" name="Email" value="<?= $editMode ? htmlspecialchars($editFournisseur['Email']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>Nom de la société:</label>
                        <input type="text" name="CompanyName" value="<?= $editMode ? htmlspecialchars($editFournisseur['CompanyName']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nom Complet:</label>
                        <input type="text" name="NomComplet" value="<?= $editMode ? htmlspecialchars($editFournisseur['NomComplet']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Adresse:</label>
                        <input type="text" name="Adress" value="<?= $editMode ? htmlspecialchars($editFournisseur['Adress']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone Fixe:</label>
                        <input type="text" name="TelFix" value="<?= $editMode ? htmlspecialchars($editFournisseur['TelFix']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone Mobile:</label>
                        <input type="text" name="TelMobile" value="<?= $editMode ? htmlspecialchars($editFournisseur['TelMobile']) : '' ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary"><?= $editMode ? 'Modifier' : 'Ajouter' ?></button>
                        <?php if ($editMode): ?>
                            <a href="index.php?tab=fournisseurs&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Section header with Ajouter button -->
            <div class="section-header">
                <h2>Liste des Fournisseurs</h2>
                <div class="button-group">
                    <button class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?> onclick="window.location.href='index.php?tab=fournisseurs&showForm=fournisseur'">Ajouter Fournisseur</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('fournisseurs-table', 'fournisseurs_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                </div>
            </div>
            <!-- Search bar for Fournisseurs -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-fournisseurs"
                            class="search-input"
                            placeholder="Rechercher dans les fournisseurs..."
                            autocomplete="off"
                        />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Email"> Email
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="CompanyName"> Société
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="NomComplet"> Nom Complet
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Adress"> Adresse
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="TelFix"> Tel Fixe
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="TelMobile"> Tel Mobile
                        </label>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <table id="fournisseurs-table" class="table-materiel">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Société</th>
                            <th>Nom Complet</th>
                            <th>Adresse</th>
                            <th>Tel Fixe</th>
                            <th>Tel Mobile</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fournisseurs as $fournisseur): ?>
                        <tr>
                            <td><?= htmlspecialchars($fournisseur['Email']) ?></td>
                            <td><?= htmlspecialchars($fournisseur['CompanyName']) ?></td>
                            <td><?= htmlspecialchars($fournisseur['NomComplet']) ?></td>
                            <td><?= htmlspecialchars($fournisseur['Adress']) ?></td>
                            <td><?= htmlspecialchars($fournisseur['TelFix']) ?></td>
                            <td><?= htmlspecialchars($fournisseur['TelMobile']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?edit=<?= urlencode($fournisseur['Email']) ?>&type=fournisseur&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>
                                        <img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/>
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">
                                        <input type="hidden" name="action" value="delete_fournisseur">
                                        <input type="hidden" name="Email" value="<?= $fournisseur['Email'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer" <?= !$is_admin ? 'disabled' : '' ?>>
                                            <img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="maintenance" class="tab-content <?= ($activeTab === 'maintenance') ? 'active' : '' ?>">
            <div class="section-header">
                <h2>Maintenance</h2>
                <div class="button-group">
                    <a href="reparation_history.php?ste=<?= urlencode($ste_filter) ?>" class="btn btn-primary" target="_blank" style="margin-left:10px;">Historique des réparations</a>
                </div>
            </div>
            <!-- Search bar for Maintenance -->
            <div class="search-container">
                <div class="search-row">
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search-maintenance"
                            class="search-input"
                            placeholder="Rechercher dans la maintenance..."
                            autocomplete="off"
                        />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Filtres :</label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="NumSerie"> N° Série
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="NomPrenom"> Utilisateur
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Marque"> Marque
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="TypeLibelle"> Type
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="classification"> Classification
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Model"> Modèle
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="Dateentree"> Date Entrée
                        </label>
                        <label class="filter-checkbox">
                            <input type="checkbox" class="search-filter" data-column="État"> État
                        </label>
                    </div>
                </div>
            </div>
            <div class="section">
                <div class="state-filters">
                    <a class="active" <?= !$is_admin ? 'disabled' : '' ?> onclick="showMaintenanceSubtab('list')" id="maintenance-list-tab">Matériels à réparer</a>
                    <a class="" <?= !$is_admin ? 'disabled' : '' ?> onclick="showMaintenanceSubtab('en_reparation')" id="maintenance-en-reparation-tab">Matériel en réparation</a>
                </div>
                <div id="maintenance-list" class="maintenance-subtab">
                    <table class="table-materiel">
                        <thead>
                            <tr>
                                <th>Numéro de Série</th>
                                <th>Utilisateur</th>
                                <th>Marque</th>
                                <th>Type</th>
                                <th>Classification</th>
                                <th>Modèle</th>
                                <th>Date Entrée</th>
                                <th>État</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materiels as $materiel): ?>
                                <?php if (isset($materiel['stock']) && ($materiel['stock'] == 0 || $materiel['stock'] == 1)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($materiel['NumSerie']) ?></td>
                                    <td><?= htmlspecialchars($materiel['NomPrenom'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Marque'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['TypeLibelle'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['classification'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Model'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($materiel['Dateentree'] ?? 'N/A') ?></td>
                                    <td><?= ($materiel['stock'] == 0) ? 'En service' : 'En stock' ?></td>
                                    <td>
                                        <a href="fiche_reparation.php?numserie=<?= urlencode($materiel['NumSerie']) ?>" class="btn-primary" target="_blank" <?= !$is_admin ? 'tabindex="-1" style="pointer-events:none;opacity:0.6;"' : '' ?>>Fiche de réparation</a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="maintenance-en-reparation" class="maintenance-subtab" style="display:none;">
                    <?php
                    // Fetch materiel en reparation from the new table
                    try {
                        $reparation_stmt = $pdo->prepare("SELECT * FROM materiel_en_reparation WHERE STE = ? ORDER BY date_sent DESC");
                        $reparation_stmt->execute([$ste_filter]);
                        $materiels_en_reparation = $reparation_stmt->fetchAll();
                        // Fetch user, marque, and type names for each materiel
                        foreach ($materiels_en_reparation as &$mat) {
                            // User name
                            $userStmt = $pdo->prepare("SELECT NomPrenom FROM utilisateur WHERE Compte = ?");
                            $userStmt->execute([$mat['CodeUtilisateur']]);
                            $mat['NomPrenom'] = $userStmt->fetchColumn() ?: $mat['CodeUtilisateur'];
                            // Marque
                            $marqueStmt = $pdo->prepare("SELECT Marque FROM marque WHERE Code = ?");
                            $marqueStmt->execute([$mat['CodeMarque']]);
                            $mat['Marque'] = $marqueStmt->fetchColumn() ?: $mat['CodeMarque'];
                            // Type
                            $typeStmt = $pdo->prepare("SELECT Libelle FROM type WHERE CodeType = ?");
                            $typeStmt->execute([$mat['CodeType']]);
                            $mat['TypeLibelle'] = $typeStmt->fetchColumn() ?: $mat['CodeType'];
                        }
                        unset($mat);
                    } catch (PDOException $e) {
                        $materiels_en_reparation = [];
                    }
                    ?>
                    <table class="table-materiel">
                        <thead>
                            <tr>
                                <th>Numéro de Série</th>
                                <th>Utilisateur</th>
                                <th>Marque</th>
                                <th>Type</th>
                                <th>Classification</th>
                                <th>Modèle</th>
                                <th>Date Entrée</th>
                                <th>État</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materiels_en_reparation as $mat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($mat['NumSerie']) ?></td>
                                    <td><?= htmlspecialchars($mat['NomPrenom'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($mat['Marque'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($mat['TypeLibelle'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($mat['classification'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($mat['Model'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($mat['Dateentree'] ?? 'N/A') ?></td>
                                    <td><?= ($mat['stock'] == 0) ? 'En service' : (($mat['stock'] == 1) ? 'En stock' : $mat['stock']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="recuperer_reparation">
                                            <input type="hidden" name="NumSerie" value="<?= htmlspecialchars($mat['NumSerie']) ?>">
                                            <button type="submit" class="btn-primary" <?= !$is_admin ? 'disabled' : '' ?>>Récupérer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal for fiche de reparation will be implemented next -->
        </div>
    </div>

    <!-- State Change Modal -->
    <div id="state-change-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="state-change-label">Changer l'état du matériel</h2>
                <span class="close" onclick="closeStateChangeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="state-change-form" method="POST" action="php/get_material_history.php" data-ajax="true">
                <input type="hidden" name="action" value="change_state">
                <input type="hidden" id="state-change-numserie" name="numserie" value="">
                <input type="hidden" id="state-change-target" name="target_state" value="">
                <input type="hidden" name="ste" value="<?= $ste_filter ?>">
                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?? '' ?>">
                
                <div id="damage-cause-field" class="form-group">
                    <label>Cause:</label>
                    <textarea name="cause" rows="3" placeholder="Décrivez la cause du problème..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Notes:</label>
                    <textarea name="notes" rows="3" placeholder="Notes additionnelles..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeStateChangeModal()">Annuler</button>
                    <button type="submit" class="btn-primary">Confirmer</button>
                </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Material History Modal -->
    <div id="history-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Historique du Matériel</h2>
            <div id="history-content">
                <!-- History will be loaded here -->
            </div>
        </div>
    </div>

    <div id="notification-container"></div>

    <script src="js/script.js?v=<?= time() ?>"></script>
    <!-- <script src="js/export.js?v=<?= time() ?>"></script> -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('change', function () {
                    const ste = this.checked ? 'comm' : 'prod';
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('ste', ste);
                    window.location.href = currentUrl.toString();
                });
            }

            const materielStateSelect = document.getElementById('materiel-state-select');
            const damageCauseContainer = document.getElementById('damage-cause-container');

            if(materielStateSelect) {
                materielStateSelect.addEventListener('change', function() {
                    if (this.value === 'endommage' || this.value === 'casse') {
                        damageCauseContainer.style.display = 'block';
                    } else {
                        damageCauseContainer.style.display = 'none';
                    }
                });
            }
        });

        function handleFormSubmit(form) {
            // Find all buttons in the form and disable them to prevent multiple submissions
            const buttons = form.querySelectorAll('button, input[type="submit"]');
            buttons.forEach(button => {
                button.disabled = true;
            });
            return true; // Allow the form to be submitted
        }

        function showForm(type) {
            // Hide all other forms
            document.querySelectorAll('.section[class*="-form"]').forEach(form => {
                if (!form.classList.contains(type + '-form')) {
                    form.classList.add('hide');
                }
            });
            // Show the correct form
            const form = document.querySelector('.' + type + '-form');
            if (form) {
                form.classList.remove('hide');
            }
        }
    </script>
    <!-- Fiche de réparation modal -->
    <div id="fiche-reparation-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:600px;">
            <span class="close" onclick="closeFicheReparationModal()">&times;</span>
            <h2>Fiche de réparation</h2>
            <form id="fiche-reparation-form" method="POST">
                <input type="hidden" name="action" value="send_to_reparation">
                <input type="hidden" name="NumSerie" id="fiche-numserie" value="">
                <div class="form-group">
                    <label>Numéro de Série:</label>
                    <span id="fiche-numserie-label"></span>
                </div>
                <div class="form-group">
                    <label>Marque:</label>
                    <span id="fiche-marque-label"></span>
                </div>
                <div class="form-group">
                    <label>Type:</label>
                    <span id="fiche-type-label"></span>
                </div>
                <div class="form-group">
                    <label>Modèle:</label>
                    <span id="fiche-model-label"></span>
                </div>
                <div class="form-group">
                    <label>Utilisateur:</label>
                    <span id="fiche-user-label"></span>
                </div>
                <div class="form-group">
                    <label>Date d'entrée:</label>
                    <span id="fiche-date-label"></span>
                </div>
                <div class="form-group">
                    <label>Ce qu'il faut réparer:</label>
                    <textarea name="repair_request" id="fiche-repair-request" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Zone pour écriture manuelle après impression:</label>
                    <div style="border:1px dashed #888; height:100px; margin-bottom:10px;"></div>
                </div>
                <div class="form-group" style="display:flex; gap:10px;">
                    <button type="button" class="btn-primary" onclick="printFicheReparation()">Imprimer la fiche</button>
                    <button type="submit" class="btn-primary">Envoyer en réparation</button>
                    <button type="button" class="btn-cancel" onclick="closeFicheReparationModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <a href="user.php" class="user-profile-icon" title="User Profile">
        <img src="https://ui-avatars.com/api/?name=U&background=e0e7ef&color=222" alt="User" />
    </a>
</body>
</html>