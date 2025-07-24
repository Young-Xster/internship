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

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM login WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['passwordHash'])) {
    // Login success
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_admin'] = $user['admin'];
    header('Location: index.php');
    exit;
} else {
    // Login failed
    echo "Invalid email or password";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Page</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { background: #f6f8fa; }
    .login-card { max-width: 400px; margin: 80px auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 40px 32px; }
    .login-card h2 { text-align: center; margin-bottom: 28px; }
    .login-card form { display: flex; flex-direction: column; gap: 20px; }
    .login-card label { font-weight: 500; margin-bottom: 4px; }
    .login-card input { padding: 12px; border-radius: 7px; border: 1px solid #ccc; font-size: 1.1rem; }
    .login-card button { background: #007bff; color: #fff; border: none; border-radius: 7px; padding: 13px; font-size: 1.1rem; cursor: pointer; transition: background 0.18s; }
    .login-card button:hover { background: #0056b3; }
    .login-card .register-link { text-align: center; margin-top: 20px; }
  </style>
</head>
<body>
  <div class="login-card">
    <h2>Login</h2>
    <form action="login.php" method="post">
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required>
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Login</button>
    </form>
    <div class="register-link">
      Don't have an account? <a href="signup.php">Sign up</a>
    </div>
  </div>
</body>
</html>