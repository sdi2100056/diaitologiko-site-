<?php
require_once __DIR__ . '/init.php';
require_login();
$pdo = get_db();

$scalar = function($sql, $args=[]) use ($pdo) {
    try { $s=$pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
};

// ---- 12μηνο buckets ----
$months = [];
for ($i=11; $i>=0; $i--) $months[] = date('Y-m', strtotime("first day of -$i month"));
$mlabels = array_map(fn($m)=>date('m/y', strtotime($m.'-01')), $months);
$fill = function($rows, $key='ym', $val='v') use ($months) {
    $map = []; foreach ($rows as $r) $map[$r[$key]] = (float)$r[$val];
    return array_map(fn($m)=>$map[$m] ?? 0, $months);
};
$rowsOf = function($sql) use ($pdo) { try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; } };

// ---- KPIs ----
$rev_month = (float)$scalar("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='paid' AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");
$rev_total = (float)$scalar("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='paid'");
$appts_month = (int)$scalar("SELECT COUNT(*) FROM appointments WHERE DATE_FORMAT(appointment_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND status<>'cancelled'");
$active_clients = (int)$scalar("SELECT COUNT(*) FROM clients WHERE status='active'");

$ns = (int)$scalar("SELECT COALESCE(SUM(no_show=1),0) FROM appointments WHERE status<>'cancelled' AND CONCAT(appointment_date,' ',appointment_time) < NOW()");
$past_tot = (int)$scalar("SELECT COUNT(*) FROM appointments WHERE status<>'cancelled' AND CONCAT(appointment_date,' ',appointment_time) < NOW()");
$noshow_rate = $past_tot ? round($ns/$past_tot*100, 1) : 0.0;

$with_appt = (int)$scalar("SELECT COUNT(*) FROM (SELECT client_id FROM appointments WHERE client_id IS NOT NULL AND status<>'cancelled' GROUP BY client_id) t");
$repeat = (int)$scalar("SELECT COUNT(*) FROM (SELECT client_id FROM appointments WHERE client_id IS NOT NULL AND status<>'cancelled' GROUP BY client_id HAVING COUNT(*)>=2) t");
$retention = $with_appt ? round($repeat/$with_appt*100, 1) : 0.0;

// ---- Charts data ----
$revChart = $fill($rowsOf("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, SUM(amount) v FROM orders WHERE status='paid' GROUP BY ym"));
$apptAll  = $fill($rowsOf("SELECT DATE_FORMAT(appointment_date,'%Y-%m') ym, COUNT(*) v FROM appointments WHERE status<>'cancelled' GROUP BY ym"));
$apptCanc = $fill($rowsOf("SELECT DATE_FORMAT(appointment_date,'%Y-%m') ym, COUNT(*) v FROM appointments WHERE status='cancelled' GROUP BY ym"));
$newCli   = $fill($rowsOf("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) v FROM clients GROUP BY ym"));

$svc = $rowsOf("SELECT s.name nm, COUNT(*) c FROM orders o JOIN services s ON s.id=o.service_id WHERE o.status='paid' GROUP BY s.id ORDER BY c DESC LIMIT 6");
$svcLabels = array_map(fn($r)=>$r['nm'], $svc);
$svcData   = array_map(fn($r)=>(int)$r['c'], $svc);

$an = [
    'mlabels'=>$mlabels, 'rev'=>$revChart, 'apptAll'=>$apptAll, 'apptCanc'=>$apptCanc,
    'newCli'=>$newCli, 'svcLabels'=>$svcLabels, 'svcData'=>$svcData,
];

$page_title = 'Στατιστικά';
$active = 'analytics';
$use_charts = true;
require __DIR__ . '/layout_top.php';
?>
<div class="kpi-grid">
  <div class="kpi"><span class="kpi-cap">Έσοδα μήνα</span><span class="kpi-num"><?= eur($rev_month) ?></span></div>
  <div class="kpi"><span class="kpi-cap">Έσοδα σύνολο</span><span class="kpi-num"><?= eur($rev_total) ?></span></div>
  <div class="kpi"><span class="kpi-cap">Ραντεβού μήνα</span><span class="kpi-num"><?= $appts_month ?></span></div>
  <div class="kpi"><span class="kpi-cap">Ενεργοί πελάτες</span><span class="kpi-num"><?= $active_clients ?></span></div>
  <div class="kpi"><span class="kpi-cap">No-show</span><span class="kpi-num"><?= number_format($noshow_rate,1,',','.') ?>%</span><span class="kpi-sub"><?= $ns ?>/<?= $past_tot ?> ραντεβού</span></div>
  <div class="kpi"><span class="kpi-cap">Επαναληπτικότητα</span><span class="kpi-num"><?= number_format($retention,1,',','.') ?>%</span><span class="kpi-sub"><?= $repeat ?>/<?= $with_appt ?> πελάτες</span></div>
</div>

<div class="grid-2">
  <div class="card"><div class="card-head"><h2>Έσοδα ανά μήνα</h2></div><div class="chart-wrap"><canvas id="revChart" height="150"></canvas></div></div>
  <div class="card"><div class="card-head"><h2>Ραντεβού ανά μήνα</h2></div><div class="chart-wrap"><canvas id="apptChart" height="150"></canvas></div></div>
</div>
<div class="grid-2">
  <div class="card"><div class="card-head"><h2>Δημοφιλείς υπηρεσίες</h2></div><div class="chart-wrap"><canvas id="svcChart" height="150"></canvas></div>
    <?php if (!$svc): ?><p class="empty">Δεν υπάρχουν πληρωμένες παραγγελίες ακόμη.</p><?php endif; ?>
  </div>
  <div class="card"><div class="card-head"><h2>Νέοι πελάτες ανά μήνα</h2></div><div class="chart-wrap"><canvas id="cliChart" height="150"></canvas></div></div>
</div>

<?php $inline_js = "window.__an=" . json_encode($an, JSON_UNESCAPED_UNICODE) . ";" . <<<'JS'

(function(){
  var a = window.__an; if(!a || !window.Chart) return;
  var css = getComputedStyle(document.documentElement);
  var em = (css.getPropertyValue('--emerald')||'#0E9488').trim();
  var emd = (css.getPropertyValue('--emerald-d')||'#04795B').trim();
  var coral = (css.getPropertyValue('--coral')||'#FF6B54').trim();
  var violet = (css.getPropertyValue('--violet')||'#7C6CF0').trim();
  var amber = (css.getPropertyValue('--amber')||'#FFB443').trim();
  Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  var euro = function(v){ return new Intl.NumberFormat('el-GR',{style:'currency',currency:'EUR'}).format(v); };

  new Chart(document.getElementById('revChart'), {
    type:'bar',
    data:{ labels:a.mlabels, datasets:[{ label:'Έσοδα', data:a.rev, backgroundColor:em, borderRadius:6 }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false},
      tooltip:{ callbacks:{ label:function(c){ return euro(c.parsed.y); } } } },
      scales:{ y:{ beginAtZero:true, ticks:{ callback:function(v){ return v>=1000?(v/1000)+'k':v; } } } } }
  });

  new Chart(document.getElementById('apptChart'), {
    data:{ labels:a.mlabels, datasets:[
      { type:'line', label:'Ενεργά', data:a.apptAll, borderColor:emd, backgroundColor:'rgba(4,121,91,.12)', fill:true, tension:.3 },
      { type:'line', label:'Ακυρωμένα', data:a.apptCanc, borderColor:coral, tension:.3 }
    ]},
    options:{ responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
      plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
  });

  if (a.svcData && a.svcData.length) new Chart(document.getElementById('svcChart'), {
    type:'doughnut',
    data:{ labels:a.svcLabels, datasets:[{ data:a.svcData, backgroundColor:[em,violet,amber,coral,emd,'#7CC6B8'] }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right' } }, cutout:'58%' }
  });

  new Chart(document.getElementById('cliChart'), {
    type:'bar',
    data:{ labels:a.mlabels, datasets:[{ label:'Νέοι πελάτες', data:a.newCli, backgroundColor:violet, borderRadius:6 }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
  });
})();
JS;
?>
<?php require __DIR__ . '/layout_bottom.php'; ?>
