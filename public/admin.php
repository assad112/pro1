<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_role([ROLE_ADMIN]);

$counts = admin_counts();
$users = admin_latest_users();
$requests = admin_latest_requests();
$bids = admin_latest_bids();
$transactions = admin_latest_transactions();

function admin_status_badge(string $status): string
{
    return '<span class="badge status-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

require __DIR__ . '/_header.php';
?>
<section class="admin-page">
  <div class="admin-hero card">
    <div>
      <p class="eyebrow">PMMS</p>
      <h1><?= e(t('admin.title')) ?></h1>
      <p><?= e(t('admin.overview')) ?></p>
    </div>
    <a class="admin-action" href="/dashboard.php"><?= e(t('dashboard.admin_panel')) ?></a>
  </div>

  <div class="stats admin-stats">
    <div class="stat"><i class="bi bi-people"></i><span><?= e(t('admin.users')) ?></span><strong><?= (int) $counts['users'] ?></strong></div>
    <div class="stat"><i class="bi bi-list-check"></i><span><?= e(t('admin.requests')) ?></span><strong><?= (int) $counts['requests'] ?></strong></div>
    <div class="stat"><i class="bi bi-cash-coin"></i><span><?= e(t('admin.bids')) ?></span><strong><?= (int) $counts['bids'] ?></strong></div>
    <div class="stat"><i class="bi bi-receipt"></i><span><?= e(t('admin.transactions')) ?></span><strong><?= (int) $counts['transactions'] ?></strong></div>
  </div>

  <div class="admin-grid">
    <section class="card admin-panel">
      <h2><?= e(t('admin.latest_users')) ?></h2>
      <div class="table-wrap">
        <table>
          <tr>
            <th>#</th>
            <th><?= e(t('auth.name')) ?></th>
            <th><?= e(t('auth.email')) ?></th>
            <th><?= e(t('common.role')) ?></th>
          </tr>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int) $u['id'] ?></td>
              <td><?= e($u['name']) ?></td>
              <td><?= e($u['email']) ?></td>
              <td><span class="badge role-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php if (count($users) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
    </section>

    <section class="card admin-panel">
      <h2><?= e(t('admin.latest_requests')) ?></h2>
      <div class="table-wrap">
        <table>
          <tr>
            <th>#</th>
            <th><?= e(t('requests.request_title')) ?></th>
            <th><?= e(t('requests.client')) ?></th>
            <th><?= e(t('bids.status')) ?></th>
            <th><?= e(t('requests.budget')) ?></th>
          </tr>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?= (int) $r['id'] ?></td>
              <td><?= e($r['title']) ?></td>
              <td><?= e($r['client_name']) ?></td>
              <td><?= admin_status_badge($r['status']) ?></td>
              <td><?= e(number_format((float) $r['budget'], 2)) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php if (count($requests) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
    </section>
  </div>

  <section class="card admin-panel">
    <h2><?= e(t('admin.latest_bids')) ?></h2>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th><?= e(t('messages.request')) ?></th>
          <th><?= e(t('requests.client')) ?></th>
          <th><?= e(t('bids.provider')) ?></th>
          <th><?= e(t('bids.price')) ?></th>
          <th><?= e(t('bids.duration_days')) ?></th>
          <th><?= e(t('bids.status')) ?></th>
        </tr>
        <?php foreach ($bids as $b): ?>
          <tr>
            <td><?= (int) $b['id'] ?></td>
            <td><?= e($b['title']) ?></td>
            <td><?= e($b['client_name']) ?></td>
            <td><?= e($b['provider_name']) ?></td>
            <td><?= e(number_format((float) $b['price'], 2)) ?></td>
            <td><?= (int) $b['duration_days'] ?> <?= e(t('bids.days')) ?></td>
            <td><?= admin_status_badge($b['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($bids) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>

  <section class="card admin-panel">
    <h2><?= e(t('admin.latest_transactions')) ?></h2>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th><?= e(t('messages.request')) ?></th>
          <th><?= e(t('admin.before_discount')) ?></th>
          <th><?= e(t('admin.discount')) ?></th>
          <th><?= e(t('admin.after_discount')) ?></th>
        </tr>
        <?php foreach ($transactions as $t): ?>
          <?php $discountPercent = (float) $t['original_price'] > 0 ? round(((float) $t['discount_amount'] / (float) $t['original_price']) * 100, 1) : 0; ?>
          <tr>
            <td><?= (int) $t['id'] ?></td>
            <td><?= e($t['title']) ?></td>
            <td><?= e(number_format((float) $t['original_price'], 2)) ?></td>
            <td><?= e(number_format((float) $t['discount_amount'], 2)) ?> <span class="muted">(<?= e((string) $discountPercent) ?>%)</span></td>
            <td><strong><?= e(number_format((float) $t['final_price'], 2)) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($transactions) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
