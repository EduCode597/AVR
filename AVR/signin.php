<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) {
  $redir = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
  $safe = ($redir && (str_starts_with($redir, BASE_URL . '/') || str_starts_with($redir, '/')));
  $dest = $safe ? $redir : ((current_user()['role'] ?? '') === 'admin' ? (BASE_URL . '/admin/index.php') : (BASE_URL . '/index.php'));
  header('Location: ' . $dest); exit;
}
$page_title = 'Sign in';
$error = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
    $redir = isset($_GET['redirect']) ? (string)$_GET['redirect'] : (isset($_POST['redirect']) ? (string)$_POST['redirect'] : '');
    $safe = ($redir && (str_starts_with($redir, BASE_URL . '/') || str_starts_with($redir, '/')));
    $dest = $safe ? $redir : ((current_user()['role'] ?? '') === 'admin' ? (BASE_URL . '/admin/index.php') : (BASE_URL . '/index.php'));
    header('Location: ' . $dest); exit;
  } else {
    $_SESSION['flash_err'] = 'Invalid credentials';
    $qp = isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '';
    header('Location: ' . BASE_URL . '/signin.php' . $qp); exit;
  }
}
include __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap" style="padding:40px 0">
  <div class="container" style="max-width:1080px">
  <div class="auth-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:18px;overflow:hidden;box-shadow:0 12px 28px -8px rgba(0,0,0,.25)">
      <div style="background:linear-gradient(135deg,#0a4d8f,#0e72d1);color:#eaf6ff;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;position:relative">
        <!-- Logo removed -->
        <div style="font-size:14px;letter-spacing:.5px;font-weight:600;opacity:.85;text-align:center">WELCOME BACK</div>
  <h2 style="margin:8px 0 12px;font-size:30px;line-height:1.08;color:#fff;text-align:center;letter-spacing:.5px;font-family:'Segoe UI',Arial,sans-serif;font-weight:700;">Nice to see you again</h2>
        <p style="margin:0 0 18px;max-width:380px;line-height:1.5;color:#dcefff;font-size:14px;text-align:center;margin-left:auto;margin-right:auto">Access your account, track orders and manage services easily.</p>
        <!-- Slogan container removed -->
      </div>
      <div style="background:#fff;padding:56px 50px;display:flex;flex-direction:column;justify-content:center">
        <h3 style="margin:0 0 22px;color:#0f172a;font-size:26px">Login Account</h3>
        <?php if ($error): ?><div class="form" style="background:#fee2e2;border-color:#fecaca;margin:0 0 14px;"><?= h($error); ?></div><?php endif; ?>
        <form method="post" style="display:flex;flex-direction:column;gap:18px">
          <?php if(isset($_GET['redirect'])): ?><input type="hidden" name="redirect" value="<?= h((string)$_GET['redirect']); ?>"><?php endif; ?>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Username or Email
            <input name="username" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Password
            <input type="password" name="password" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;color:#475569">
            <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" style="width:16px;height:16px"> <span>Keep me signed in</span></label>
            <a href="#" style="text-decoration:none;color:#0e72d1;font-weight:600">Forgot?</a>
          </div>
          <button class="btn" style="width:100%;font-size:15px;padding:14px 0;border-radius:10px;letter-spacing:.5px">Sign In</button>
          <div style="text-align:center;font-size:14px;color:#475569">No account? <a href="<?= BASE_URL ?>/register.php" style="color:#0e72d1;font-weight:600;text-decoration:none">Register</a></div>
        </form>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
