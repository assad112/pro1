<?php
$user = current_user();
$lang = current_lang();
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e(page_dir()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(t('site.title')) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
  <header class="topbar">
    <div class="container topbar-inner">
      <a class="brand" href="/dashboard.php">PMMS</a>
      <nav class="nav">
        <?php if ($user): ?>
          <?php if ($user['role'] !== ROLE_ADMIN): ?>
            <a href="/requests.php"><i class="bi bi-list-check"></i><span><?= e(t('nav.requests')) ?></span></a>
            <a href="/bids.php"><i class="bi bi-cash-coin"></i><span><?= e(t('nav.bids')) ?></span></a>
            <a href="/messages.php"><i class="bi bi-chat-dots"></i><span><?= e(t('nav.messages')) ?></span></a>
            <a href="/reviews.php"><i class="bi bi-star"></i><span><?= e(t('nav.reviews')) ?></span></a>
          <?php endif; ?>
          <?php if ($user['role'] === ROLE_PROVIDER): ?>
            <a href="/marketing.php"><i class="bi bi-megaphone"></i><span><?= e(t('nav.marketing')) ?></span></a>
          <?php endif; ?>
          <?php if ($user['role'] === ROLE_ADMIN): ?>
            <a href="/dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <a href="/admin.php#customers"><i class="bi bi-people-fill"></i><span>Customers</span></a>
            <a href="/admin_promotions.php"><i class="bi bi-gift-fill"></i><span>Promotions</span></a>
            <a href="/admin.php#transactions"><i class="bi bi-cash-stack"></i><span>Transactions</span></a>
            <a href="/admin_analytics.php"><i class="bi bi-bar-chart-fill"></i><span>Reports</span></a>
          <?php endif; ?>
          <a href="/logout.php"><i class="bi bi-box-arrow-right"></i><span><?= e(t('nav.logout')) ?></span></a>
        <?php else: ?>
          <a href="/login.php"><i class="bi bi-box-arrow-in-left"></i><span><?= e(t('nav.login')) ?></span></a>
          <a href="/register.php"><i class="bi bi-person-plus"></i><span><?= e(t('nav.register')) ?></span></a>
        <?php endif; ?>
        <?php if ($lang === 'ar'): ?>
          <a href="<?= e(switch_lang_url('en')) ?>"><i class="bi bi-translate"></i><span><?= e(t('lang.en')) ?></span></a>
        <?php else: ?>
          <a href="<?= e(switch_lang_url('ar')) ?>"><i class="bi bi-translate"></i><span><?= e(t('lang.ar')) ?></span></a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="container main">
