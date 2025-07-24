<?php
session_start();
if (!isset($_SESSION['userName'])) {
    header('Location: login.php');
    exit;
}
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
require_once 'php/config.php';
$username = $_SESSION['userName'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Handle create user (admin only)
$success_message = '';
$error_message = '';
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $new_role = $_POST['new_role'] === 'admin' ? 1 : 0;
    if (strlen($new_username) < 3 || strlen($new_username) > 64) {
        $error_message = 'Le nom d\'utilisateur doit comporter entre 3 et 64 caractères.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Le mot de passe doit comporter au moins 8 caractères.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login WHERE userName = ?');
        $stmt->execute([$new_username]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = 'Ce nom d\'utilisateur existe déjà.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO login (userName, passwordHash, admin) VALUES (?, ?, ?)');
            $stmt->execute([$new_username, $hash, $new_role]);
            $success_message = 'Utilisateur créé avec succès!';
        }
    }
}
// Handle role change if admin
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role_user'], $_POST['new_role_select'])) {
    $target_user = $_POST['change_role_user'];
    $new_role = $_POST['new_role_select'] === 'admin' ? 1 : 0;
    if ($target_user !== $username) {
        $stmt = $pdo->prepare('UPDATE login SET admin = ? WHERE userName = ?');
        $stmt->execute([$new_role, $target_user]);
        $success_message = 'Rôle mis à jour avec succès.';
    }
}
// Handle delete user (admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user = $_POST['delete_user'];
    if ($target_user !== $username) {
        $stmt = $pdo->prepare('DELETE FROM login WHERE userName = ?');
        $stmt->execute([$target_user]);
        $success_message = 'Utilisateur supprimé avec succès.';
    }
}
// Fetch all users if admin
$users = [];
if ($is_admin) {
    $stmt = $pdo->query('SELECT userName, admin FROM login');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// Get current user's role label
$role_label = $is_admin ? 'Admin' : 'Viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #f6f8fa; }
        .user-card { max-width: 400px; margin: 80px auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 40px 32px; text-align: center; }
        .user-card h2 { margin-bottom: 18px; }
        .user-card .role-label { font-size: 1.1rem; margin-bottom: 18px; color: #555; }
        .user-card .signout-btn { background: #e74c3c; color: #fff; border: none; border-radius: 8px; padding: 16px 38px; font-size: 1.1rem; cursor: pointer; margin-top: 18px; }
        .user-card .signout-btn:hover { background: #c0392b; }
        .user-list { margin: 40px auto 0 auto; max-width: 500px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 24px; }
        .user-list h3 { margin-bottom: 18px; }
        .user-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .user-row .user-name { font-weight: 500; }
        .user-row select { padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; }
        .user-row form { display: inline; }
        .user-row .self-label { color: #888; font-size: 0.95em; margin-left: 8px; }
        .user-row .delete-btn { background: #e74c3c; color: #fff; border: none; border-radius: 6px; padding: 6px 14px; font-size: 0.98rem; cursor: pointer; margin-left: 10px; }
        .user-row .delete-btn:hover { background: #c0392b; }
        .create-user-form { margin-bottom: 32px; background: #f8f8f8; border-radius: 10px; padding: 18px 18px 10px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .create-user-form label { font-weight: 500; margin-bottom: 4px; display: block; }
        .create-user-form input, .create-user-form select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; margin-bottom: 12px; width: 100%; }
        .create-user-form button { background: #007bff; color: #fff; border: none; border-radius: 7px; padding: 10px 0; font-size: 1.05rem; cursor: pointer; width: 100%; }
        .create-user-form button:hover { background: #0056b3; }
        .msg-success { color: #27ae60; font-weight: bold; margin-bottom: 16px; }
        .msg-error { color: #e74c3c; font-weight: bold; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="user-card">
        <h2>User Profile</h2>
        <div>Logged in as: <b><?= htmlspecialchars($username) ?></b></div>
        <div class="role-label">Role: <b><?= $role_label ?></b></div>
        <form method="POST">
            <button type="submit" name="logout" class="signout-btn">Sign Out</button>
        </form>
    </div>
    <?php if ($is_admin): ?>
    <div class="user-list">
        <h3>Manage Users</h3>
        <?php if ($success_message): ?>
            <div class="msg-success"> <?= $success_message ?> </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="msg-error"> <?= $error_message ?> </div>
        <?php endif; ?>
        <form method="POST" class="create-user-form" autocomplete="off">
            <input type="hidden" name="create_user" value="1">
            <label for="new_username">Nom d'utilisateur :</label>
            <input type="text" id="new_username" name="new_username" required minlength="3" maxlength="64">
            <label for="new_password">Mot de passe :</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
            <label for="new_role">Rôle :</label>
            <select id="new_role" name="new_role">
                <option value="viewer">Viewer</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit">Créer le compte</button>
        </form>
        <?php foreach ($users as $user): ?>
            <div class="user-row">
                <span class="user-name"><?= htmlspecialchars($user['userName']) ?></span>
                <?php if ($user['userName'] === $username): ?>
                    <span class="self-label">(You)</span>
                    <span style="margin-left:12px; font-weight:bold; color:#555;">Admin</span>
                <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="change_role_user" value="<?= htmlspecialchars($user['userName']) ?>">
                        <select name="new_role_select" onchange="this.form.submit()">
                            <option value="viewer" <?= !$user['admin'] ? 'selected' : '' ?>>Viewer</option>
                            <option value="admin" <?= $user['admin'] ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_user" value="<?= htmlspecialchars($user['userName']) ?>">
                        <button type="submit" class="delete-btn" onclick="return confirm('Supprimer cet utilisateur ?');">Supprimer</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</body>
</html> 