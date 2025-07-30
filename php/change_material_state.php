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
    $prev_user = $row['CodeUtilisateur'];

    $new_user = $_POST['new_user'] ?? $prev_user;
    $changed = false;
    // Check if user changed (not just state)
    if ($new_user != $prev_user) {
        // Update user if changed
        $updateUser = $pdo->prepare('UPDATE materiel SET CodeUtilisateur = ? WHERE NumSerie = ?');
        $updateUser->execute([$new_user, $numserie]);
        log_debug("UPDATE materiel SET CodeUtilisateur = $new_user WHERE NumSerie = $numserie");
        // Always record in history when user changes, even if state does not change
        $history = $pdo->prepare('INSERT INTO materiel_history (numserie, prev_state, new_state, previous_owner, new_owner, date_change, user_id, notes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)');
        $history->execute([
            $numserie,
            $prev_state,
            $target_state,
            $prev_user,
            $new_user,
            $user_id,
            $notes ?: $cause
        ]);
        log_debug("History recorded for $numserie: $prev_user -> $new_user | prev_owner: $prev_user | new_owner: $new_user");
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