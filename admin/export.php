<?php
require_once __DIR__ . '/init.php';
require_login();

$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$search = trim($_GET['q'] ?? '');

function send_csv($filename, $header, $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM για σωστά ελληνικά στο Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, $header, ';');
    foreach ($rows as $r) fputcsv($out, $r, ';');
    fclose($out);
    exit;
}

if ($type === 'appointments') {
    $where=[]; $args=[];
    if (in_array($status,['pending','confirmed','cancelled'],true)) { $where[]='status=?'; $args[]=$status; }
    if ($from) { $where[]='appointment_date>=?'; $args[]=$from; }
    if ($to)   { $where[]='appointment_date<=?'; $args[]=$to; }
    if ($search){ $where[]='(client_name LIKE ? OR client_email LIKE ? OR client_phone LIKE ?)'; $l="%$search%"; array_push($args,$l,$l,$l); }
    $wsql=$where?('WHERE '.implode(' AND ',$where)):'';
    $rows = q("SELECT * FROM appointments $wsql ORDER BY appointment_date DESC, appointment_time DESC", $args)->fetchAll(PDO::FETCH_ASSOC);
    $map = ['pending'=>'Σε αναμονή','confirmed'=>'Επιβεβαιωμένο','cancelled'=>'Ακυρωμένο'];
    $data = array_map(function($a) use ($map){
        return [$a['id'], $a['appointment_date'], substr($a['appointment_time'],0,5), $a['client_name'], $a['client_email'], $a['client_phone'], $map[$a['status']]??$a['status'], $a['notes'], $a['created_at']];
    }, $rows);
    send_csv('appointments_'.date('Y-m-d').'.csv', ['ID','Ημερομηνία','Ώρα','Πελάτης','Email','Τηλέφωνο','Κατάσταση','Σημειώσεις','Δημιουργήθηκε'], $data);
}

if ($type === 'orders') {
    $where=[]; $args=[];
    if (in_array($status,['pending','paid','failed','cancelled'],true)) { $where[]='o.status=?'; $args[]=$status; }
    if ($from) { $where[]='o.created_at>=?'; $args[]=$from.' 00:00:00'; }
    if ($to)   { $where[]='o.created_at<=?'; $args[]=$to.' 23:59:59'; }
    if ($search){ $where[]='(o.client_name LIKE ? OR o.client_email LIKE ? OR o.viva_order_code LIKE ?)'; $l="%$search%"; array_push($args,$l,$l,$l); }
    $wsql=$where?('WHERE '.implode(' AND ',$where)):'';
    $rows = q("SELECT o.*, s.name sname FROM orders o LEFT JOIN services s ON s.id=o.service_id $wsql ORDER BY o.created_at DESC", $args)->fetchAll(PDO::FETCH_ASSOC);
    $map = ['pending'=>'Εκκρεμεί','paid'=>'Πληρωμένη','failed'=>'Απέτυχε','cancelled'=>'Ακυρωμένη'];
    $data = array_map(function($o) use ($map){
        return [$o['id'], $o['created_at'], $o['client_name'], $o['client_email'], $o['sname']??'', number_format((float)$o['amount'],2,'.',''), $map[$o['status']]??$o['status'], $o['viva_order_code'], $o['viva_transaction_id']];
    }, $rows);
    send_csv('orders_'.date('Y-m-d').'.csv', ['ID','Ημερομηνία','Πελάτης','Email','Υπηρεσία','Ποσό','Κατάσταση','Κωδικός Viva','Transaction ID'], $data);
}

flash_set('bad', 'Άγνωστος τύπος εξαγωγής.');
redirect('index.php');
