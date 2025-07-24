<?php
require_once 'php/config.php';
function HashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    // Check if email exists
    $stmt = $pdo->prepare("SELECT 1 FROM login WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $message = "<span style='color:red'>Email already registered.</span>";
    } else {
        $hash = HashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO login (email, passwordHash, admin) VALUES (?, ?, 0)");
        $stmt->execute([$email, $hash]);
        $message = "<span style='color:green'>Registration successful. <a href='login.php'>Login here</a>.</span>";
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
    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Register</button>
    </form>
    <div class="login-link">
        Already have an account? <a href="login.php">Login</a>
    </div>
</div>
</body>
</html> 