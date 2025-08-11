<?php
require_once 'php/config.php';
function HashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (strlen($username) < 3 || strlen($username) > 64) {
        $message = "<span style='color:red'>Le nom d'utilisateur doit comporter entre 3 et 64 caractères.</span>";
    } elseif (strlen($password) < 8) {
        $message = "<span style='color:red'>Le mot de passe doit comporter au moins 8 caractères.</span>";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT 1 FROM login WHERE userName = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = "<span style='color:red'>Ce nom d'utilisateur existe déjà.</span>";
        } else {
            // If there is no admin yet, the first registered user becomes admin
            $hasAdmin = (int)$pdo->query("SELECT COUNT(*) FROM login WHERE admin = 1")->fetchColumn() > 0;
            $adminFlag = $hasAdmin ? 0 : 1;
            $hash = HashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO login (userName, passwordHash, admin) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $adminFlag]);
            $roleMsg = $adminFlag ? " (compte admin)" : "";
            $message = "<span style='color:green'>Inscription réussie$roleMsg. <a href='login.php'>Se connecter</a>.</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 32px; }
        .register-container h2 { text-align: center; margin-bottom: 24px; }
        .register-container form { display: flex; flex-direction: column; gap: 18px; }
        .register-container input { padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; }
        .register-container button { background: #4CAF50; color: #fff; border: none; border-radius: 6px; padding: 12px; font-size: 1.1rem; cursor: pointer; transition: background 0.18s; }
        .register-container button:hover { background: #6fdc7a; }
        .register-container .login-link { text-align: center; margin-top: 18px; }
    </style>
</head>
<body>
<div class="register-container">
    <h2>Register</h2>
    <?php if ($message) echo $message; ?>
    <form method="POST" autocomplete="off">
        <input type="text" name="username" placeholder="Nom d'utilisateur" minlength="3" maxlength="64" required>
        <input type="password" name="password" placeholder="Mot de passe (min 8 caractères)" minlength="8" required>
        <button type="submit">Register</button>
    </form>
    <div class="login-link">
        Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
    </div>
</div>
</body>
</html>