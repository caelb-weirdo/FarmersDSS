<?php
require_once 'db.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['passwordConfirm'] ?? '';
    
    if (empty($fullName) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $passwordConfirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare('INSERT INTO users (email, full_name, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$email, $fullName, $passwordHash]);
            
            // Log the user in
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = 'Farmer'; // Default role
            
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "Email already registered.";
            } else {
                $error = "Database error during registration.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register - Farmar's DSS</title>
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

        <form action="register.php" method="POST" class="auth-form">
          <div class="form-group">
            <label for="register-fullname">Full Name</label>
            <input id="register-fullname" type="text" name="fullName" placeholder="Your full name" required value="<?php echo htmlspecialchars($_POST['fullName'] ?? ''); ?>" />
          </div>

          <div class="form-group">
            <label for="register-email">Email Address</label>
            <input id="register-email" type="email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
          </div>

          <div class="form-group">
            <label for="register-password">Password</label>
            <div class="password-input-wrap">
              <input id="register-password" type="password" name="password" placeholder="Create a password" required />
              <button type="button" class="password-toggle" aria-label="Show password">👁️</button>
            </div>
          </div>

          <div class="form-group">
            <label for="register-password-confirm">Confirm Password</label>
            <div class="password-input-wrap">
              <input id="register-password-confirm" type="password" name="passwordConfirm" placeholder="Confirm your password" required />
              <button type="button" class="password-toggle" aria-label="Show password">👁️</button>
            </div>
          </div>

          <button type="submit" class="primary-button">Create Account</button>

          <?php if (!empty($error)): ?>
            <p class="auth-error" style="display:block;"><?php echo htmlspecialchars($error); ?></p>
          <?php endif; ?>
        </form>

        <div class="auth-footer">
          <p>
            Already have an account?
            <a href="login.php">Sign in</a>
          </p>
        </div>
      </div>
    </div>
  </body>
</html>
