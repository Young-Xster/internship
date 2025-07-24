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
$email = $_SESSION['user_email'];
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
        .user-card .email { color: #555; margin-bottom: 28px; }
        .user-card button { background: #e74c3c; color: #fff; border: none; border-radius: 7px; padding: 13px 32px; font-size: 1.1rem; cursor: pointer; transition: background 0.18s; }
        .user-card button:hover { background: #c0392b; }
    </style>
</head>
<body>
    <div class="user-card">
        <h2>User Profile</h2>
        <div class="email">Logged in as: <strong><?= htmlspecialchars($email) ?></strong></div>
        <form method="POST">
            <button type="submit" name="logout">Sign Out</button>
        </form>
    </div>
</body>
</html> 