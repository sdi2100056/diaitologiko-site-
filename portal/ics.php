<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

$aid = (int)($_GET['appt'] ?? 0);
$a = q("SELECT * FROM appointments WHERE id=? AND client_id=?", [$aid, $cid])->fetch(PDO::FETCH_ASSOC);
if (!$a || $a['status'] === 'cancelled') { http_response_code(404); die('Το ραντεβού δεν βρέθηκε.'); }

require_once __DIR__ . '/../includes/ics.php';
$isOnline = ($a['mode'] ?? '') === 'online';
$loc = $isOnline ? ($a['meeting_link'] ?: 'Online') : (setting('address', '') ?: 'Γραφείο');
$desc = $a['notes'] ?: 'Ραντεβού διατροφικής συνεδρίας';
if ($isOnline && !empty($a['meeting_link'])) $desc .= "\nΣύνδεσμος: " . $a['meeting_link'];
$ics = build_ics([
    'start'       => $a['appointment_date'] . ' ' . $a['appointment_time'],
    'duration'    => 45,
    'summary'     => 'Ραντεβού — ' . biz_name(),
    'description' => $desc,
    'location'    => $loc,
    'uid'         => 'appt-' . $a['id'] . '@dietoffice',
    'organizer'   => defined('SITE_ADMIN_EMAIL') ? SITE_ADMIN_EMAIL : '',
]);
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="rantevou_' . $a['id'] . '.ics"');
echo $ics;
