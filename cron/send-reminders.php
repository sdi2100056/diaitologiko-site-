<?php
/**
 * Αποστολή υπενθυμίσεων ραντεβού ~24 ώρες πριν.
 *
 * Τρόποι εκτέλεσης:
 *  1) Γραμμή εντολών (συνιστάται):  php cron/send-reminders.php
 *     — στήσε καθημερινό cron / Windows Task Scheduler.
 *  2) Μέσω URL (αν δεν υπάρχει cron):  /cron/send-reminders.php?key=ΤΟ_CRON_KEY
 *     — όρισε πρώτα το CRON_KEY στο includes/mail_config.php.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ics.php';
if (is_file(__DIR__ . '/../includes/mail_config.php')) require_once __DIR__ . '/../includes/mail_config.php';

$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key = defined('CRON_KEY') ? CRON_KEY : '';
    if ($key === '' || ($_GET['key'] ?? '') !== $key) {
        http_response_code(403);
        die("Απαγορευμένη πρόσβαση. Όρισε CRON_KEY και κάλεσε ?key=...\n");
    }
}

$pdo = get_db();
// Ραντεβού στο παράθυρο 23–25 ωρών από τώρα, ενεργά, χωρίς ήδη σταλμένη υπενθύμιση
$sql = "SELECT a.* FROM appointments a
        LEFT JOIN clients c ON c.id = a.client_id
        WHERE a.status <> 'cancelled'
          AND a.reminder_sent = 0
          AND (c.id IS NULL OR c.notify_reminders = 1)
          AND CONCAT(a.appointment_date,' ',a.appointment_time) BETWEEN
              DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$sent = 0; $fail = 0;
foreach ($rows as $a) {
    if (!$a['client_email']) continue;
    $when = date('d/m/Y', strtotime($a['appointment_date'])) . ' στις ' . substr($a['appointment_time'], 0, 5);
    $ics = build_ics([
        'start'    => $a['appointment_date'] . ' ' . $a['appointment_time'],
        'duration' => 45,
        'summary'  => 'Υπενθύμιση ραντεβού',
        'uid'      => 'appt-' . $a['id'] . '@dietoffice',
        'organizer'=> defined('SITE_ADMIN_EMAIL') ? SITE_ADMIN_EMAIL : '',
    ]);
    $ok = send_notification_email(
        $a['client_email'],
        'Υπενθύμιση ραντεβού — αύριο',
        "Γεια σου {$a['client_name']},\n\nΣου υπενθυμίζουμε το ραντεβού σου: {$when}.\n\nΑν χρειάζεται να το αλλάξεις ή να το ακυρώσεις, μπες στον λογαριασμό σου.\n\nΘα τα πούμε!",
        ['ics' => $ics]
    );
    if ($ok) { $pdo->prepare("UPDATE appointments SET reminder_sent=1 WHERE id=?")->execute([$a['id']]); $sent++; }
    else { $fail++; }
}

$msg = "Υπενθυμίσεις: εξετάστηκαν " . count($rows) . ", στάλθηκαν $sent" . ($fail ? ", απέτυχαν $fail" : '') . ".\n";
echo $msg;
