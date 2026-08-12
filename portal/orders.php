<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id']; $email = $me['email'];

$orders = q("SELECT o.*, s.name sname, s.type stype, s.file_path sfile
             FROM orders o LEFT JOIN services s ON s.id=o.service_id
             WHERE (o.client_id=? OR (o.client_email=? AND o.client_email<>''))
             ORDER BY o.created_at DESC", [$cid,$email])->fetchAll(PDO::FETCH_ASSOC);
$st = ['pending'=>['Εκκρεμεί','warn'],'paid'=>['Πληρωμένη','ok'],'failed'=>['Απέτυχε','bad'],'cancelled'=>['Ακυρωμένη','muted']];

$page_title = 'Οι αγορές μου';
$active = 'orders';
require __DIR__ . '/layout_top.php';
?>
<?php if (!$orders): ?>
  <div class="p-panel"><p class="p-empty">Δεν έχεις αγορές ακόμη. <a href="../services.php">Δες τις υπηρεσίες →</a></p></div>
<?php else: ?>
<div class="p-panel">
  <table class="p-table">
    <thead><tr><th>Ημ/νία</th><th>Υπηρεσία</th><th>Ποσό</th><th>Κατάσταση</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): [$lbl,$cl]=$st[$o['status']] ?? ['—','muted']; ?>
      <tr>
        <td><?= gr_date($o['created_at']) ?></td>
        <td><?= e($o['sname'] ?? 'Υπηρεσία') ?></td>
        <td class="mono"><?= eur($o['amount']) ?></td>
        <td><span class="badge <?= $cl ?>"><?= $lbl ?></span></td>
        <td class="ta-right">
          <?php if ($o['status']==='paid' && $o['stype']==='ebook' && $o['sfile']): ?>
            <a class="btn btn-outline btn-sm" href="download.php?order=<?= (int)$o['id'] ?>">Λήψη e-book</a>
          <?php endif; ?>
          <?php if ($o['status']==='paid'): ?>
            <a class="btn btn-outline btn-sm" href="receipt.php?order=<?= (int)$o['id'] ?>" target="_blank">Απόδειξη</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>
