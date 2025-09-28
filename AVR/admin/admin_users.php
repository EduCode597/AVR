<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id && ($_POST['action'] ?? '') === 'delete') {
    // Null out user reference in orders then delete (never touch admin accounts)
    $pdo = db();
    $pdo->prepare("UPDATE orders SET user_id = NULL WHERE user_id = :id")->execute([':id'=>$id]);
    $pdo->prepare("DELETE FROM users WHERE id = :id AND (role IS NULL OR role <> 'admin') LIMIT 1")->execute([':id'=>$id]);
    $_SESSION['flash_msg'] = 'User deleted';
    header('Location: ' . BASE_URL . '/admin/admin_users.php');
    exit;
  }
}
// Exclude any admin accounts from listing (dashboard requirement)
$users = db()->query("SELECT id,name,email,username,phone,location,role,status,created_at FROM users WHERE (role IS NULL OR role <> 'admin') ORDER BY created_at DESC")->fetchAll();
admin_layout_start('Users / Customers');
?>
  <?php if ($msg): ?><div class="card"><?= h($msg); ?></div><?php endif; ?>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td data-label="ID"><?= (int)$u['id']; ?></td>
      <td data-label="Name"><?= h($u['name']); ?></td>
      <td data-label="Email"><?= h($u['email']); ?></td>
      <td data-label="Username"><?= h($u['username']); ?></td>
      <td data-label="Role"><?= h($u['role'] ?? 'customer'); ?></td>
      <td data-label="Status"><?= h($u['status']); ?></td>
      <td data-label="Action">
        <form method="post" onsubmit="return confirm('Delete this user account? This cannot be undone.');" style="margin:0">
          <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
          <input type="hidden" name="action" value="delete">
          <button class="btn secondary" style="background:#b91c1c">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php admin_layout_end(); ?>
