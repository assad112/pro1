<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_login();
$user = current_user();

require __DIR__ . '/_header.php';
?>
<section class="card">
  <h1><?= e(t('dashboard.welcome', ['name' => $user['name']])) ?></h1>
  <p><?= e(t('dashboard.role', ['role' => role_label($user['role'])])) ?></p>
  <div class="grid">
    <?php if ($user['role'] !== ROLE_ADMIN): ?>
      <a class="tile" href="/requests.php"><?= e(t('dashboard.manage_requests')) ?></a>
      <a class="tile" href="/bids.php"><?= e(t('nav.bids')) ?></a>
      <a class="tile" href="/messages.php"><?= e(t('nav.messages')) ?></a>
      <a class="tile" href="/reviews.php"><?= e(t('nav.reviews')) ?></a>
      <?php if ($user['role'] === ROLE_PROVIDER): ?>
        <a class="tile" href="/marketing.php"><?= e(t('dashboard.marketing_discounts')) ?></a>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($user['role'] === ROLE_ADMIN): ?>
      <a class="tile" href="/admin.php"><?= e(t('dashboard.admin_panel')) ?></a>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
