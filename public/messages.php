<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_role([ROLE_CLIENT, ROLE_PROVIDER]);
$user = current_user();

$conversationId = (int) ($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $body = trim($_POST['body'] ?? '');
    $conversation = get_conversation_for_user($conversationId, (int) $user['id']);
    if (!$conversation || $body === '') {
        $error = t('messages.send_unable');
    } else {
        $sent = send_message($conversationId, (int) $user['id'], $body);
        if ($sent) {
            $ok = t('messages.sent');
        } else {
            $error = t('messages.send_failed');
        }
    }
}

$conversations = get_user_conversations((int) $user['id'], true);
$activeConversation = $conversationId > 0 ? get_conversation_for_user($conversationId, (int) $user['id']) : null;
$messages = $activeConversation ? get_messages($conversationId) : [];

require __DIR__ . '/_header.php';
?>
<section class="card">
  <h1><?= e(t('messages.title')) ?></h1>
  <?php if ($error !== ''): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <?php if ($ok !== ''): ?><p class="alert success"><?= e($ok) ?></p><?php endif; ?>

  <h2><?= e(t('messages.conversations')) ?></h2>
  <table>
    <tr>
      <th>#</th>
      <th><?= e(t('messages.request')) ?></th>
      <th><?= e(t('requests.client')) ?></th>
      <th><?= e(t('bids.provider')) ?></th>
      <th></th>
    </tr>
    <?php foreach ($conversations as $c): ?>
      <tr>
        <td><?= (int) $c['id'] ?></td>
        <td><?= e($c['title']) ?></td>
        <td><?= e($c['client_name']) ?></td>
        <td><?= e($c['provider_name']) ?></td>
        <td><a href="/messages.php?conversation_id=<?= (int) $c['id'] ?>"><?= e(t('messages.open')) ?></a></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($activeConversation): ?>
    <h2><?= e(t('messages.chat')) ?></h2>
    <div class="chat-box">
      <?php foreach ($messages as $m): ?>
        <div class="msg <?= (int) $m['sender_id'] === (int) $user['id'] ? 'mine' : '' ?>">
          <strong><?= e($m['sender_name']) ?>:</strong>
          <span><?= e($m['body']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <form method="post" class="form">
      <input type="hidden" name="action" value="send_message">
      <input type="hidden" name="conversation_id" value="<?= (int) $activeConversation['id'] ?>">
      <label><?= e(t('messages.write_message')) ?></label>
      <textarea name="body" required></textarea>
      <button type="submit"><?= e(t('messages.send')) ?></button>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
