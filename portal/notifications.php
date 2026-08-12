<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

$items = q("SELECT * FROM client_notifications WHERE client_id=? ORDER BY created_at DESC LIMIT 60", [$cid])->fetchAll(PDO::FETCH_ASSOC);
// σήμανση όλων ως αναγνωσμένα
q("UPDATE client_notifications SET is_read=1 WHERE client_id=? AND is_read=0", [$cid]);

$icons = ['message'=>'💬','plan'=>'🥗','appointment'=>'📅','reschedule'=>'🔄','system'=>'🔔'];

$page_title = 'Ειδοποιήσεις';
$active = 'notifications';
require __DIR__ . '/layout_top.php';
?>
<div class="p-panel">
  <div class="p-panel-head"><h2>Ειδοποιήσεις</h2></div>
  <?php if (!$items): ?>
    <p class="p-empty">Δεν υπάρχουν ειδοποιήσεις.</p>
  <?php else: ?>
    <ul class="notif-list">
      <?php foreach ($items as $n): ?>
        <li class="notif-item <?= $n['is_read']?'':'unread' ?>">
          <span class="notif-ic"><?= $icons[$n['type']] ?? '🔔' ?></span>
          <div class="notif-body">
            <?php if ($n['link']): ?><a href="<?= e($n['link']) ?>"><?= e($n['message']) ?></a><?php else: ?><span><?= e($n['message']) ?></span><?php endif; ?>
            <span class="notif-time"><?= gr_datetime($n['created_at']) ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
