<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_login();
$user = current_user();

$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$error = '';
$ok = '';
$providerOpenRequests = $user['role'] === ROLE_PROVIDER ? get_open_requests() : [];
if ($user['role'] === ROLE_PROVIDER && $requestId <= 0 && count($providerOpenRequests) > 0) {
    $requestId = (int) $providerOpenRequests[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'submit_bid' && $user['role'] === ROLE_PROVIDER) {
        $price = (float) ($_POST['price'] ?? 0);
        $duration = (int) ($_POST['duration_days'] ?? 0);
        $details = trim($_POST['details'] ?? '');
        if ($requestId <= 0 || $price <= 0 || $duration <= 0 || $details === '') {
            $error = t('bids.invalid_bid_data');
        } elseif (get_service_request_status($requestId) !== 'open') {
            $error = t('bids.request_not_open');
        } elseif (provider_has_bid($requestId, (int) $user['id'])) {
            $error = t('bids.already_submitted');
        } else {
            $okBid = submit_bid($requestId, (int) $user['id'], $price, $duration, $details);
            if ($okBid) {
                $ok = t('bids.sent');
            } else {
                $error = t('bids.send_failed');
            }
        }
    }

    if ($action === 'accept_bid' && $user['role'] === ROLE_CLIENT) {
        $bidId = (int) ($_POST['bid_id'] ?? 0);
        $request = get_request_for_client($requestId, (int) $user['id']);
        $bid = get_bid_for_request($bidId, $requestId);
        if (!$request || !$bid || $request['status'] !== 'open') {
            $error = t('bids.cannot_accept');
        } else {
            $done = accept_bid_and_start_request($requestId, $bidId, (int) $bid['provider_id'], (float) $bid['price']);
            if ($done) {
                $ok = t('bids.accepted');
            } else {
                $error = t('bids.accept_failed');
            }
        }
    }

    if ($action === 'complete_request' && $user['role'] === ROLE_CLIENT) {
        if ($requestId <= 0) {
            $error = t('bids.invalid_request');
        } else {
            $done = complete_request($requestId, (int) $user['id']);
            if ($done) {
                $ok = t('bids.completed');
            } else {
                $error = t('bids.complete_failed');
            }
        }
    }
}

$request = null;
$bids = [];
$providerBids = [];
if ($requestId > 0) {
    if ($user['role'] === ROLE_CLIENT) {
        $request = get_request_for_client($requestId, (int) $user['id']);
    } else {
        foreach ($providerOpenRequests as $r) {
            if ((int) $r['id'] === $requestId) {
                $request = $r;
                break;
            }
        }
    }
    if ($request) {
        $bids = get_bids_for_request($requestId);
    }
}
if ($user['role'] === ROLE_PROVIDER) {
    $providerBids = get_bids_by_provider((int) $user['id']);
}

$clientRequests = $user['role'] === ROLE_CLIENT ? get_requests_by_client((int) $user['id']) : [];

require __DIR__ . '/_header.php';
?>
<section class="card">
  <h1><?= e(t('bids.title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok !== ''): ?><p class="alert success"><?= e($ok) ?></p><?php endif; ?>

  <?php if ($user['role'] === ROLE_PROVIDER): ?>
    <?php if (count($providerOpenRequests) > 0): ?>
      <h2><?= e(t('bids.submit_on', ['title' => $request['title'] ?? $providerOpenRequests[0]['title']])) ?></h2>
      <form method="post" class="form">
        <input type="hidden" name="action" value="submit_bid">
        <label><?= e(t('requests.request_title')) ?></label>
        <select name="request_id" required>
          <?php foreach ($providerOpenRequests as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= ((int) $r['id'] === (int) $requestId) ? 'selected' : '' ?>>
              #<?= (int) $r['id'] ?> - <?= e($r['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label><?= e(t('bids.price')) ?></label>
        <input type="number" min="1" step="0.01" name="price" required>
        <label><?= e(t('bids.duration_days')) ?></label>
        <input type="number" min="1" name="duration_days" required>
        <label><?= e(t('bids.details')) ?></label>
        <textarea name="details" required></textarea>
        <button type="submit"><?= e(t('bids.send')) ?></button>
      </form>
      <h2><?= e(t('bids.current')) ?></h2>
      <table>
        <tr>
          <th><?= e(t('bids.provider')) ?></th>
          <th><?= e(t('bids.price')) ?></th>
          <th><?= e(t('bids.duration_days')) ?></th>
          <th><?= e(t('bids.rating')) ?></th>
          <th><?= e(t('bids.status')) ?></th>
        </tr>
        <?php foreach ($bids as $b): ?>
          <tr>
            <td><?= e($b['provider_name']) ?></td>
            <td><?= e((string) $b['price']) ?></td>
            <td><?= (int) $b['duration_days'] ?></td>
            <td><?= e((string) ($b['rating_avg'] ?? '0')) ?></td>
            <td><?= e(status_label($b['status'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p><?= e(t('bids.select_request_first')) ?></p>
    <?php endif; ?>

    <h2><?= e(t('bids.my_bids')) ?></h2>
    <table>
      <tr>
        <th>#</th>
        <th><?= e(t('bids.request_title')) ?></th>
        <th><?= e(t('bids.price')) ?></th>
        <th><?= e(t('bids.duration_days')) ?></th>
        <th><?= e(t('bids.status')) ?></th>
      </tr>
      <?php foreach ($providerBids as $b): ?>
        <tr>
          <td><?= (int) $b['id'] ?></td>
          <td><?= e($b['request_title']) ?></td>
          <td><?= e((string) $b['price']) ?></td>
          <td><?= (int) $b['duration_days'] ?> <?= e(t('bids.days')) ?></td>
          <td><?= e(status_label($b['status'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if ($user['role'] === ROLE_CLIENT): ?>
    <h2><?= e(t('requests.my_requests')) ?></h2>
    <table>
      <tr>
        <th>#</th>
        <th><?= e(t('requests.request_title')) ?></th>
        <th><?= e(t('bids.status')) ?></th>
        <th></th>
      </tr>
      <?php foreach ($clientRequests as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e(status_label($r['status'])) ?></td>
          <td><a href="/bids.php?request_id=<?= (int) $r['id'] ?>"><?= e(t('bids.manage')) ?></a></td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?php if ($request): ?>
      <h2><?= e(t('bids.request_bids', ['title' => $request['title']])) ?></h2>
      <table>
        <tr>
          <th><?= e(t('bids.provider')) ?></th>
          <th><?= e(t('bids.price')) ?></th>
          <th><?= e(t('bids.duration_days')) ?></th>
          <th><?= e(t('bids.rating')) ?></th>
          <th><?= e(t('bids.details')) ?></th>
          <th><?= e(t('bids.status')) ?></th>
          <th></th>
        </tr>
        <?php foreach ($bids as $b): ?>
          <tr>
            <td><?= e($b['provider_name']) ?></td>
            <td><?= e((string) $b['price']) ?></td>
            <td><?= (int) $b['duration_days'] ?> <?= e(t('bids.days')) ?></td>
            <td><?= e((string) ($b['rating_avg'] ?? '0')) ?></td>
            <td><?= e($b['details']) ?></td>
            <td><?= e(status_label($b['status'])) ?></td>
            <td>
              <?php if ($request['status'] === 'open'): ?>
                <form method="post">
                  <input type="hidden" name="action" value="accept_bid">
                  <input type="hidden" name="request_id" value="<?= (int) $requestId ?>">
                  <input type="hidden" name="bid_id" value="<?= (int) $b['id'] ?>">
                  <button type="submit"><?= e(t('bids.select')) ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php if ($request['status'] === 'in_progress'): ?>
        <form method="post" class="inline-form">
          <input type="hidden" name="action" value="complete_request">
          <input type="hidden" name="request_id" value="<?= (int) $requestId ?>">
          <button type="submit"><?= e(t('bids.finish_service')) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
