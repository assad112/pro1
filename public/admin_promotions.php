<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_role([ROLE_ADMIN]);

$promotions = admin_latest_promotions();

require __DIR__ . '/_header.php';
?>
<section class="admin-page">
  <div class="admin-hero card">
    <div>
      <p class="eyebrow">PMMS</p>
      <h1>Promotions</h1>
      <p>Latest provider promotions and discount campaigns.</p>
    </div>
    <a class="admin-action" href="/admin.php">Dashboard</a>
  </div>

  <section class="card admin-panel">
    <h2>Promotions</h2>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Provider</th>
          <th>Type</th>
          <th>Value</th>
          <th>Period</th>
          <th>Status</th>
        </tr>
        <?php foreach ($promotions as $promotion): ?>
          <tr>
            <td><?= (int) $promotion['id'] ?></td>
            <td><?= e($promotion['title']) ?></td>
            <td><?= e($promotion['provider_name']) ?></td>
            <td><?= e($promotion['discount_type']) ?></td>
            <td><?= e((string) $promotion['discount_value']) ?><?= $promotion['discount_type'] === 'percent' ? '%' : '' ?></td>
            <td><?= e($promotion['start_date']) ?> - <?= e($promotion['end_date']) ?></td>
            <td>
              <span class="badge <?= (int) $promotion['is_active'] === 1 ? 'status-completed' : 'status-cancelled' ?>">
                <?= (int) $promotion['is_active'] === 1 ? 'Active' : 'Stopped' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($promotions) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
