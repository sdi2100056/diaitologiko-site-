<?php
require_once __DIR__ . '/init.php';
require_login();

$order_id = (int)($_GET['order'] ?? 0);
$o = q("SELECT o.*, s.name sname FROM orders o LEFT JOIN services s ON s.id=o.service_id WHERE o.id=?", [$order_id])->fetch(PDO::FETCH_ASSOC);
if (!$o) { http_response_code(404); die('Η παραγγελία δεν βρέθηκε.'); }

require_once __DIR__ . '/../includes/pdf_receipt.php';
$d = [
    'order_id'     => $o['id'],
    'date'         => gr_date($o['created_at']),
    'client_name'  => $o['client_name'],
    'client_email' => $o['client_email'],
    'service'      => $o['sname'],
    'amount'       => $o['amount'],
    'payment_ref'  => $o['viva_transaction_id'] ?: $o['viva_order_code'],
    'brand'        => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Διαιτολογικό Γραφείο',
];
if (extension_loaded('mbstring')) {
    try {
        $pdf = build_receipt_pdf($d);
        if ($pdf && substr($pdf, 0, 4) === '%PDF') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="apodeixi_' . $o['id'] . '.pdf"');
            echo $pdf; exit;
        }
    } catch (\Throwable $e) {}
}
header('Content-Type: text/html; charset=utf-8');
echo receipt_html($d);
