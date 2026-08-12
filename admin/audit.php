<?php
require_once __DIR__ . '/init.php';
require_login();

$rows = q("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
$labels = [
    'login'=>'Σύνδεση', 'settings_update'=>'Αλλαγή ρυθμίσεων', '2fa_enable'=>'Ενεργοποίηση 2FA',
    '2fa_disable'=>'Απενεργοποίηση 2FA', 'client_create'=>'Νέος πελάτης', 'gdpr_delete'=>'Διαγραφή (GDPR)',
    'post_delete'=>'Διαγραφή άρθρου', 'post_publish'=>'Δημοσίευση άρθρου', 'post_unpublish'=>'Απόσυρση άρθρου',
    'appointment_delete'=>'Διαγραφή ραντεβού',
];

$page_title = 'Αρχείο ενεργειών';
$active = 'settings';
require __DIR__ . '/layout_top.php';
?>
<p class="crumbs"><a href="settings.php">← Ρυθμίσεις</a></p>
<div class="card">
  <div class="card-head"><h2>Αρχείο ενεργειών (audit log)</h2></div>
  <?php if ($rows): ?>
  <div class="table-scroll">
  <table class="table"><thead><tr><th>Ημ/νία</th><th>Χρήστης</th><th>Ενέργεια</th><th>Οντότητα</th><th>Λεπτομέρειες</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="mono"><?= gr_datetime($r['created_at']) ?></td>
      <td><?= e($r['admin_user'] ?: '—') ?></td>
      <td><?= e($labels[$r['action']] ?? $r['action']) ?></td>
      <td><?= e($r['entity'] ?: '—') ?><?= $r['entity_id'] ? ' #'.(int)$r['entity_id'] : '' ?></td>
      <td><?= e($r['details'] ?: '—') ?></td>
      <td class="mono"><?= e($r['ip'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php else: ?><p class="empty">Δεν υπάρχουν καταγεγραμμένες ενέργειες ακόμη.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
