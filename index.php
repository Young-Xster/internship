<?php
require_once 'php/config.php';
// Email functionality removed to improve performance

// Add this mapping at the top of the file (if not already present)
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

// DEBUG: Log all POST requests for troubleshooting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/material_notifications.log', date('Y-m-d H:i:s') . ' POST: ' . json_encode($_POST) . "\n", FILE_APPEND);
}

// Check for success or error messages from redirects
if (isset($_GET['success_message'])) {
    $success_message = htmlspecialchars($_GET['success_message']);
}
if (isset($_GET['error_message'])) {
    $error_message = htmlspecialchars($_GET['error_message']);
}


if ($_POST) {
    $action = $_POST['action'] ?? '';
    $tab = $_GET['tab'] ?? 'materiel';
    $ste = $_POST['STE'] ?? 'prod';

    try {
        switch ($action) {
            case 'add_materiel':
                // The nested try...catch is redundant if the outer one catches PDOException.
                // For consistency, each action will have its own try...catch.
                try {
                    $stmt = $pdo->prepare("INSERT INTO MATERIEL (NumSerie, Dateentree, Model, ID_Type, ID_Marque, ID_Fournisseur, STE, CodeUtilisateur, Processeur, graphique, disqdur, mhtz, mo, memoire, ip, ecran, pouce, observation, stock, classification, damage_cause) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

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
                        $_POST['stock'],
                        $_POST['classification'],
                        $damageCause
                    ]);

                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le matériel a été ajouté avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de l'ajout du matériel: " . $e->getMessage()));
                    exit;
                }
                break;
    
            case 'add_utilisateur':
                try {
                    // Check if user already exists in ANY environment
                    $checkStmt = $pdo->prepare("SELECT Compte, STE FROM utilisateur WHERE Compte = ?");
                    $checkStmt->execute([$_POST['Compte']]);
                    if ($existing_user = $checkStmt->fetch()) {
                        $existing_dept = strtoupper(htmlspecialchars($existing_user['STE']));
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Ce compte utilisateur existe déjà dans l'environnement " . $existing_dept . ". Un utilisateur ne peut exister que dans un seul environnement."));
                        exit;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO utilisateur (Compte, CodeService, Email, NomPrenom, Tel, STE) VALUES (?, ?, ?, ?, ?, ?)");
                    $codeService = !empty($_POST['CodeService']) ? $_POST['CodeService'] : NULL;
                    $stmt->execute([$_POST['Compte'], $codeService, $_POST['Email'], $_POST['NomPrenom'], $_POST['Tel'], $_POST['STE']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("L'utilisateur a été ajouté avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de l'ajout de l'utilisateur: " . $e->getMessage()));
                    exit;
                }
                break;
                
            case 'add_marque':
                try {
                    
                    $maxCodeStmt = $pdo->query("SELECT MAX(Code) as max_code FROM marque");
                    $maxCode = $maxCodeStmt->fetchColumn();
                    $newCode = ($maxCode === null || $maxCode == 0) ? 1 : $maxCode + 1;

                    $stmt = $pdo->prepare("INSERT INTO marque (Code, Marque) VALUES (?, ?)");
                    $stmt->execute([$newCode, $_POST['Marque']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("La marque a été ajoutée avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de l'ajout de la marque. Veuillez réessayer."));
                    exit;
                }
                break;
                
            case 'add_type':
                try {
         
                    $maxCodeStmt = $pdo->query("SELECT MAX(CodeType) as max_code FROM type");
                    $maxCode = $maxCodeStmt->fetchColumn();
                    $newCode = ($maxCode === null || $maxCode == 0) ? 1 : $maxCode + 1;

                    $stmt = $pdo->prepare("INSERT INTO type (CodeType, Libelle) VALUES (?, ?)");
                    $stmt->execute([$newCode, $_POST['Libelle']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le type a été ajouté avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de l'ajout du type. Veuillez réessayer."));
                    exit;
                }
                break;
                
            case 'add_service':
                try {
                    // Auto-generate the next CodeService
                    $maxCodeStmt = $pdo->query("SELECT MAX(CodeService) as max_code FROM service");
                    $maxCode = $maxCodeStmt->fetchColumn();
                    $newCode = ($maxCode === null || $maxCode == 0) ? 1 : $maxCode + 1;

                    $stmt = $pdo->prepare("INSERT INTO service (CodeService, Libelle, STE) VALUES (?, ?, ?)");
                    $stmt->execute([$newCode, $_POST['Libelle'], $_POST['STE']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le service a été ajouté avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de l'ajout du service: " . $e->getMessage()));
                    exit;
                }
                break;
                
            case 'add_fournisseur':
                try {
                    $stmt = $pdo->prepare("INSERT INTO fournisseur (Email, CompanyName, NomComplet, Adress, TelFix, TelMobile) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_POST['Email'], $_POST['CompanyName'], $_POST['NomComplet'], $_POST['Adress'], $_POST['TelFix'], $_POST['TelMobile']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le fournisseur a été ajouté avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de l'ajout du fournisseur. Veuillez vérifier les informations et réessayer."));
                    exit;
                }
                break;
                
            case 'delete_materiel':
                    try {
                        $stmt = $pdo->prepare("DELETE FROM materiel WHERE NumSerie = ?");
                        $stmt->execute([$_POST['NumSerie']]);
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le matériel a été supprimé avec succès."));
                        exit;
                    } catch (PDOException $e) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la suppression du matériel. Il se peut qu'il soit encore lié à d'autres enregistrements."));
                        exit;
                    }
                    break;
            case 'delete_utilisateur':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeUtilisateur = ?");
                    $checkStmt->execute([$_POST['Compte']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("L'utilisateur ne peut pas être supprimé car il est lié à " . $count . " matériel(s)."));
                        exit;
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE Compte = ?");
                        $stmt->execute([$_POST['Compte']]);
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("L'utilisateur a été supprimé avec succès."));
                        exit;
                    }
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la suppression de l'utilisateur."));
                    exit;
                }
                break;
            case 'delete_marque':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeMarque = ?");
                    $checkStmt->execute([$_POST['Code']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("La marque ne peut pas être supprimée car elle est liée à " . $count . " matériel(s)."));
                        exit;
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM marque WHERE Code = ?");
                        $stmt->execute([$_POST['Code']]);
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("La marque a été supprimée avec succès."));
                        exit;
                    }
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la suppression de la marque."));
                    exit;
                }
                break;
            case 'delete_type':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeType = ?");
                    $checkStmt->execute([$_POST['CodeType']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Le type ne peut pas être supprimé car il est lié à " . $count . " matériel(s)."));
                        exit;
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM type WHERE CodeType = ?");
                        $stmt->execute([$_POST['CodeType']]);
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le type a été supprimé avec succès."));
                        exit;
                    }
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la suppression du type."));
                    exit;
                }
                break;
            case 'delete_service':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur WHERE CodeService = ?");
                    $checkStmt->execute([$_POST['CodeService']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Le service ne peut pas être supprimé car il est lié à " . $count . " utilisateur(s)."));
                        exit;
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM service WHERE CodeService = ?");
                        $stmt->execute([$_POST['CodeService']]);
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le service a été supprimé avec succès."));
                        exit;
                    }
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la suppression du service."));
                    exit;
                }
                break;
                    
            case 'delete_fournisseur':
                try {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM materiel WHERE CodeFournisseur = ?");
                    $checkStmt->execute([$_POST['Email']]);
                    $count = $checkStmt->fetchColumn();

                    if ($count > 0) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Le fournisseur ne peut pas être supprimé car il est lié à " . $count . " matériel(s)."));
                        exit;
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM fournisseur WHERE Email = ?");
                        $stmt->execute([$_POST['Email']]);
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le fournisseur a été supprimé avec succès."));
                        exit;
                    }
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la suppression du fournisseur."));
                    exit;
                }
                break;
            
            case 'modify_materiel':
                if (empty($_POST['NumSerie']) || trim($_POST['NumSerie']) === '') {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Le numéro de série est obligatoire."));
                    exit;
                }
                
                $codeUtilisateur = !empty($_POST['CodeUtilisateur']) ? $_POST['CodeUtilisateur'] : NULL;
                $codeMarque = !empty($_POST['CodeMarque']) ? $_POST['CodeMarque'] : NULL;
                $codeType = !empty($_POST['CodeType']) ? $_POST['CodeType'] : NULL;
                $codeFournisseur = !empty($_POST['CodeFournisseur']) ? $_POST['CodeFournisseur'] : NULL;
                
                $dateentree = !empty($_POST['Dateentree']) ? $_POST['Dateentree'] : NULL;
                
                try {
                    // Get current state to check if it's changed
                    $prevStateStmt = $pdo->prepare("SELECT stock FROM materiel WHERE NumSerie = ?");
                    $prevStateStmt->execute([trim($_POST['NumSerie'])]);
                    $prevState = $prevStateStmt->fetchColumn();
                    
                    // Only include damage_cause if state requires it
                    $damageCause = null;
                    if ($_POST['stock'] === 'endommage' || $_POST['stock'] === 'casse') {
                        $damageCause = $_POST['damage_cause'] ?? null;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE materiel SET CodeUtilisateur = ?, CodeMarque = ?, CodeType = ?, CodeFournisseur = ?, STE = ?, Model = ?, Dateentree = ?, Processeur = ?, graphique = ?, disqdur = ?, mhtz = ?, mo = ?, memoire = ?, ip = ?, ecran = ?, pouce = ?, observation = ?, stock = ?, classification = ?, damage_cause = ? WHERE NumSerie = ?");
                    $stmt->execute([
                        $codeUtilisateur, $codeMarque, $codeType, $codeFournisseur,
                        $_POST['STE'], $_POST['Model'], $dateentree, $_POST['Processeur'],
                        $_POST['graphique'], $_POST['disqdur'], $_POST['mhtz'], $_POST['mo'],
                        $_POST['memoire'], $_POST['ip'], $_POST['ecran'], $_POST['pouce'],
                        $_POST['observation'], $_POST['stock'], $_POST['classification'],
                        $damageCause, trim($_POST['NumSerie'])
                    ]);
                    
                    // Record state change in history if state has changed
                    if ($prevState !== $_POST['stock']) {
                        $historyStmt = $pdo->prepare("INSERT INTO materiel_history (numserie, prev_state, new_state, date_change, user_id, notes) VALUES (?, ?, ?, NOW(), ?, 'Changement via formulaire de modification')");
                        $historyStmt->execute([
                            trim($_POST['NumSerie']),
                            $prevState,
                            $_POST['stock'],
                            NULL // Would be current user ID in a real authentication system
                        ]);
                    }
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le matériel a été modifié avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la modification du matériel. Veuillez vérifier les informations et réessayer."));
                    exit;
                }
                break;
            case 'modify_utilisateur':
                try {
                    $codeService = !empty($_POST['CodeService']) ? $_POST['CodeService'] : NULL;
                    $stmt = $pdo->prepare("UPDATE utilisateur SET CodeService = ?, Email = ?, NomPrenom = ?, Tel = ?, STE = ? WHERE Compte = ?");
                    $stmt->execute([$codeService, $_POST['Email'], $_POST['NomPrenom'], $_POST['Tel'], $_POST['STE'], $_POST['Compte']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("L'utilisateur a été modifié avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la modification de l'utilisateur. Veuillez vérifier les informations et réessayer."));
                    exit;
                }
                break;
            case 'modify_marque':
                try {
                    $stmt = $pdo->prepare("UPDATE marque SET Marque = ? WHERE Code = ?");
                    $stmt->execute([$_POST['Marque'], $_POST['Code']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("La marque a été modifiée avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la modification de la marque. Veuillez réessayer."));
                    exit;
                }
                break;
            case 'modify_type':
                try {
                    $stmt = $pdo->prepare("UPDATE type SET Libelle = ? WHERE CodeType = ?");
                    $stmt->execute([$_POST['Libelle'], $_POST['CodeType']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le type a été modifié avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la modification du type. Veuillez réessayer."));
                    exit;
                }
                break;
            case 'modify_service':
                try {
                    $stmt = $pdo->prepare("UPDATE service SET Libelle = ?, STE = ? WHERE CodeService = ?");
                    $stmt->execute([$_POST['Libelle'], $_POST['STE'], $_POST['CodeService']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le service a été modifié avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la modification du service. Veuillez réessayer."));
                    exit;
                }
                break;
                
            case 'modify_fournisseur':
                try {
                    $stmt = $pdo->prepare("UPDATE fournisseur SET CompanyName = ?, NomComplet = ?, Adress = ?, TelFix = ?, TelMobile = ? WHERE Email = ?");
                    $stmt->execute([$_POST['CompanyName'], $_POST['NomComplet'], $_POST['Adress'], $_POST['TelFix'], $_POST['TelMobile'], $_POST['Email']]);
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&success_message=' . urlencode("Le fournisseur a été modifié avec succès."));
                    exit;
                } catch (PDOException $e) {
                    header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Une erreur est survenue lors de la modification du fournisseur. Veuillez vérifier les informations et réessayer."));
                    exit;
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
                        
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($current_ste) . '&success_message=' . urlencode("Le matériel a été transféré avec succès vers le département " . strtoupper($target_ste) . "."));
                        exit;
                    } catch (PDOException $e) {
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($current_ste) . '&error_message=' . urlencode("Erreur lors du transfert du matériel: " . $e->getMessage()));
                        exit;
                    }
                    break;
                case 'transfer_utilisateur':
                    try {
                        $compte = $_POST['Compte'];
                        $code_service = $_POST['CodeService'];
                        $target_ste = $_POST['target_STE'];
                        $current_ste = $_POST['STE'];
                        
                        // Update the user with the new service and change STE
                        $stmt = $pdo->prepare("UPDATE utilisateur SET CodeService = ?, STE = ? WHERE Compte = ?");
                        $stmt->execute([$code_service, $target_ste, $compte]);
                        
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($current_ste) . '&success_message=' . urlencode("L'utilisateur a été transféré avec succès vers le département " . strtoupper($target_ste) . "."));
                        exit;
                    } catch (PDOException $e) {
                        $current_ste = $_POST['STE'] ?? 'prod';
                        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($current_ste) . '&error_message=' . urlencode("Erreur lors du transfert de l'utilisateur: " . $e->getMessage()));
                        exit;
                    }
                    break;
                case 'change_state':
                    $numSerie = $_POST['NumSerie'] ?? '';
                    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
                    $redirectState = $_POST['redirect_state'] ?? $selected_state ?? 'en-service';
                    $redirectSte = $_POST['STE'] ?? $ste_filter ?? 'prod';
                    if ($numSerie !== '') {
                        $stmt = $pdo->prepare('UPDATE materiel SET stock = ? WHERE NumSerie = ?');
                        $stmt->execute([$stock, $numSerie]);
                        // Success message is now handled by the redirect
                    }
                    header('Location: index.php?tab=materiel&ste=' . urlencode($redirectSte) . '&state=' . urlencode($redirectState) . '&success_message=' . urlencode("État du matériel mis à jour."));
                    exit;
                case 'fin_inventaire':
                    $present = isset($_POST['present']) ? $_POST['present'] : [];
                    $state_filter = $_POST['state'] ?? null;
                    $ste_param = $_POST['ste'] ?? $ste_filter;
                    // Select all materials for the given STE that are not already in inventory
                    $query = "SELECT NumSerie FROM materiel WHERE STE = ? AND (inventair = 0 OR inventair IS NULL)";
                    $params = [$ste_param];
                    if ($state_filter && in_array($state_filter, ['en-service','en-stock','endommage','casse'])) {
                        $query .= " AND stock = ?";
                        $params[] = $state_filter;
                    }
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $materiel_nums = array_column($stmt->fetchAll(), 'NumSerie');
                    
                    $pdo->beginTransaction();
                    try {
                        foreach ($materiel_nums as $num) {
                            if (in_array($num, $present)) {
                                // Checked: stays in main list (or comes back from inventaire)
                                $update = $pdo->prepare('UPDATE materiel SET inventair = 0, dateinvent = NULL WHERE NumSerie = ?');
                                $update->execute([$num]);
                            } else {
                                // Unchecked: goes to inventaire list
                                $update = $pdo->prepare('UPDATE materiel SET inventair = 1, dateinvent = NOW() WHERE NumSerie = ?');
                                $update->execute([$num]);
                            }
                        }
                        $pdo->commit();
                        $success_message = "L'inventaire a été finalisé avec succès.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "Erreur lors de la finalisation de l'inventaire: " . $e->getMessage();
                        // To display the error, we can't redirect. We need to fall through.
                        // But the rest of the script assumes a redirect. So we'll redirect with an error flag.
                        header('Location: index.php?tab=inventaire&ste=' . urlencode($ste_param) . '&error_message=' . urlencode($error_message));
                        exit;
                    }

                    header('Location: index.php?tab=inventaire&ste=' . urlencode($ste_param) . '&success_message=' . urlencode($success_message));
                    exit;
                case 'recuperer_inventaire':
                    $numSerie = $_POST['NumSerie'] ?? '';
                    $current_ste = $_POST['STE'] ?? 'prod';
                    if ($numSerie !== '') {
                        $stmt = $pdo->prepare('UPDATE materiel SET inventair = 0, dateinvent = NULL WHERE NumSerie = ?');
                        $stmt->execute([$numSerie]);
                        // Success message is now handled by the redirect
                    }
                    // Redirect back to the inventaire tab to see the list update
                    header('Location: index.php?tab=inventaire&ste=' . urlencode($current_ste) . '&success=1&success_message=' . urlencode("Le matériel a été récupéré dans la liste principale."));
                    exit;
        }
    } catch (Exception $e) {
        $tab = $_GET['tab'] ?? 'materiel';
        $ste = $_POST['STE'] ?? 'prod';
        header('Location: index.php?tab=' . urlencode($tab) . '&ste=' . urlencode($ste) . '&error_message=' . urlencode("Erreur lors du traitement de la demande: " . $e->getMessage()));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Parc Informatique</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/materiel_state.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <img src="imgs/Logo_AAF.JPG" alt="Logo">
            </div>
            <h1>Gestion de Parc Informatique</h1>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="success-message" id="success-message">
                <?php echo $success_message; ?>
                <span class="close-btn" onclick="document.getElementById('success-message').style.display='none'">&times;</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error-message" id="error-message">
                <?php echo $error_message; ?>
                <span class="close-btn" onclick="document.getElementById('error-message').style.display='none'">&times;</span>
            </div>
        <?php endif; ?>

        <nav>
            <ul>
                <?php
                $tabs = ['materiel', 'utilisateurs', 'marques', 'types', 'services', 'fournisseurs', 'inventaire'];
                $current_tab = $_GET['tab'] ?? 'materiel';
                $is_form_open = ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['success_message']));

                foreach ($tabs as $tab_item) {
                    $active_class = ($tab_item == $current_tab) ? 'active' : '';
                    $disabled_attr = ($is_form_open && $tab_item !== $current_tab) ? 'disabled' : '';
                    $link = $is_form_open ? '#' : 'index.php?tab=' . $tab_item;
                    echo "<li><a href=\"$link\" class=\"$active_class\" $disabled_attr>" . ucfirst($tab_item) . "</a></li>";
                }
                ?>
            </ul>
        </nav>
        
        <main>
            <?php
            $tab = $_GET['tab'] ?? 'materiel';
            $action = $_GET['action'] ?? 'view';
            $id = $_GET['id'] ?? null;
            $ste_filter = $_GET['ste'] ?? 'prod';
            $selected_state = $_GET['state'] ?? 'en-service';

            // Fetch data for dropdowns
            $marques = $pdo->query("SELECT * FROM marque ORDER BY Marque")->fetchAll();
            $types = $pdo->query("SELECT * FROM type ORDER BY Libelle")->fetchAll();
            $fournisseurs = $pdo->query("SELECT * FROM fournisseur ORDER BY CompanyName")->fetchAll();
            
            // Fetch users and services based on STE filter
            $services_stmt = $pdo->prepare("SELECT * FROM service WHERE STE = ? ORDER BY Libelle");
            $services_stmt->execute([$ste_filter]);
            $services = $services_stmt->fetchAll();

            $utilisateurs_stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE STE = ? ORDER BY NomPrenom");
            $utilisateurs_stmt->execute([$ste_filter]);
            $utilisateurs = $utilisateurs_stmt->fetchAll();

            // Include the content for the selected tab
            if (file_exists("php/tabs/{$tab}.php")) {
                include "php/tabs/{$tab}.php";
            } else {
                echo "<p>Onglet non trouvé.</p>";
            }
            ?>
        </main>
    </div>

    <script src="js/script.js"></script>
    <script src="js/materiel_state.js"></script>
    <script>
        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const successMsg = document.getElementById('success-message');
            if (successMsg) {
                successMsg.style.display = 'none';
            }
            const errorMsg = document.getElementById('error-message');
            if (errorMsg) {
                errorMsg.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>