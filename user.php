<?php
session_start();
if (!isset($_SESSION['user_email'])) {
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
$email = $_SESSION['user_email'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Handle role change if admin
$success_message = '';
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role_email'], $_POST['new_role'])) {
    $target_email = $_POST['change_role_email'];
    $new_role = $_POST['new_role'] === 'admin' ? 1 : 0;
    if ($target_email !== $email) { // Prevent self-demotion
        $stmt = $pdo->prepare("UPDATE login SET admin = ? WHERE email = ?");
        $stmt->execute([$new_role, $target_email]);
        $success_message = 'Role updated successfully.';
    }
}

// Fetch all users if admin
$users = [];
if ($is_admin) {
    $stmt = $pdo->query("SELECT email, admin FROM login");
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
        .user-row .user-email { font-weight: 500; }
        .user-row select { padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; }
        .user-row form { display: inline; }
        .user-row .self-label { color: #888; font-size: 0.95em; margin-left: 8px; }
    </style>
</head>
<body>
    <div class="user-card">
        <h2>User Profile</h2>
        <div>Logged in as: <b><?= htmlspecialchars($email) ?></b></div>
        <div class="role-label">Role: <b><?= $role_label ?></b></div>
        <form method="POST">
            <button type="submit" name="logout" class="signout-btn">Sign Out</button>
        </form>
    </div>
    <?php if ($is_admin): ?>
    <div class="user-list">
        <h3>Manage Users</h3>
        <?php if ($success_message): ?>
            <div style="color: #27ae60; font-weight: bold; margin-bottom: 16px;"> <?= $success_message ?> </div>
        <?php endif; ?>
        <?php foreach ($users as $user): ?>
            <div class="user-row">
                <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
                <?php if ($user['email'] === $email): ?>
                    <span class="self-label">(You)</span>
                    <span style="margin-left:12px; font-weight:bold; color:#555;">Admin</span>
                <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="change_role_email" value="<?= htmlspecialchars($user['email']) ?>">
                        <select name="new_role" onchange="this.form.submit()">
                            <option value="viewer" <?= !$user['admin'] ? 'selected' : '' ?>>Viewer</option>
                            <option value="admin" <?= $user['admin'] ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</body>
</html> 