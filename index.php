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

                    $success_message = "Le matériel a été ajouté avec succès.";
                } catch (PDOException $e) {
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
                    $success_message = "L'utilisateur a été ajouté avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout de l'utilisateur: " . $e->getMessage();
                }
                break;
                
            case 'add_marque':
                try {
                    
                    $maxCodeStmt = $pdo->query("SELECT MAX(Code) as max_code FROM marque");
                    $maxCode = $maxCodeStmt->fetchColumn();
                    $newCode = ($maxCode === null || $maxCode == 0) ? 1 : $maxCode + 1;

                    $stmt = $pdo->prepare("INSERT INTO marque (Code, Marque) VALUES (?, ?)");
                    $stmt->execute([$newCode, $_POST['Marque']]);
                    $success_message = "La marque a été ajoutée avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout de la marque. Veuillez réessayer.";
                }
                break;
                
            case 'add_type':
                try {
         
                    $maxCodeStmt = $pdo->query("SELECT MAX(CodeType) as max_code FROM type");
                    $maxCode = $maxCodeStmt->fetchColumn();
                    $newCode = ($maxCode === null || $maxCode == 0) ? 1 : $maxCode + 1;

                    $stmt = $pdo->prepare("INSERT INTO type (CodeType, Libelle) VALUES (?, ?)");
                    $stmt->execute([$newCode, $_POST['Libelle']]);
                    $success_message = "Le type a été ajouté avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout du type. Veuillez réessayer.";
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
                    $success_message = "Le service a été ajouté avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout du service: " . $e->getMessage();
                }
                break;
                
            case 'add_fournisseur':
                try {
                    $stmt = $pdo->prepare("INSERT INTO fournisseur (Email, CompanyName, NomComplet, Adress, TelFix, TelMobile) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_POST['Email'], $_POST['CompanyName'], $_POST['NomComplet'], $_POST['Adress'], $_POST['TelFix'], $_POST['TelMobile']]);
                    $success_message = "Le fournisseur a été ajouté avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout du fournisseur. Veuillez vérifier les informations et réessayer.";
                }
                break;
                
            case 'delete_materiel':
                    try {
                        $stmt = $pdo->prepare("DELETE FROM materiel WHERE NumSerie = ?");
                        $stmt->execute([$_POST['NumSerie']]);
                        $success_message = "Le matériel a été supprimé avec succès.";
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
                        $success_message = "L'utilisateur a été supprimé avec succès.";
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
                        $success_message = "La marque a été supprimée avec succès.";
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
                        $success_message = "Le type a été supprimé avec succès.";
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
                        $success_message = "Le service a été supprimé avec succès.";
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
                        $success_message = "Le fournisseur a été supprimé avec succès.";
                    }
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la suppression du fournisseur.";
                }
                break;
            
            case 'modify_materiel':
                if (empty($_POST['NumSerie']) || trim($_POST['NumSerie']) === '') {
                    $error_message = "Le numéro de série est obligatoire.";
                    break;
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
                    $success_message = "Le matériel a été modifié avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification du matériel. Veuillez vérifier les informations et réessayer.";
                }
                break;
            case 'modify_utilisateur':
                try {
                    $codeService = !empty($_POST['CodeService']) ? $_POST['CodeService'] : NULL;
                    $stmt = $pdo->prepare("UPDATE utilisateur SET CodeService = ?, Email = ?, NomPrenom = ?, Tel = ?, STE = ? WHERE Compte = ?");
                    $stmt->execute([$codeService, $_POST['Email'], $_POST['NomPrenom'], $_POST['Tel'], $_POST['STE'], $_POST['Compte']]);
                    $success_message = "L'utilisateur a été modifié avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification de l'utilisateur. Veuillez vérifier les informations et réessayer.";
                }
                break;
            case 'modify_marque':
                try {
                    $stmt = $pdo->prepare("UPDATE marque SET Marque = ? WHERE Code = ?");
                    $stmt->execute([$_POST['Marque'], $_POST['Code']]);
                    $success_message = "La marque a été modifiée avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification de la marque. Veuillez réessayer.";
                }
                break;
            case 'modify_type':
                try {
                    $stmt = $pdo->prepare("UPDATE type SET Libelle = ? WHERE CodeType = ?");
                    $stmt->execute([$_POST['Libelle'], $_POST['CodeType']]);
                    $success_message = "Le type a été modifié avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification du type. Veuillez réessayer.";
                }
                break;
            case 'modify_service':
                try {
                    $stmt = $pdo->prepare("UPDATE service SET Libelle = ?, STE = ? WHERE CodeService = ?");
                    $stmt->execute([$_POST['Libelle'], $_POST['STE'], $_POST['CodeService']]);
                    $success_message = "Le service a été modifié avec succès.";
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de la modification du service. Veuillez réessayer.";
                }
                break;
                
            case 'modify_fournisseur':
                try {
                    $stmt = $pdo->prepare("UPDATE fournisseur SET CompanyName = ?, NomComplet = ?, Adress = ?, TelFix = ?, TelMobile = ? WHERE Email = ?");
                    $stmt->execute([$_POST['CompanyName'], $_POST['NomComplet'], $_POST['Adress'], $_POST['TelFix'], $_POST['TelMobile'], $_POST['Email']]);
                    $success_message = "Le fournisseur a été modifié avec succès.";
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
                        
                        $success_message = "Le matériel a été transféré avec succès vers le département " . strtoupper($target_ste) . ".";
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
                        
                        $success_message = "L'utilisateur a été transféré avec succès vers le département " . strtoupper($target_ste) . ".";
                    } catch (PDOException $e) {
                        $error_message = "Erreur lors du transfert de l'utilisateur: " . $e->getMessage();
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
                        $success_message = "État du matériel mis à jour.";
                    }
                    header('Location: index.php?tab=materiel&ste=' . urlencode($redirectSte) . '&state=' . urlencode($redirectState));
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
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "Erreur lors de la finalisation de l'inventaire: " . $e->getMessage();
                        // To display the error, we can't redirect. We need to fall through.
                        // But the rest of the script assumes a redirect. So we'll redirect with an error flag.
                        header('Location: index.php?tab=inventaire&ste=' . urlencode($ste_param) . '&error=1');
                        exit;
                    }

                    header('Location: index.php?tab=inventaire&ste=' . urlencode($ste_param) . '&success=1');
                    exit;
                case 'recuperer_inventaire':
                    $numSerie = $_POST['NumSerie'] ?? '';
                    $current_ste = $_POST['STE'] ?? 'prod';
                    if ($numSerie !== '') {
                        $stmt = $pdo->prepare('UPDATE materiel SET inventair = 0, dateinvent = NULL WHERE NumSerie = ?');
                        $stmt->execute([$numSerie]);
                        $success_message = "Le matériel a été récupéré dans la liste principale.";
                    }
                    // Redirect back to the inventaire tab to see the list update
                    header('Location: index.php?tab=inventaire&ste=' . urlencode($current_ste) . '&success=1');
                    exit;
        }
    } catch (Exception $e) {
        $error_message = "Erreur lors du traitement de la demande: " . $e->getMessage();
    }
}

$success_message = isset($_GET['success']) ? "Opération réalisée avec succès!" : (isset($success_message) ? $success_message : null);

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
        'fournisseurs' => 'fournisseurs'
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
    $utilisateurs_stmt = $pdo->prepare("SELECT u.*, s.Libelle as ServiceLibelle FROM utilisateur u LEFT JOIN service s ON u.CodeService = s.CodeService WHERE u.STE = ? ORDER BY u.NomPrenom");
    $utilisateurs_stmt->execute([$ste_filter]);
    $utilisateurs = $utilisateurs_stmt->fetchAll();

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

$selected_state = isset($_GET['state']) ? $_GET['state'] : 'en-service';


    if ($selected_state !== 'all' && in_array($selected_state, ['en-service','en-stock','endommage','casse'])) {
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
        $inventaire_stmt = $pdo->prepare("SELECT m.*, u.NomPrenom, ma.Marque, t.Libelle as TypeLibelle FROM materiel m LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte LEFT JOIN marque ma ON m.CodeMarque = ma.Code LEFT JOIN type t ON m.CodeType = t.CodeType WHERE m.inventair = 1 AND m.STE = ? ORDER BY m.NumSerie DESC");
        $inventaire_stmt->execute([$ste_filter]);
        $inventaire_materiels = $inventaire_stmt->fetchAll();
    } catch (PDOException $e) {
        $inventaire_materiels = [];
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

// Filter materiels to exclude those in inventaire
$materiels = array_filter($materiels, function($m) { return empty($m['inventair']) || $m['inventair'] == 0; });

// Detect inventaire mode from GET
$inventaire_mode = isset($_GET['inventaire_mode']) && $_GET['inventaire_mode'] == '1';

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Matériel</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/materiel_state.css">
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
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <strong>Succès!</strong> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
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

        <main>
            <?php
            // Include the content for the selected tab
            if (file_exists("php/tabs/{$activeTab}.php")) {
                include "php/tabs/{$activeTab}.php";
            } else {
                // Fallback for old tab names if necessary
                $legacyTabFile = '';
                if ($activeTab === 'utilisateurs') $legacyTabFile = 'utilisateur';
                if ($activeTab === 'marques') $legacyTabFile = 'marque';
                if ($activeTab === 'types') $legacyTabFile = 'type';
                if ($activeTab === 'services') $legacyTabFile = 'service';
                
                if (!empty($legacyTabFile) && file_exists("php/tabs/{$legacyTabFile}.php")) {
                    include "php/tabs/{$legacyTabFile}.php";
                } else {
                    echo "<div class='tab-content active'><p>Contenu pour '" . htmlspecialchars($activeTab) . "' non trouvé.</p></div>";
                }
            }
            ?>
        </main>

    </div>

    <script src="js/script.js"></script>
    <script src="js/materiel_state.js"></script>
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