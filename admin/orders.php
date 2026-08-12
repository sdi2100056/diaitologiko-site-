<?php
require_once __DIR__ . '/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $map = ['paid'=>'paid','fail'=>'failed','cancel'=>'cancelled','pending'=>'pending'];
    if ($id && isset($map[$act])) {
        q("UPDATE orders SET status=? WHERE id=?", [$map[$act], $id]);
        flash_set('ok', 'Η κατάσταση της παραγγελίας ενημερώθηκε.');
    } elseif ($id && $act === 'delete') {
        q("DELETE FROM orders WHERE id=?", [$id]);
        flash_set('ok', 'Η παραγγελία διαγράφηκε.');
    }
    redirect('orders.php?' . http_build_query($_GET));
}

$status = $_GET['status'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$per    = 20;

$where=[]; $args=[];
if (in_array($status, ['pending','paid','failed','cancelled'], true)) { $where[]='o.status=?'; $args[]=$status; }
if ($from) { $where[]='o.created_at>=?'; $args[]=$from.' 00:00:00'; }
if ($to)   { $where[]='o.created_at<=?'; $args[]=$to.' 23:59:59'; }
if ($search) { $where[]='(o.client_name LIKE ? OR o.client_email LIKE ? OR o.viva_order_code LIKE ?)'; $like="%$search%"; array_push($args,$like,$like,$like); }
$wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$total = (int) q("SELECT COUNT(*) FROM orders o $wsql", $args)->fetchColumn();
$sum_all  = (float) q("SELECT COALESCE(SUM(o.amount),0) FROM orders o $wsql", $args)->fetchColumn();
$paid_where = $wsql ? $wsql." AND o.status='paid'" : "WHERE o.status='paid'";
$sum_paid = (float) q("SELECT COALESCE(SUM(o.amount),0) FROM orders o $paid_where", $args)->fetchColumn();

$pages = max(1, (int)ceil($total/$per));
$page = min($page,$pages);
$offset = ($page-1)*$per;
$list = q("SELECT o.*, s.name sname FROM orders o LEFT JOIN services s ON s.id=o.service_id $wsql ORDER BY o.created_at DESC LIMIT $per OFFSET $offset", $args)->fetchAll(PDO::FETCH_ASSOC);

$qs = $_GET; unset($qs['p']); $base_qs = http_build_query($qs);

$page_title = 'Πωλήσεις';
$active = 'orders';
require __DIR__ . '/layout_top.php';
?>
<section class="mini-kpis">
  <div class="mini-kpi"><span>Έσοδα (πληρωμένες)</span><strong class="mono"><?= eur($sum_paid) ?></strong></div>
  <div class="mini-kpi"><span>Σύνολο (φιλτραρισμένων)</span><strong class="mono"><?= eur($sum_all) ?></strong></div>
  <div class="mini-kpi"><span>Πλήθος</span><strong><?= $total ?></strong></div>
</section>

<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Πελάτης / email / κωδικός Viva">
    <select name="status">
      <option value="">Όλες</option>
      <option value="paid"      <?= $status==='paid'?'selected':'' ?>>Πληρωμένες</option>
      <option value="pending"   <?= $status==='pending'?'selected':'' ?>>Εκκρεμείς</option>
      <option value="failed"    <?= $status==='failed'?'selected':'' ?>>Αποτυχημένες</option>
      <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Ακυρωμένες</option>
    </select>
    <label class="inline">Από <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="inline">Έως <input type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="btn btn-outline" type="submit">Φιλτράρισμα</button>
    <?php if ($status||$from||$to||$search): ?><a class="btn btn-ghost" href="orders.php">Καθαρισμός</a><?php endif; ?>
  </form>
  <div class="toolbar-actions">
    <a class="btn btn-outline" href="export.php?type=orders&<?= e($base_qs) ?>">Εξαγωγή CSV</a>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><?= $total ?> παραγγελίες</h2></div>
  <?php if ($list): ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>#</th><th>Ημ/νία</th><th>Πελάτης</th><th>Υπηρεσία</th><th>Ποσό</th><th>Viva</th><th>Κατάσταση</th><th class="ta-right">Ενέργειες</th></tr></thead>
    <tbody>
      <?php foreach ($list as $o): [$lbl,$cl]=$GR_STATUS_ORDER[$o['status']] ?? ['—','muted']; ?>
      <tr>
        <td class="mono">#<?= (int)$o['id'] ?></td>
        <td><?= gr_datetime($o['created_at']) ?></td>
        <td><span class="strong"><?= e($o['client_name']) ?></span><br><span class="muted-cell"><?= e($o['client_email']) ?></span></td>
        <td><?= e($o['sname'] ?? '—') ?></td>
        <td class="mono"><?= eur($o['amount']) ?></td>
        <td class="muted-cell mono"><?= e($o['viva_order_code'] ?: '—') ?></td>
        <td><span class="badge <?= $cl ?>"><?= $lbl ?></span></td>
        <td class="ta-right actions">
          <?php if ($o['status']!=='paid'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button name="action" value="paid" class="chip chip-ok" title="Σήμανση ως πληρωμένη">€✓</button></form>
          <?php endif; ?>
          <?php if ($o['status']!=='cancelled'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button name="action" value="cancel" class="chip chip-warn" title="Ακύρωση">⦸</button></form>
          <?php endif; ?>
          <form method="post" class="inline-form" data-confirm="Διαγραφή παραγγελίας;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button name="action" value="delete" class="chip chip-bad" title="Διαγραφή">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><p class="empty">Δεν βρέθηκαν παραγγελίες.</p><?php endif; ?>
</div>

<?php if ($pages>1): ?>
<nav class="pager">
  <?php for($i=1;$i<=$pages;$i++): ?><a class="page-num<?= $i===$page?' is-active':'' ?>" href="?<?= e($base_qs) ?>&p=<?= $i ?>"><?= $i ?></a><?php endfor; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>
