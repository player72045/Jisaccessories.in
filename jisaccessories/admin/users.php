<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/currency.php';

requireAdmin();
if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
    header('Location: ' . BASE_PATH . '/admin/index.php');
    exit;
}
$pageTitle = 'Users';
$activeNav = 'users';
$successMsg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_role') {
    $id = (int) ($_POST['id'] ?? 0);
    $role = $_POST['role'] ?? '';
    if ($id === (int) $_SESSION['user_id']) {
        $errors[] = "You can't change your own role.";
    } elseif (in_array($role, ['member', 'admin', 'super_admin'], true)) {
        $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->bind_param('si', $role, $id);
        $stmt->execute();
        $stmt->close();
        $successMsg = 'Role updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_user') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
        $errors[] = "You can't delete your own account.";
    } else {
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $successMsg = 'User deleted.';
    }
}

$search = trim($_GET['search'] ?? '');
$where = '1=1'; $types = ''; $params = [];
if ($search !== '') { $where = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)'; $types = 'sss'; $like = '%' . $search . '%'; $params = [$like, $like, $like]; }
$stmt = $conn->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count FROM users u WHERE $where ORDER BY u.created_at DESC");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/layout.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?= e($successMsg) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $err) echo '<div>' . e($err) . '</div>'; ?></div><?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h3>All Users (<?= count($users) ?>)</h3>
    <form method="GET" style="display:flex;gap:8px;">
      <input type="text" name="search" placeholder="Search name or email…" value="<?= e($search) ?>" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;">
      <button class="btn btn-outline btn-sm">Search</button>
    </form>
  </div>
  <div class="table-scroll">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Joined</th><th>Role</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= (int)$u['order_count'] ?></td>
          <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
              <span class="role-badge <?= e($u['role']) ?>"><?= e($u['role']) ?></span>
            <?php else: ?>
            <form method="POST">
              <input type="hidden" name="form" value="update_role">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <select name="role" class="status-select" data-autosubmit>
                <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
              </select>
            </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <form method="POST" data-confirm="Delete this user permanently?">
              <input type="hidden" name="form" value="delete_user">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="icon-btn danger" title="Delete">🗑️</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
