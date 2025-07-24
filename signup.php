<?php
session_start();
if (isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}
require_once 'php/config.php';
function HashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 8) {
        $message = "<span style='color:red'>Le mot de passe doit contenir au moins 8 caractères.</span>";
    } elseif ($password !== $confirm) {
        $message = "<span style='color:red'>Les mots de passe ne correspondent pas.</span>";
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM login WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $message = "<span style='color:red'>Email déjà enregistré.</span>";
        } else {
            $hash = HashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO login (email, passwordHash, admin) VALUES (?, ?, 0)");
            $stmt->execute([$email, $hash]);
            $message = "<span style='color:green'>Inscription réussie ! <a href='login.php'>Se connecter</a></span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - AAF Gestion de Matériel</title>
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <style>
    body { background: #f6f8fa; }
    .aaf-branding { text-align: center; margin-top: 48px; margin-bottom: 18px; }
    .aaf-logo { width: 80px; height: 80px; border-radius: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .aaf-title { font-size: 2.1rem; font-weight: bold; margin: 18px 0 6px 0; color: #222; letter-spacing: 1px; }
    .aaf-subtitle { color: #666; font-size: 1.1rem; margin-bottom: 18px; }
    .signup-card { max-width: 400px; margin: 0 auto 80px auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 40px 32px; }
    .signup-card h2 { text-align: center; margin-bottom: 28px; }
    .signup-card form { display: flex; flex-direction: column; gap: 20px; }
    .signup-card label { font-weight: 500; margin-bottom: 4px; }
    .signup-card input { padding: 12px; border-radius: 7px; border: 1px solid #ccc; font-size: 1rem; }
    .signup-card button { background: #4CAF50; color: #fff; border: none; border-radius: 7px; padding: 14px; font-size: 1.1rem; cursor: pointer; margin-top: 10px; }
    .signup-card button:hover { background: #6fdc7a; }
    .signup-card .bottom-link { text-align: center; margin-top: 18px; }
    .signup-card .error, .signup-card .success { text-align: center; margin-bottom: 10px; }
  </style>
</head>
<body>
  <div class="aaf-branding">
    <img src="imgs/Logo_AAF.JPG" alt="AAF Logo" class="aaf-logo">
    <div class="aaf-title">Inscription à l'Espace AAF</div>
    <div class="aaf-subtitle">Créer un compte pour accéder au système de gestion de matériel de l'AAF</div>
  </div>
  <div class="signup-card">
    <h2>Sign Up</h2>
    <?php if ($message) echo "<div class='error'>$message</div>"; ?>
    <form action="signup.php" method="post" autocomplete="off">
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required>
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required minlength="8" pattern=".{8,}" title="Au moins 8 caractères">
      <label for="confirm_password">Confirm Password:</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8" pattern=".{8,}" title="Au moins 8 caractères">
      <button type="submit">Sign Up</button>
    </form>
    <div class="bottom-link">
      Déjà inscrit ? <a href="login.php">Se connecter</a>
    </div>
  </div>
</body>
</html> 