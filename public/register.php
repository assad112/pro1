<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? ROLE_CLIENT);

    if ($name === '' || $email === '' || $password === '') {
        $error = t('auth.all_required');
    } elseif (!in_array($role, [ROLE_CLIENT, ROLE_PROVIDER], true)) {
        $error = t('auth.invalid_role');
    } elseif (find_user_by_email($email)) {
        $error = t('auth.email_exists');
    } else {
        if (create_user($name, $email, $password, $role)) {
            $ok = t('auth.created_success');
        } else {
            $error = t('auth.created_failed');
        }
    }
}

require __DIR__ . '/_header.php';
?>
<section class="card auth-card">
  <h1><?= e(t('auth.register_title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok !== ''): ?><p class="alert success"><?= e($ok) ?></p><?php endif; ?>
  <form method="post" class="form">
    <label><?= e(t('auth.name')) ?></label>
    <input type="text" name="name" required>
    <label><?= e(t('auth.email')) ?></label>
    <input type="email" name="email" required>
    <label><?= e(t('auth.password')) ?></label>
    <input type="password" name="password" required>
    <label><?= e(t('auth.account_type')) ?></label>
    <select name="role" required>
      <option value="client"><?= e(t('role.client')) ?></option>
      <option value="provider"><?= e(t('role.provider')) ?></option>
    </select>
    <button type="submit"><?= e(t('nav.register')) ?></button>
  </form>
  <p><a href="/login.php"><?= e(t('auth.have_account')) ?></a></p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
