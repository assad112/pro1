<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_role([ROLE_PROVIDER]);
$user = current_user();
$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_promotion') {
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['discount_type'] ?? 'percent');
        $value = (float) ($_POST['discount_value'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');

        if ($title === '' || !in_array($type, ['percent', 'fixed'], true) || $value <= 0 || $startDate === '' || $endDate === '') {
            $error = t('marketing.invalid');
        } else {
            $saved = create_promotion((int) $user['id'], $title, $type, $value, $startDate, $endDate);
            if ($saved) {
                $ok = t('marketing.created');
            } else {
                $error = t('marketing.create_failed');
            }
        }
    }

    if ($action === 'toggle_promotion') {
        $promotionId = (int) ($_POST['promotion_id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 0);
        if ($promotionId > 0) {
            $changed = toggle_promotion($promotionId, (int) $user['id'], $active);
            if ($changed) {
                $ok = t('marketing.status_updated');
            } else {
                $error = t('marketing.status_update_failed');
            }
        }
    }
}

$promotions = get_provider_promotions((int) $user['id'], true);
require __DIR__ . '/_header.php';
?>
<section class="card">
  <h1><?= e(t('marketing.title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok !== ''): ?><p class="alert success"><?= e($ok) ?></p><?php endif; ?>

  <h2><?= e(t('marketing.create_new')) ?></h2>
  <form method="post" class="form">
    <input type="hidden" name="action" value="create_promotion">
    <label><?= e(t('marketing.offer_title')) ?></label>
    <input type="text" name="title" required>
    <label><?= e(t('marketing.discount_type')) ?></label>
    <select name="discount_type" required>
      <option value="percent"><?= e(t('marketing.percent')) ?></option>
      <option value="fixed"><?= e(t('marketing.fixed')) ?></option>
    </select>
    <label><?= e(t('marketing.discount_value')) ?></label>
    <input type="number" name="discount_value" min="1" step="0.01" required>
    <label><?= e(t('marketing.start_date')) ?></label>
    <input type="date" name="start_date" required>
    <label><?= e(t('marketing.end_date')) ?></label>
    <input type="date" name="end_date" required>
    <button type="submit"><?= e(t('marketing.save_offer')) ?></button>
  </form>

  <h2><?= e(t('marketing.my_offers')) ?></h2>
  <table>
    <tr>
      <th>#</th>
      <th><?= e(t('marketing.offer_title')) ?></th>
      <th><?= e(t('marketing.type')) ?></th>
      <th><?= e(t('marketing.value')) ?></th>
      <th><?= e(t('marketing.period')) ?></th>
      <th><?= e(t('bids.status')) ?></th>
      <th></th>
    </tr>
    <?php foreach ($promotions as $p): ?>
      <tr>
        <td><?= (int) $p['id'] ?></td>
        <td><?= e($p['title']) ?></td>
        <td><?= e($p['discount_type'] === 'percent' ? t('marketing.percent') : t('marketing.fixed')) ?></td>
        <td><?= e((string) $p['discount_value'] . ($p['discount_type'] === 'percent' ? '%' : '')) ?></td>
        <td><?= e($p['start_date']) ?> - <?= e($p['end_date']) ?></td>
        <td><?= (int) $p['is_active'] === 1 ? e(t('marketing.active')) : e(t('marketing.stopped')) ?></td>
        <td>
          <form method="post">
            <input type="hidden" name="action" value="toggle_promotion">
            <input type="hidden" name="promotion_id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="active" value="<?= (int) $p['is_active'] === 1 ? 0 : 1 ?>">
            <button type="submit"><?= (int) $p['is_active'] === 1 ? e(t('marketing.stop')) : e(t('marketing.activate')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
