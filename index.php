<?php
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

// If there's a POST, let's see what action the user wants to do
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $tab = $_GET['tab'] ?? 'materiel';
    $ste = $_POST['STE'] ?? 'prod';

    try {
        switch ($action) {
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
                    $to = '22kingofthedead17@gmail.com';
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

                    // All done! Redirect back to the main page with a success message
                    header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&success=add_materiel");
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
                        $error_message = "L'utilisateur ne peut pas être supprimé car il est lié à " . $count . " matériel(s).";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE Compte = ?");
                        $stmt->execute([$_POST['Compte']]);
                        header("Location: index.php?tab=utilisateur&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_user");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la suppression de l'utilisateur.";
                }
                break;
            case 'delete_marque':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeMarque = ?");
                    $checkStmt->execute([$_POST['Code']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        $error_message = "La marque ne peut pas être supprimée car elle est liée à " . $count . " matériel(s).";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM marque WHERE Code = ?");
                        $stmt->execute([$_POST['Code']]);
                        header("Location: index.php?tab=marque&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_marque");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la suppression de la marque.";
                }
                break;
            case 'delete_type':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeType = ?");
                    $checkStmt->execute([$_POST['CodeType']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        $error_message = "Le type ne peut pas être supprimé car il est lié à " . $count . " matériel(s).";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM type WHERE CodeType = ?");
                        $stmt->execute([$_POST['CodeType']]);
                        header("Location: index.php?tab=type&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_type");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la suppression du type.";
                }
                break;
            case 'delete_service':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE CodeService = ?");
                    $checkStmt->execute([$_POST['CodeService']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        $error_message = "Le service ne peut pas être supprimé car il est lié à " . $count . " utilisateur(s).";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM service WHERE CodeService = ?");
                        $stmt->execute([$_POST['CodeService']]);
                        header("Location: index.php?tab=service&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_service");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la suppression du service.";
                }
                break;
                    
            case 'delete_fournisseur':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeFournisseur = ?");
                    $checkStmt->execute([$_POST['Email']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        $error_message = "Le fournisseur ne peut pas être supprimé car il est lié à " . $count . " matériel(s).";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM fournisseur WHERE Email = ?");
                        $stmt->execute([$_POST['Email']]);
                        header("Location: index.php?tab=fournisseurs&ste=" . urlencode($_POST['STE'] ?? 'prod') . "&success=delete_fournisseur");
                        exit();
                    }
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la suppression du fournisseur.";
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
                    if ($original_user !== $new_user || $original_stock !== $stock) {
                        // Use the actual table structure from your screenshot
                        $history_stmt = $pdo->prepare("INSERT INTO materiel_history 
                            (numserie, prev_state, new_state, date_change, user_id, notes) 
                            VALUES (?, ?, ?, NOW(), ?, ?)");
                        
                        $notes = "Modification: ";
                        if ($original_user !== $new_user) {
                            $notes .= "Utilisateur changé de $prev_user_name à $new_user_name. ";
                        }
                        if ($original_stock !== $stock) {
                            $notes .= "État changé de " . ($stockLabelMap[$original_stock] ?? $original_stock) . " à " . ($stockLabelMap[$stock] ?? $stock) . ".";
                        }
                        
                        $history_stmt->execute([
                            $serial,
                            $original_stock,
                            $stock,
                            'system',
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
                    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
                    $redirectState = $_POST['redirect_state'] ?? $selected_state ?? 'en-service';
                    $redirectSte = $_POST['STE'] ?? $ste_filter ?? 'prod';
                    $datefinservice = $_POST['datefinservice'] ?? null;
                    if ($numSerie !== '') {
                        if ($stock == 3) {
                            if (!$datefinservice) {
                                $datefinservice = date('Y-m-d H:i:s');
                            }
                            $stmt = $pdo->prepare('UPDATE materiel SET stock = ?, datefinservice = ? WHERE NumSerie = ?');
                            $stmt->execute([$stock, $datefinservice, $numSerie]);
                        } else {
                            $stmt = $pdo->prepare('UPDATE materiel SET stock = ?, datefinservice = NULL WHERE NumSerie = ?');
                            $stmt->execute([$stock, $numSerie]);
                        }
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
        }
    } catch (Exception $e) {
        $error_message = "Erreur lors du traitement de la demande: " . $e->getMessage();
    }
}

$success_messages = [
    'add_materiel' => 'Le matériel a été ajouté avec succès.',
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
    '1' => "Erreur lors de la finalisation de l'inventaire. Consultez les logs pour plus de détails."
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
        'fournisseur' => 'fournisseurs' // Fix: map 'fournisseur' to 'fournisseurs'
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
        'fournisseurs' => 'fournisseurs'
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Matériel</title>
    <link rel="stylesheet" href="css/style.css">
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
            background-color: #28a745 !important; /* green */
            color: #fff !important;
        }
        .btn-excel-comm {
            background-color: #007bff !important; /* blue */
            color: #fff !important;
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
        </nav>

        <?php if ($activeTab === 'materiel'): ?>
        <div class="state-filters">
            <?php if ($inventaire_mode): ?>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=all" class="<?= $selected_state === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=en-service" class="<?= $selected_state === 'en-service' ? 'active' : '' ?>">En service</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=en-stock" class="<?= $selected_state === 'en-stock' ? 'active' : '' ?>">En stock</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=endommage" class="<?= $selected_state === 'endommage' ? 'active' : '' ?>">Endommagé</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=casse" class="<?= $selected_state === 'casse' ? 'active' : '' ?>">Casse</a>
            <?php else: ?>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=all" class="<?= $selected_state === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=en-service" class="<?= $selected_state === 'en-service' ? 'active' : '' ?>">En service</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=en-stock" class="<?= $selected_state === 'en-stock' ? 'active' : '' ?>">En stock</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=endommage" class="<?= $selected_state === 'endommage' ? 'active' : '' ?>">Endommagé</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=casse" class="<?= $selected_state === 'casse' ? 'active' : '' ?>">Casse</a>
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
                    <input type="hidden" name="STE" value="<?= $editMode ? htmlspecialchars($editMateriel['STE']) : $ste_filter ?>">
                    <input type="hidden" name="datefinservice" id="datefinservice-input" value="<?= $editMode ? htmlspecialchars($editMateriel['datefinservice'] ?? '') : '' ?>">
                    
                    <div class="form-group">
                        <label>Numéro de Série:</label>
                        <input type="text" name="NumSerie" value="<?= $editMode ? htmlspecialchars($editMateriel['NumSerie']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?> pattern="[^\s].*" title="Le numéro de série ne peut pas être vide ou contenir uniquement des espaces">
                    </div>
                    
                    <div class="form-group">
                        <label>Utilisateur:</label>
                        <select name="CodeUtilisateur" required>
                            <option value="">Sélectionner un utilisateur</option>
                            <?php foreach ($utilisateurs as $user): ?>
                                <option value="<?= $user['Compte'] ?>" <?= $editMode && $user['Compte'] == $editMateriel['CodeUtilisateur'] ? 'selected' : '' ?>><?= $user['NomPrenom'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fournisseur:</label>
                        <select name="CodeFournisseur" required>
                            <option value="">Sélectionner un fournisseur</option>
                            <?php foreach ($fournisseurs as $four):
                                $isSelected = $editMode && isset($editMateriel['CodeFournisseur']) && $four['Email'] == $editMateriel['CodeFournisseur'];
                                $displayName = !empty($four['CompanyName']) ? $four['CompanyName'] : $four['NomComplet'];
                                ?>
                                <option value="<?= htmlspecialchars($four['Email']) ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($displayName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Marque:</label>
                        <select name="CodeMarque" required>
                            <option value="">Sélectionner une marque</option>
                            <?php foreach ($marques as $marque): ?>
                                <option value="<?= $marque['Code'] ?>" <?= $editMode && $marque['Code'] == $editMateriel['CodeMarque'] ? 'selected' : '' ?>><?= $marque['Marque'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Type:</label>
                        <select name="CodeType" required>
                            <option value="">Sélectionner un type</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?= $type['CodeType'] ?>" <?= $editMode && $type['CodeType'] == $editMateriel['CodeType'] ? 'selected' : '' ?>><?= $type['Libelle'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    
                    <div class="form-group">
                        <label>Modèle:</label>
                        <input type="text" name="Model" value="<?= $editMode ? htmlspecialchars($editMateriel['Model']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Date d'entrée:</label>
                        <input type="date" name="Dateentree" value="<?= $editMode ? htmlspecialchars($editMateriel['Dateentree']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Processeur:</label>
                        <input type="text" name="Processeur" value="<?= $editMode ? htmlspecialchars($editMateriel['Processeur']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Carte Graphique:</label>
                        <input type="text" name="graphique" value="<?= $editMode ? htmlspecialchars($editMateriel['graphique']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Disque Dur:</label>
                        <input type="text" name="disqdur" value="<?= $editMode ? htmlspecialchars($editMateriel['disqdur']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Fréquence (MHz):</label>
                        <input type="text" name="mhtz" value="<?= $editMode ? htmlspecialchars($editMateriel['mhtz']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>MO:</label>
                        <input type="text" name="mo" value="<?= $editMode ? htmlspecialchars($editMateriel['mo']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Mémoire:</label>
                        <input type="text" name="memoire" value="<?= $editMode ? htmlspecialchars($editMateriel['memoire']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Adresse IP:</label>
                        <input type="text" name="ip" value="<?= $editMode ? htmlspecialchars($editMateriel['ip']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Écran:</label>
                        <input type="text" name="ecran" value="<?= $editMode ? htmlspecialchars($editMateriel['ecran']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Pouces:</label>
                        <input type="text" name="pouce" value="<?= $editMode ? htmlspecialchars($editMateriel['pouce']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Classification:</label>
                        <input type="text" name="classification" value="<?= $editMode ? htmlspecialchars($editMateriel['classification']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label>État:</label>
                        <select name="stock" id="materiel-state-select" onchange="toggleDamageCause(this.value)">
                            <option value="en-service" <?= ($editMode && $editMateriel['stock'] === 'en-service') || (!$editMode && $default_state === 'en-service') ? 'selected' : '' ?>>En service</option>
                            <option value="en-stock" <?= ($editMode && $editMateriel['stock'] === 'en-stock') || (!$editMode && $default_state === 'en-stock') ? 'selected' : '' ?>>En stock</option>
                            <option value="endommage" <?= ($editMode && $editMateriel['stock'] === 'endommage') || (!$editMode && $default_state === 'endommage') ? 'selected' : '' ?>>Endommagé</option>
                            <option value="casse" <?= ($editMode && $editMateriel['stock'] === 'casse') || (!$editMode && $default_state === 'casse') ? 'selected' : '' ?>>Casse</option>
                        </select>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var stateSelect = document.getElementById('materiel-state-select');
                            var dateInput = document.getElementById('datefinservice-input');
                            if (stateSelect) {
                                stateSelect.addEventListener('change', function() {
                                    if (this.value === 'casse') {
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
                                });
                                // If already selected on load
                                if (stateSelect.value === 'casse') {
                                    var now = new Date();
                                    var formatted = now.getFullYear() + '-' +
                                        String(now.getMonth()+1).padStart(2,'0') + '-' +
                                        String(now.getDate()).padStart(2,'0') + ' ' +
                                        String(now.getHours()).padStart(2,'0') + ':' +
                                        String(now.getMinutes()).padStart(2,'0') + ':' +
                                        String(now.getSeconds()).padStart(2,'0');
                                    dateInput.value = formatted;
                                }
                            }
                        });
                        </script>
                    </div>

                    <div id="damage-cause-group" class="form-group" style="display: <?= $editMode && ($editMateriel['stock'] === 'endommage' || $editMateriel['stock'] === 'casse') ? 'block' : 'none' ?>;">
                        <label>Cause du dommage:</label>
                        <textarea name="damage_cause" rows="2"><?= $editMode ? htmlspecialchars($editMateriel['damage_cause'] ?? '') : '' ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Observation:</label>
                        <textarea name="observation" rows="3"><?= $editMode ? htmlspecialchars($editMateriel['observation']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-primary"><?= $editMode ? 'Modifier le Matériel' : 'Ajouter le Matériel' ?></button>
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
                        <label>Matériel:</label>
                        <input type="text" value="<?= ($transferMode && $transferType === 'materiel') ? htmlspecialchars($transferMateriel['Model']) : '' ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Transférer vers:</label>
                        <input type="text" name="target_STE" value="<?= ($ste_filter === 'prod') ? 'COMM' : 'PROD' ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Nouveau Propriétaire:</label>
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>&state=<?= urlencode($selected_state) ?>&showForm=materiel'">Ajouter Matériel</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('materiel-table', 'materiel_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                    <a href="export_pdf.php?ste=<?= urlencode($ste_filter) ?>" class="btn btn-primary btn-export-pdf">
                        <i class="fas fa-file-pdf"></i> Exporter en PDF
                    </a>
                    <?php else: ?>
                    <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-cancel">Annuler l'Inventaire</a>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" id="fin-inventaire-form">
                <input type="hidden" name="action" value="fin_inventaire">
                <input type="hidden" name="ste" value="<?= htmlspecialchars($ste_filter) ?>">
                <input type="hidden" name="state" value="<?= htmlspecialchars($selected_state) ?>">
                <div class="table-container">
                    <table id="materiel-table" class="table-materiel">
                        <thead>
                            <tr>
                                <?php if ($inventaire_mode): ?>
                                <th>Présent</th>
                                <?php endif; ?>
                                <th>Numéro de Série</th>
                                <th>Utilisateur</th>
                                <th>Marque</th>
                                <th>Type</th>
                                <th>Modèle</th>
                                <th>Date Entrée</th>
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
                                <td><?= htmlspecialchars($materiel['NomPrenom'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($materiel['Marque'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($materiel['TypeLibelle'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($materiel['Model'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($materiel['Dateentree'] ?? 'N/A') ?></td>
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
                                        <a href="index.php?edit=<?= $materiel['NumSerie'] ?>&type=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier"><img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/></a>
                                        <a href="index.php?transfer=<?= $materiel['NumSerie'] ?>&type=materiel&ste=<?= urlencode($ste_filter) ?>" class="btn-transfer" title="Transférer"><img width="20px" height="20px" src="imgs/transfer.png" alt="transférer"/></a>
                                        <a href="get_material_history.php?numserie=<?= $materiel['NumSerie'] ?>&ste=<?= urlencode($ste_filter) ?>" class="btn-history" title="Historique"><img width="20px" height="20px" src="imgs/history.png" alt="historique"/></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce matériel ?');">
                                            <input type="hidden" name="action" value="delete_materiel">
                                            <input type="hidden" name="NumSerie" value="<?= $materiel['NumSerie'] ?>">
                                            <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                            <button type="submit" class="btn-delete" title="Supprimer"><img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/></button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                    <a href="index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>&inventaire_mode=1" class="btn btn-primary btn-export-pdf">Début Inventaire</a>
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
                                            <button type="submit" class="btn-primary">Récupérer</button>
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
                        <label>Compte:</label>
                        <input type="text" name="Compte" value="<?= $editMode && isset($editUtilisateur['Compte']) ? htmlspecialchars($editUtilisateur['Compte']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?> >
                    </div>

                    <div class="form-group">
                        <label>Nom et Prénom:</label>
                        <input type="text" name="NomPrenom" value="<?= $editMode && isset($editUtilisateur['NomPrenom']) ? htmlspecialchars($editUtilisateur['NomPrenom']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Service:</label>
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=utilisateur&ste=<?= urlencode($ste_filter) ?>&showForm=utilisateur'">Ajouter Utilisateur</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('utilisateurs-table', 'utilisateurs_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                                    <a href="index.php?edit=<?= $utilisateur['Compte'] ?>&type=utilisateur&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier"><img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/></a>
                                    <!-- <a href="index.php?transfer=<?= $utilisateur['Compte'] ?>&type=utilisateur&ste=<?= urlencode($ste_filter) ?>" class="btn-transfer" title="Transférer"><img width="20px" height="20px" src="imgs/transfer.png" alt="transférer"/></a> -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                        <input type="hidden" name="action" value="delete_utilisateur">
                                        <input type="hidden" name="Compte" value="<?= $utilisateur['Compte'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer"><img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/></button>
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
                        <label>Marque:</label>
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=marque&showForm=marque'">Ajouter Marque</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('marques-table', 'marques_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                                    <a href="index.php?edit=<?= $marque['Code'] ?>&type=marque&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier"><img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette marque ?');">
                                        <input type="hidden" name="action" value="delete_marque">
                                        <input type="hidden" name="Code" value="<?= $marque['Code'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer"><img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/></button>
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
                        <label>Libellé:</label>
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=type&showForm=type'">Ajouter Type</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('types-table', 'types_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                                    <a href="index.php?edit=<?= $type['CodeType'] ?>&type=type&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier"><img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce type ?');">
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="CodeType" value="<?= $type['CodeType'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer"><img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/></button>
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
                        <label>Libellé:</label>
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=service&ste=<?= urlencode($ste_filter) ?>&showForm=service'">Ajouter Service</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('services-table', 'services_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                                    <a href="index.php?edit=<?= $service['CodeService'] ?>&type=service&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier"><img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="CodeService" value="<?= $service['CodeService'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer"><img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/></button>
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
                        <label>Email:</label>
                        <input type="email" name="Email" value="<?= $editMode ? htmlspecialchars($editFournisseur['Email']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>Nom de la société:</label>
                        <input type="text" name="CompanyName" value="<?= $editMode ? htmlspecialchars($editFournisseur['CompanyName']) : '' ?>">
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=fournisseurs&showForm=fournisseur'">Ajouter Fournisseur</button>
                    <button class="btn btn-primary btn-excel-<?= $ste_filter ?>" onclick="exportTableToExcel('fournisseurs-table', 'fournisseurs_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                                    <a href="index.php?edit=<?= urlencode($fournisseur['Email']) ?>&type=fournisseur&ste=<?= urlencode($ste_filter) ?>" class="btn-modify" title="Modifier"><img width="20px" height="20px" src="imgs/edit.png" alt="modifier"/></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">
                                        <input type="hidden" name="action" value="delete_fournisseur">
                                        <input type="hidden" name="Email" value="<?= $fournisseur['Email'] ?>">
                                        <input type="hidden" name="STE" value="<?= $ste_filter ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer"><img width="20px" height="20px" src="imgs/trash.png" alt="Supprimer"/></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
</body>
</html>