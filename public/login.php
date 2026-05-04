<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user = find_user_by_email($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = t('auth.invalid_login');
    } else {
        login_user($user);
        header('Location: /dashboard.php');
        exit;
    }
}

require __DIR__ . '/_header.php';
?>
<section class="card auth-card">
  <h1><?= e(t('auth.login_title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="form">
    <label><?= e(t('auth.email')) ?></label>
    <input type="email" name="email" required>
    <label><?= e(t('auth.password')) ?></label>
    <input type="password" name="password" required>
    <button type="submit"><?= e(t('nav.login')) ?></button>
  </form>
  <p><a href="/register.php"><?= e(t('auth.create_account')) ?></a></p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
