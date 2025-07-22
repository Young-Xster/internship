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
$user_id = $_POST['user_id'] ?? null;


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

    // Only update if something changed
    $changed = false;
    if ($target_state != $prev_state) {
        $changed = true;
        $update = $pdo->prepare('UPDATE materiel SET stock = ? WHERE NumSerie = ?');
        $update->execute([$target_state, $numserie]);
        log_debug("UPDATE materiel SET stock = $target_state WHERE NumSerie = $numserie");
    }
    // If user change is part of this request, handle it here (add logic if needed)
    // Example: $new_user = $_POST['new_user'] ?? null;
    // if ($new_user && $new_user != $prev_user) { ... update user ... $changed = true; }

    // Only record history if something changed
    if ($changed) {
        $history = $pdo->prepare('INSERT INTO materiel_history (numserie, prev_state, new_state, date_change, user_id, notes) VALUES (?, ?, ?, NOW(), ?, ?)');
        $history->execute([
            $numserie,
            $prev_state,
            $target_state,
            $user_id,
            $notes ?: $cause
        ]);
        log_debug("History recorded for $numserie: $prev_state -> $target_state");
    }

    echo json_encode(['success' => true, 'newState' => $target_state, 'history_recorded' => $changed]);
} catch (PDOException $e) {
    log_debug('SQL Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 