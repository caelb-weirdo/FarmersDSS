<?php
require_once 'db.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $stmt = $pdo->prepare('SELECT id, email, full_name, role, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Farmar's DSS</title>
    <link rel="icon" type="image/png" href="logo.png" />
    <link rel="apple-touch-icon" href="logo.png" />
    <link rel="stylesheet" href="styles.css" />
  </head>
  <body>
    <div class="auth-container">
      <div class="auth-card">
        <div class="auth-header">
          <img src="logo.png" alt="Farmar's DSS Logo" class="brand-mark-auth brand-logo-auth" />
          <h1>Farmar's DSS</h1>
          <p class="auth-subtitle">Smart decisions for every harvest</p>
        </div>

        <form action="login.php" method="POST" class="auth-form">
          <div class="form-group">
            <label for="login-email">Email Address</label>
            <input id="login-email" type="email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
          </div>

          <div class="form-group">
            <label for="login-password">Password</label>
            <div class="password-input-wrap">
              <input id="login-password" type="password" name="password" placeholder="Enter your password" required />
              <button type="button" class="password-toggle" aria-label="Show password">👁️</button>
            </div>
          </div>

          <button type="submit" class="primary-button">Sign In</button>

          <?php if (!empty($error)): ?>
            <p class="auth-error" style="display:block;"><?php echo htmlspecialchars($error); ?></p>
          <?php endif; ?>
        </form>

        <div class="auth-footer">
          <p>
            Don't have an account?
            <a href="register.php">Create one</a>
          </p>
        </div>

        <div class="demo-info">
          <p><strong>Demo Credentials:</strong></p>
          <p>Email: admin@gmail.com or farmer@farmer-dss.com</p>
          <p>Password: admin@123 (Admin) or password123 (Farmer)</p>
        </div>
      </div>
    </div>
  </body>
</html>