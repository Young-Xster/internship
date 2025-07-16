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
    // Get previous state
    $stmt = $pdo->prepare('SELECT stock FROM materiel WHERE NumSerie = ?');
    $stmt->execute([$numserie]);
    $prev_state = $stmt->fetchColumn();

    // Update materiel state
    $update = $pdo->prepare('UPDATE materiel SET stock = ? WHERE NumSerie = ?');
    $update->execute([$target_state, $numserie]);
    log_debug("UPDATE materiel SET stock = $target_state WHERE NumSerie = $numserie");

    // Record in history
    $history = $pdo->prepare('INSERT INTO materiel_history (numserie, prev_state, new_state, date_change, user_id, notes) VALUES (?, ?, ?, NOW(), ?, ?)');
    $history->execute([
        $numserie,
        $prev_state,
        $target_state,
        $user_id,
        $notes ?: $cause
    ]);
    log_debug("History recorded for $numserie: $prev_state -> $target_state");

    echo json_encode(['success' => true, 'newState' => $target_state]);
} catch (PDOException $e) {
    log_debug('SQL Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 