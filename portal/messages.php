<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        if (mb_strlen($body) > 4000) $body = mb_substr($body, 0, 4000);
        q("INSERT INTO messages (client_id,sender,body) VALUES (?,'client',?)", [$cid, $body]);
        notify_admin('message', $cid, null, 'Νέο μήνυμα από ' . ($me['name'] ?? 'πελάτη'));
        // ειδοποίηση γραφείου
        if (defined('SITE_ADMIN_EMAIL')) {
            send_notification_email(SITE_ADMIN_EMAIL, 'Νέο μήνυμα πελάτη',
                "Ο/Η {$me['name']} έστειλε μήνυμα μέσω της πύλης:\n\n" . $body);
        }
        flash_set('ok', 'Το μήνυμα στάλθηκε.');
    }
    redirect('messages.php');
}

// σήμανση ως αναγνωσμένα (μηνύματα γραφείου → πελάτη)
q("UPDATE messages SET read_at=NOW() WHERE client_id=? AND sender='admin' AND read_at IS NULL", [$cid]);

$msgs = q("SELECT * FROM messages WHERE client_id=? ORDER BY created_at ASC, id ASC", [$cid])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Μηνύματα';
$active = 'messages';
require __DIR__ . '/layout_top.php';
?>
<?php foreach (flash_all() as $f): ?><div class="p-flash <?= $f['type']==='ok'?'ok':'bad' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<div class="p-panel">
  <div class="p-panel-head"><h2>Συνομιλία με το γραφείο</h2></div>
  <div class="chat">
    <?php if (!$msgs): ?>
      <p class="p-empty">Δεν υπάρχουν μηνύματα ακόμη. Στείλε το πρώτο σου μήνυμα!</p>
    <?php else: foreach ($msgs as $m): $mine = $m['sender']==='client'; ?>
      <div class="chat-row <?= $mine?'mine':'theirs' ?>">
        <div class="chat-bubble">
          <?= nl2br(e($m['body'])) ?>
          <span class="chat-time"><?= gr_datetime($m['created_at']) ?></span>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <form method="post" class="chat-form">
    <?= csrf_field() ?>
    <textarea name="body" rows="2" placeholder="Γράψε το μήνυμά σου…" required maxlength="4000"></textarea>
    <button class="btn btn-primary" type="submit">Αποστολή</button>
  </form>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
