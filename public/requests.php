<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_login();
$user = current_user();

$error = '';
$ok = '';

if ($user['role'] === ROLE_CLIENT && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_request') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $budget = (float) ($_POST['budget'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');

    if ($title === '' || $category === '' || $budget <= 0 || $location === '' || $dueDate === '') {
        $error = t('requests.invalid_data');
    } else {
        $created = create_service_request((int) $user['id'], $title, $category, $budget, $location, $dueDate);
        if ($created) {
            $ok = t('requests.created');
        } else {
            $error = t('requests.create_failed');
        }
    }
}

$clientRequests = [];
$openRequests = [];

if ($user['role'] === ROLE_CLIENT) {
    $clientRequests = get_requests_by_client((int) $user['id']);
} else {
    $openRequests = get_open_requests();
}

require __DIR__ . '/_header.php';
?>
<section class="card">
  <h1><?= e(t('requests.title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok !== ''): ?><p class="alert success"><?= e($ok) ?></p><?php endif; ?>

  <?php if ($user['role'] === ROLE_CLIENT): ?>
    <h2><?= e(t('requests.new')) ?></h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="create_request">
      <label><?= e(t('requests.request_title')) ?></label>
      <input type="text" name="title" required>
      <label><?= e(t('requests.service_type')) ?></label>
      <input type="text" name="category" required>
      <label><?= e(t('requests.budget')) ?></label>
      <input type="number" name="budget" min="1" step="0.01" required>
      <label><?= e(t('requests.location')) ?></label>
      <input type="text" name="location" required>
      <label><?= e(t('requests.due_date')) ?></label>
      <input type="date" name="due_date" required>
      <button type="submit"><?= e(t('requests.publish')) ?></button>
    </form>

    <h2><?= e(t('requests.my_requests')) ?></h2>
    <table>
      <tr>
        <th>#</th>
        <th><?= e(t('requests.request_title')) ?></th>
        <th><?= e(t('bids.status')) ?></th>
        <th><?= e(t('requests.budget')) ?></th>
        <th><?= e(t('requests.offers')) ?></th>
      </tr>
      <?php foreach ($clientRequests as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e(status_label($r['status'])) ?></td>
          <td><?= e((string) $r['budget']) ?></td>
          <td><a href="/bids.php?request_id=<?= (int) $r['id'] ?>"><?= e(t('requests.view_offers', ['count' => (int) $r['bids_count']])) ?></a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <h2><?= e(t('requests.open_requests')) ?></h2>
    <table>
      <tr>
        <th>#</th>
        <th><?= e(t('requests.request_title')) ?></th>
        <th><?= e(t('requests.service_type')) ?></th>
        <th><?= e(t('requests.budget')) ?></th>
        <th><?= e(t('requests.location')) ?></th>
        <th><?= e(t('requests.client')) ?></th>
        <th></th>
      </tr>
      <?php foreach ($openRequests as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e($r['category']) ?></td>
          <td><?= e((string) $r['budget']) ?></td>
          <td><?= e($r['location']) ?></td>
          <td><?= e($r['client_name']) ?></td>
          <td><a href="/bids.php?request_id=<?= (int) $r['id'] ?>"><?= e(t('requests.submit_bid')) ?></a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
