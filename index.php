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
                    $stmt = $pdo->prepare("INSERT INTO MATERIEL (NumSerie, Dateentree, Model, CodeType, CodeMarque, CodeFournisseur, STE, CodeUtilisateur, Processeur, graphique, disqdur, mhtz, mo, memoire, ip, ecran, pouce, observation, stock, classification, damage_cause) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

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
                    header("Location: index.php?tab=materiel&ste=" . urlencode($_POST['STE']) . "&success=add_materiel");
                    exit();
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
                    header("Location: index.php?tab=utilisateur&ste=" . urlencode($_POST['STE']) . "&success=add_user");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Une erreur est survenue lors de l'ajout de l'utilisateur: " . $e->getMessage();
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
                            
                    // Update material first
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
                        $_POST['observation'], $stock, $_POST['classification'], $damageCause, $serial
                    ]);

                    
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
                    if ($numSerie !== '') {
                        $stmt = $pdo->prepare('UPDATE materiel SET stock = ? WHERE NumSerie = ?');
                        $stmt->execute([$stock, $numSerie]);
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
    '1' => "Opération réalisée avec succès!" // Generic for inventaire
];

$success_code = $_GET['success'] ?? null;
$success_message = null;
if ($success_code && isset($success_messages[$success_code])) {
    $success_message = $success_messages[$success_code];
} elseif (isset($success_message)) {
    // Keep any existing success message if no GET param
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

        <?php if ($activeTab === 'materiel'): ?>
        <div class="state-filters">
            <?php if ($inventaire_mode): ?>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=all" class="<?= $selected_state === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=en-service" class="<?= $selected_state === 'en-service' ? 'active' : '' ?>">En service</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=en-stock" class="<?= $selected_state === 'en-stock' ? 'active' : '' ?>">En stock</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=endommage" class="<?= $selected_state === 'endommage' ? 'active' : '' ?>">Endommagé</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&inventaire_mode=1&state=casse" class="<?= $selected_state === 'casse' ? 'active' : '' ?>">Cassé</a>
            <?php else: ?>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=all" class="<?= $selected_state === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=en-service" class="<?= $selected_state === 'en-service' ? 'active' : '' ?>">En service</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=en-stock" class="<?= $selected_state === 'en-stock' ? 'active' : '' ?>">En stock</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=endommage" class="<?= $selected_state === 'endommage' ? 'active' : '' ?>">Endommagé</a>
                <a href="?tab=materiel&ste=<?= $ste_filter ?>&state=casse" class="<?= $selected_state === 'casse' ? 'active' : '' ?>">Cassé</a>
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
                            <option value="en-service" <?= $editMode && $editMateriel['stock'] === 'en-service' ? 'selected' : '' ?>>En service</option>
                            <option value="en-stock" <?= $editMode && $editMateriel['stock'] === 'en-stock' ? 'selected' : '' ?>>En stock</option>
                            <option value="endommage" <?= $editMode && $editMateriel['stock'] === 'endommage' ? 'selected' : '' ?>>Endommagé</option>
                            <option value="casse" <?= $editMode && $editMateriel['stock'] === 'casse' ? 'selected' : '' ?>>Cassé</option>
                        </select>
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
                    <button class="btn-primary" onclick="window.location.href='index.php?tab=materiel&ste=<?= urlencode($ste_filter) ?>&showForm=materiel'">Ajouter Matériel</button>
                    <button class="btn-export" onclick="exportTableToExcel('materiel-table', 'materiel_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
                    <a href="export_csv.php" class="btn btn-primary">
                        <i class="fas fa-file-csv"></i> Exporter en CSV
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
                                <th>observation</th>
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
                                    <form method="POST" style="display:inline; margin:0;">
                                        <input type="hidden" name="action" value="change_state">
                                        <input type="hidden" name="NumSerie" value="<?= $materiel['NumSerie'] ?>">
                                        <input type="hidden" name="STE" value="<?= htmlspecialchars($ste_filter) ?>">
                                        <input type="hidden" name="redirect_state" value="<?= htmlspecialchars($selected_state) ?>">
                                        <select name="stock" onchange="this.form.submit()">
                                            <?php foreach ($stockLabelMap as $val => $label): ?>
                                            <option value="<?= $val ?>" <?= (isset($materiel['stock']) && $materiel['stock'] == $val) ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
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
                                <td><?= $materiel['observation'] ?? 'N/A' ?></td>
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
                    <input type="hidden" name="action" value="<?= $editMode && $editType === 'utilisateur' ? 'modify_utilisateur' : 'add_utilisateur' ?>">
                    <input type="hidden" name="STE" value="<?= $editMode ? htmlspecialchars($editUtilisateur['STE']) : $ste_filter ?>">
                    
                    <div class="form-group">
                        <label>Compte:</label>
                        <input type="text" name="Compte" value="<?= $editMode ? htmlspecialchars($editUtilisateur['Compte']) : '' ?>" required <?= $editMode ? 'readonly' : '' ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>Nom et Prénom:</label>
                        <input type="text" name="NomPrenom" value="<?= $editMode ? htmlspecialchars($editUtilisateur['NomPrenom']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Service:</label>
                        <select name="CodeService">
                            <option value="">Non spécifié</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['CodeService'] ?>" <?= $editMode && $service['CodeService'] == $editUtilisateur['CodeService'] ? 'selected' : '' ?>><?= $service['Libelle'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="Email" value="<?= $editMode ? htmlspecialchars($editUtilisateur['Email']) : '' ?>">
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
                    <button class="btn-export" onclick="exportTableToExcel('utilisateurs-table', 'utilisateurs_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                    <button class="btn-export" onclick="exportTableToExcel('marques-table', 'marques_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                    <button class="btn-export" onclick="exportTableToExcel('types-table', 'types_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                    <button class="btn-export" onclick="exportTableToExcel('services-table', 'services_<?= htmlspecialchars($ste_filter) ?>_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
                    <button class="btn-export" onclick="exportTableToExcel('fournisseurs-table', 'fournisseurs_<?= date('Y-m-d') ?>.xlsx')">Exporter en Excel</button>
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
    <script src="js/export.js?v=<?= time() ?>"></script>
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