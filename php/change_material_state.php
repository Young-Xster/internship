<?php
header('Content-Type: application/json');
require_once 'config.php';

function log_debug($msg) {
    file_put_contents(__DIR__ . '/../material_notifications.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

log_debug('POST: ' . json_encode($_POST));

$numserie = $_POST['numserie'] ?? null;
$target_state = $_POST['target_state'] ?? null;
$cause = $_POST['cause'] ?? null;
$notes = $_POST['notes'] ?? null;
$ste = $_POST['ste'] ?? null;
$user_id = 'system';


if (!$numserie || $target_state === null) {
    log_debug('Missing numserie or target_state');
    echo json_encode(['success' => false, 'message' => 'Numéro de série ou état cible manquant.']);
    exit;
}

try {
    // Get previous state and user
    $stmt = $pdo->prepare('SELECT stock, CodeUtilisateur FROM materiel WHERE NumSerie = ?');
    $stmt->execute([$numserie]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $prev_state = $row['stock'];
    $prev_user_id = $row['CodeUtilisateur'];
    // Fetch previous user's name
    $prev_user_name = '';
    if ($prev_user_id) {
        $stmtPrevName = $pdo->prepare('SELECT NomPrenom FROM utilisateur WHERE Compte = ?');
        $stmtPrevName->execute([$prev_user_id]);
        $rowPrevName = $stmtPrevName->fetch(PDO::FETCH_ASSOC);
        $prev_user_name = $rowPrevName ? $rowPrevName['NomPrenom'] : '';
    }

    $new_user_id = isset($_POST['new_user']) && $_POST['new_user'] !== '' ? $_POST['new_user'] : $prev_user_id;
    // Fetch new user's name
    $new_user_name = '';
    if ($new_user_id) {
        $stmtNewName = $pdo->prepare('SELECT NomPrenom FROM utilisateur WHERE Compte = ?');
        $stmtNewName->execute([$new_user_id]);
        $rowNewName = $stmtNewName->fetch(PDO::FETCH_ASSOC);
        $new_user_name = $rowNewName ? $rowNewName['NomPrenom'] : '';
    }
    $changed = false;
    // Check if user changed (not just state)
    if ($new_user_id != $prev_user_id) {
        // Update user if changed
        $updateUser = $pdo->prepare('UPDATE materiel SET CodeUtilisateur = ? WHERE NumSerie = ?');
        $updateUser->execute([$new_user_id, $numserie]);
        log_debug("UPDATE materiel SET CodeUtilisateur = $new_user_id WHERE NumSerie = $numserie");
        // Always record in history when user changes, even if state does not change
        // Extra debug: log all values before insert
        log_debug("History insert values: numserie=$numserie, prev_state=$prev_state, target_state=$target_state, prev_user_name=$prev_user_name, new_user_name=$new_user_name, user_id=$user_id, notes=" . ($notes ?: $cause));
        $history = $pdo->prepare('INSERT INTO materiel_history (numserie, prev_state, new_state, previous_owner, new_owner, date_change, user_id, notes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)');
        $history->execute([
            $numserie ?: '',
            $prev_state !== null ? $prev_state : '',
            $target_state !== null ? $target_state : '',
            $prev_user_name ?: '',
            $new_user_name ?: '',
            $user_id ?: '',
            $notes ?: $cause ?: ''
        ]);
        log_debug("History recorded for $numserie: $prev_user_name -> $new_user_name | prev_owner: $prev_user_name | new_owner: $new_user_name");
        // Optionally update state if changed
        if ($target_state != $prev_state) {
            $update = $pdo->prepare('UPDATE materiel SET stock = ? WHERE NumSerie = ?');
            $update->execute([$target_state, $numserie]);
            log_debug("UPDATE materiel SET stock = $target_state WHERE NumSerie = $numserie");
        }
        $changed = true;
    } else if ($target_state != $prev_state) {
        // Only update state, do not log history
        $update = $pdo->prepare('UPDATE materiel SET stock = ? WHERE NumSerie = ?');
        $update->execute([$target_state, $numserie]);
        log_debug("UPDATE materiel SET stock = $target_state WHERE NumSerie = $numserie");
    }

    echo json_encode(['success' => true, 'newState' => $target_state, 'history_recorded' => $changed]);
} catch (PDOException $e) {
    log_debug('SQL Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 