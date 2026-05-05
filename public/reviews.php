<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_role([ROLE_CLIENT, ROLE_PROVIDER]);
$user = current_user();
$error = '';
$ok = '';

if ($user['role'] === ROLE_CLIENT && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $providerId = (int) ($_POST['provider_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($requestId <= 0 || $providerId <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
        $error = t('reviews.invalid');
    } else {
        $saved = add_review($requestId, (int) $user['id'], $providerId, $rating, $comment);
        if ($saved) {
            $ok = t('reviews.saved');
        } else {
            $error = t('reviews.save_failed');
        }
    }
}

$clientPending = $user['role'] === ROLE_CLIENT ? get_completed_requests_for_review((int) $user['id'], true) : [];
$providerReviews = $user['role'] === ROLE_PROVIDER ? get_provider_reviews((int) $user['id'], true) : [];

require __DIR__ . '/_header.php';
?>
<section class="card">
  <h1><?= e(t('reviews.title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok !== ''): ?><p class="alert success"><?= e($ok) ?></p><?php endif; ?>

  <?php if ($user['role'] === ROLE_CLIENT): ?>
    <h2><?= e(t('reviews.pending')) ?></h2>
    <?php foreach ($clientPending as $r): ?>
      <form method="post" class="form card">
        <input type="hidden" name="action" value="add_review">
        <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
        <input type="hidden" name="provider_id" value="<?= (int) $r['selected_provider_id'] ?>">
        <p><strong><?= e($r['title']) ?></strong> - <?= e($r['provider_name']) ?></p>
        <label><?= e(t('reviews.rating_5')) ?></label>
        <input type="number" name="rating" min="1" max="5" required>
        <label><?= e(t('reviews.comment')) ?></label>
        <textarea name="comment" required></textarea>
        <button type="submit"><?= e(t('reviews.save')) ?></button>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($user['role'] === ROLE_PROVIDER): ?>
    <h2><?= e(t('reviews.clients_reviews')) ?></h2>
    <table>
      <tr>
        <th><?= e(t('messages.request')) ?></th>
        <th><?= e(t('requests.client')) ?></th>
        <th><?= e(t('bids.rating')) ?></th>
        <th><?= e(t('reviews.comment')) ?></th>
      </tr>
      <?php foreach ($providerReviews as $r): ?>
        <tr>
          <td><?= e($r['title']) ?></td>
          <td><?= e($r['client_name']) ?></td>
          <td><?= (int) $r['rating'] ?>/5</td>
          <td><?= e($r['comment']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
