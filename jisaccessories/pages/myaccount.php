<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireLogin();
$pageTitle = 'My Account';
$user = getCurrentUser();
$tab = $_GET['tab'] ?? 'dashboard';
$successMsg = '';
$errors = [];

// ── Handle profile update ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    } else {
        // Optional avatar upload
        $avatarFile = $user['avatar'];
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['avatar']['size'] <= 2 * 1024 * 1024) {
                $newName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
                $destDir = __DIR__ . '/../assets/uploads/avatars/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destDir . $newName)) {
                    $avatarFile = $newName;
                }
            } else {
                $errors[] = 'Avatar must be a JPG/PNG/WEBP under 2MB.';
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, address=?, city=?, avatar=? WHERE id=?');
            $stmt->bind_param('ssssssi', $firstName, $lastName, $phone, $address, $city, $avatarFile, $user['id']);
            $stmt->execute();
            $stmt->close();
            $user = getCurrentUser();
            $successMsg = 'Profile updated successfully.';
        }
    }
    $tab = 'profile';
}

// ── Handle password change ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hash, $user['id']);
        $stmt->execute();
        $stmt->close();
        $successMsg = 'Password updated successfully.';
    }
    $tab = 'password';
}

// ── Fetch order stats & lists ────────────────────────────
$stmt = $conn->prepare('SELECT COUNT(*) total, SUM(CASE WHEN status="delivered" THEN 1 ELSE 0 END) delivered, SUM(total_amount) spent FROM orders WHERE user_id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$recentLimit = $tab === 'orders' ? 100 : 5;
$stmt = $conn->prepare("SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                         FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC LIMIT ?");
$stmt->bind_param('ii', $user['id'], $recentLimit);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <div class="breadcrumb"><a href="<?= BASE_PATH ?>/index.php">Home</a><span class="sep">›</span><span class="current">My Account</span></div>
</div>

<div class="container account-layout">
  <aside class="account-sidebar">
    <div class="account-profile-card">
      <div class="avatar-circle">
        <?php if ($user['avatar']): ?>
          <img src="<?= BASE_PATH ?>/assets/uploads/avatars/<?= e($user['avatar']) ?>" alt="Avatar">
        <?php else: ?>
          <?= e(initials($user['first_name'], $user['last_name'])) ?>
        <?php endif; ?>
      </div>
      <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
      <span><?= e($user['email']) ?></span>
    </div>
    <nav class="account-nav">
      <a href="?tab=dashboard" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
      <a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">🛍 My Orders</a>
      <a href="?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">👤 Profile</a>
      <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">🔒 Change Password</a>
      <a href="<?= BASE_PATH ?>/pages/logout.php" class="logout">🚪 Logout</a>
      <?php if (isAdmin()): ?><a href="<?= BASE_PATH ?>/admin/index.php">🛠 Admin Panel</a><?php endif; ?>
    </nav>
  </aside>

  <div class="account-content">
    <?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $err) echo '<div>' . e($err) . '</div>'; ?></div><?php endif; ?>

    <?php if ($tab === 'dashboard'): ?>
      <h2>Welcome back, <?= e($user['first_name']) ?>! 👋</h2>
      <p>Here's a quick look at your account.</p>
      <div class="stat-row">
        <div class="stat-card"><span>Total Orders</span><strong><?= (int)($stats['total'] ?? 0) ?></strong></div>
        <div class="stat-card"><span>Delivered</span><strong><?= (int)($stats['delivered'] ?? 0) ?></strong></div>
        <div class="stat-card"><span>Total Spent</span><strong><?= formatPrice((float)($stats['spent'] ?? 0)) ?></strong></div>
      </div>
      <h3 style="font-size:16px;margin-bottom:14px;">Recent Orders</h3>
      <?php require __DIR__ . '/_orders_table.php'; ?>

    <?php elseif ($tab === 'orders'): ?>
      <h2>My Orders</h2>
      <p><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> total</p>
      <?php require __DIR__ . '/_orders_table.php'; ?>

    <?php elseif ($tab === 'profile'): ?>
      <h2>Profile</h2>
      <p>Update your personal information.</p>
      <form method="POST" action="?tab=profile" enctype="multipart/form-data" style="max-width:560px;">
        <input type="hidden" name="form" value="profile">
        <div class="form-group"><label>Profile Picture</label><input type="file" name="avatar" accept="image/*"></div>
        <div class="form-grid">
          <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= e($user['first_name']) ?>" required></div>
          <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= e($user['last_name']) ?>" required></div>
        </div>
        <div class="form-group"><label>Email <span style="font-weight:400;">(cannot be changed)</span></label><input type="email" value="<?= e($user['email']) ?>" disabled></div>
        <div class="form-group"><label>Phone Number</label><input type="text" name="phone" value="<?= e($user['phone']) ?>"></div>
        <div class="form-group"><label>Address</label><textarea name="address" rows="3"><?= e($user['address']) ?></textarea></div>
        <div class="form-group"><label>City</label><input type="text" name="city" value="<?= e($user['city']) ?>"></div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>

    <?php elseif ($tab === 'password'): ?>
      <h2>Change Password</h2>
      <p>Choose a strong password you don't use elsewhere.</p>
      <form method="POST" action="?tab=password" style="max-width:420px;">
        <input type="hidden" name="form" value="password">
        <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="form-group"><label>New Password</label><input type="password" name="new_password" minlength="6" required></div>
        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" minlength="6" required></div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
