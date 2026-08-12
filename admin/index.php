<?php
require_once __DIR__ . '/init.php';
require_login();

$pdo = get_db();
$today = date('Y-m-d');
$month_start = date('Y-m-01');

// ---- KPIs --------------------------------------------------------------
$rev_total  = (float) q("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='paid'")->fetchColumn();
$rev_month  = (float) q("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='paid' AND created_at >= ?", [$month_start])->fetchColumn();
$orders_paid = (int) q("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn();
$orders_pending = (int) q("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$appts_today = (int) q("SELECT COUNT(*) FROM appointments WHERE appointment_date=? AND status!='cancelled'", [$today])->fetchColumn();
$appts_upcoming = (int) q("SELECT COUNT(*) FROM appointments WHERE appointment_date>=? AND status!='cancelled'", [$today])->fetchColumn();
$appts_pending = (int) q("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
$avg_order = $orders_paid ? $rev_total / $orders_paid : 0;

// ---- Έσοδα τελευταίων 6 μηνών -----------------------------------------
$months = [];
$labels = [];
$gr_months = ['','Ιαν','Φεβ','Μαρ','Απρ','Μάι','Ιουν','Ιουλ','Αυγ','Σεπ','Οκτ','Νοε','Δεκ'];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("first day of -$i month");
    $key = date('Y-m', $t);
    $months[$key] = 0.0;
    $labels[$key] = $gr_months[(int)date('n', $t)] . " '" . date('y', $t);
}
$rows = q("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, SUM(amount) tot
           FROM orders WHERE status='paid' AND created_at >= ?
           GROUP BY ym", [date('Y-m-01', strtotime('first day of -5 month'))])->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    if (isset($months[$r['ym']])) $months[$r['ym']] = (float)$r['tot'];
}
$chart_labels = array_values($labels);
$chart_values = array_values($months);

// ---- Κατανομή ραντεβού ανά κατάσταση -----------------------------------
$appt_status_rows = q("SELECT status, COUNT(*) c FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$appt_conf = (int)($appt_status_rows['confirmed'] ?? 0);
$appt_pend = (int)($appt_status_rows['pending'] ?? 0);
$appt_canc = (int)($appt_status_rows['cancelled'] ?? 0);

// ---- Top υπηρεσίες σε έσοδα --------------------------------------------
$top_services = q("SELECT s.name, COUNT(o.id) cnt, COALESCE(SUM(o.amount),0) rev
                   FROM orders o JOIN services s ON s.id=o.service_id
                   WHERE o.status='paid'
                   GROUP BY o.service_id ORDER BY rev DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ---- Επόμενα ραντεβού & πρόσφατες πωλήσεις ------------------------------
$next_appts = q("SELECT * FROM appointments WHERE appointment_date>=? AND status!='cancelled'
                 ORDER BY appointment_date ASC, appointment_time ASC LIMIT 6", [$today])->fetchAll(PDO::FETCH_ASSOC);
$recent_orders = q("SELECT o.*, s.name sname FROM orders o LEFT JOIN services s ON s.id=o.service_id
                    ORDER BY o.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Επισκόπηση';
$active = 'dashboard';
$use_charts = true;
require __DIR__ . '/layout_top.php';
?>
<section class="kpi-grid">
  <div class="kpi">
    <div class="kpi-ico" style="--c:var(--emerald)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    <div class="kpi-body"><span class="kpi-label">Συνολικά έσοδα</span><span class="kpi-num"><?= eur($rev_total) ?></span></div>
  </div>
  <div class="kpi">
    <div class="kpi-ico" style="--c:var(--coral)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg></div>
    <div class="kpi-body"><span class="kpi-label">Έσοδα αυτόν τον μήνα</span><span class="kpi-num"><?= eur($rev_month) ?></span></div>
  </div>
  <div class="kpi">
    <div class="kpi-ico" style="--c:var(--violet)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v3M16 2v3M3 8h18M5 5h14v16H5z"/></svg></div>
    <div class="kpi-body"><span class="kpi-label">Επερχόμενα ραντεβού</span><span class="kpi-num"><?= $appts_upcoming ?></span><span class="kpi-sub"><?= $appts_today ?> σήμερα</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-ico" style="--c:var(--amber)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14h18V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/></svg></div>
    <div class="kpi-body"><span class="kpi-label">Πληρωμένες παραγγελίες</span><span class="kpi-num"><?= $orders_paid ?></span><span class="kpi-sub">Μ.Ο. <?= eur($avg_order) ?></span></div>
  </div>
</section>

<?php if ($orders_pending || $appts_pending): ?>
<section class="alert-row">
  <?php if ($appts_pending): ?><a href="appointments.php?status=pending" class="mini-alert warn"><?= $appts_pending ?> ραντεβού σε αναμονή</a><?php endif; ?>
  <?php if ($orders_pending): ?><a href="orders.php?status=pending" class="mini-alert warn"><?= $orders_pending ?> παραγγελίες εκκρεμούν</a><?php endif; ?>
</section>
<?php endif; ?>

<section class="grid-2">
  <div class="card">
    <div class="card-head"><h2>Έσοδα (τελευταίοι 6 μήνες)</h2></div>
    <div class="chart-wrap"><canvas id="revChart" height="150"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Ραντεβού ανά κατάσταση</h2></div>
    <div class="chart-wrap chart-donut"><canvas id="apptChart" height="150"></canvas></div>
  </div>
</section>

<section class="grid-2">
  <div class="card">
    <div class="card-head"><h2>Επόμενα ραντεβού</h2><a href="appointments.php" class="card-link">Όλα →</a></div>
    <?php if ($next_appts): ?>
    <table class="table compact">
      <thead><tr><th>Ημ/νία</th><th>Ώρα</th><th>Πελάτης</th><th>Κατάσταση</th></tr></thead>
      <tbody>
        <?php foreach ($next_appts as $a): [$lbl,$cl]=$GR_STATUS_APPT[$a['status']]; ?>
        <tr>
          <td><?= gr_date($a['appointment_date']) ?></td>
          <td class="mono"><?= hhmm($a['appointment_time']) ?></td>
          <td><a href="appointment-edit.php?id=<?= (int)$a['id'] ?>"><?= e($a['client_name']) ?></a></td>
          <td><span class="badge <?= $cl ?>"><?= $lbl ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="empty">Δεν υπάρχουν επερχόμενα ραντεβού.</p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Πρόσφατες πωλήσεις</h2><a href="orders.php" class="card-link">Όλες →</a></div>
    <?php if ($recent_orders): ?>
    <table class="table compact">
      <thead><tr><th>Ημ/νία</th><th>Υπηρεσία</th><th>Ποσό</th><th>Κατάσταση</th></tr></thead>
      <tbody>
        <?php foreach ($recent_orders as $o): [$lbl,$cl]=$GR_STATUS_ORDER[$o['status']] ?? ['—','muted']; ?>
        <tr>
          <td><?= gr_date($o['created_at']) ?></td>
          <td><?= e($o['sname'] ?? '—') ?></td>
          <td class="mono"><?= eur($o['amount']) ?></td>
          <td><span class="badge <?= $cl ?>"><?= $lbl ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="empty">Καμία πώληση ακόμη.</p><?php endif; ?>
  </div>
</section>

<?php if ($top_services): ?>
<section class="card">
  <div class="card-head"><h2>Δημοφιλέστερες υπηρεσίες (σε έσοδα)</h2></div>
  <table class="table">
    <thead><tr><th>Υπηρεσία</th><th>Πωλήσεις</th><th>Έσοδα</th></tr></thead>
    <tbody>
      <?php foreach ($top_services as $ts): ?>
      <tr><td><?= e($ts['name']) ?></td><td><?= (int)$ts['cnt'] ?></td><td class="mono"><?= eur($ts['rev']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php
$inline_js = "
const revLabels = " . json_encode($chart_labels, JSON_UNESCAPED_UNICODE) . ";
const revValues = " . json_encode($chart_values) . ";
const apptData = [" . $appt_conf . "," . $appt_pend . "," . $appt_canc . "];
window.__adminCharts = { revLabels, revValues, apptData };
";
require __DIR__ . '/layout_bottom.php';
