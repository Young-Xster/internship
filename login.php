<?php
session_start();
require_once 'php/config.php';

function HashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

if (isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM login WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['passwordHash'])) {
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['admin'];
        header('Location: index.php');
        exit;
    } else {
        $message = "<span style='color:red'>Email ou mot de passe invalide.</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - AAF Gestion de Matériel</title>
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <style>
    body { background: #f6f8fa; }
    .aaf-branding { text-align: center; margin-top: 48px; margin-bottom: 18px; }
    .aaf-logo { width: 80px; height: 80px; border-radius: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .aaf-title { font-size: 2.1rem; font-weight: bold; margin: 18px 0 6px 0; color: #222; letter-spacing: 1px; }
    .aaf-subtitle { color: #666; font-size: 1.1rem; margin-bottom: 18px; }
    .login-card { max-width: 400px; margin: 0 auto 80px auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 40px 32px; }
    .login-card h2 { text-align: center; margin-bottom: 28px; }
    .login-card form { display: flex; flex-direction: column; gap: 20px; }
    .login-card label { font-weight: 500; margin-bottom: 4px; }
    .login-card input { padding: 12px; border-radius: 7px; border: 1px solid #ccc; font-size: 1rem; }
    .login-card button { background: #007bff; color: #fff; border: none; border-radius: 7px; padding: 14px; font-size: 1.1rem; cursor: pointer; margin-top: 10px; }
    .login-card button:hover { background: #0056b3; }
    .login-card .bottom-link { text-align: center; margin-top: 18px; }
    .login-card .error, .login-card .success { text-align: center; margin-bottom: 10px; }
  </style>
</head>
<body>
  <div class="aaf-branding">
    <img src="imgs/Logo_AAF.JPG" alt="AAF Logo" class="aaf-logo">
    <div class="aaf-title">Bienvenue sur l'Espace AAF</div>
    <div class="aaf-subtitle">Connexion au système de gestion de matériel de l'AAF</div>
  </div>
  <div class="login-card">
    <h2>Login</h2>
    <?php if ($message) echo "<div class='error'>$message</div>"; ?>
    <form action="login.php" method="post" autocomplete="off">
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required>
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Login</button>
    </form>
    <div class="bottom-link">
      Vous n'avez pas de compte ? <a href="signup.php">Inscrivez-vous</a>
    </div>
  </div>
</body>
</html>