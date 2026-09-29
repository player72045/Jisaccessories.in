<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/pages/myaccount.php');
    exit;
}

$pageTitle = 'Create Account';
$errors = [];
$vals = ['first_name' => '', 'last_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($vals as $k => $v) $vals[$k] = trim($_POST[$k] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($vals['first_name'] === '' || $vals['last_name'] === '') $errors[] = 'First and last name are required.';
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $vals['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'member')");
        $stmt->bind_param('ssss', $vals['first_name'], $vals['last_name'], $vals['email'], $hash);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'member';
        header('Location: ' . BASE_PATH . '/pages/myaccount.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Create Account 🚀</h1>
    <p class="subtitle">Join jisaccessories and start shopping</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error"><?php foreach ($errors as $err) echo '<div>' . e($err) . '</div>'; ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_PATH ?>/pages/register.php">
      <div class="form-grid">
        <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= e($vals['first_name']) ?>" required></div>
        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= e($vals['last_name']) ?>" required></div>
      </div>
      <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?= e($vals['email']) ?>" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" minlength="6" required></div>
      <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" minlength="6" required></div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
    </form>

    <div class="auth-footer-link">Already have an account? <a href="<?= BASE_PATH ?>/pages/login.php">Log in →</a></div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
