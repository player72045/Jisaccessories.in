<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/pages/myaccount.php');
    exit;
}

$pageTitle = 'Login';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $redirect = $_SESSION['redirect_after_login'] ?? (BASE_PATH . '/pages/myaccount.php');
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = 'Incorrect email or password.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Welcome Back 👋</h1>
    <p class="subtitle">Log in to your jisaccessories account</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error"><?php foreach ($errors as $err) echo '<div>' . e($err) . '</div>'; ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_PATH ?>/pages/login.php">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">🔒 Log In</button>
    </form>

    <div class="auth-footer-link">Don't have an account? <a href="<?= BASE_PATH ?>/pages/register.php">Create one →</a></div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
