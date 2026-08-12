<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

$date = $input['appointment_date'] ?? '';
$time = $input['appointment_time'] ?? '';
$name = trim($input['client_name'] ?? '');
$email = trim($input['client_email'] ?? '');
$phone = trim($input['client_phone'] ?? '');
$notes = trim($input['notes'] ?? '');
$type = ($input['type'] ?? 'new') === 'followup' ? 'followup' : 'new';
$practitioner_id = isset($input['practitioner']) && ctype_digit((string)$input['practitioner']) ? (int)$input['practitioner'] : null;
$duration = appt_duration($type);

// Βασική επικύρωση
if (!$date || !$time || !$name || !$email || !$phone) {
    echo json_encode(['success' => false, 'message' => 'Συμπλήρωσε όλα τα υποχρεωτικά πεδία.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο email.']);
    exit;
}

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ημερομηνία.']);
    exit;
}

if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ώρα.']);
    exit;
}

try {
    $pdo = get_db();

    // Σύνδεση με λογαριασμό πελάτη (αν υπάρχει με το ίδιο email)
    $client_id = null;
    try {
        $cs = $pdo->prepare("SELECT id FROM clients WHERE email=?");
        $cs->execute([$email]);
        $cid = $cs->fetchColumn();
        if ($cid) $client_id = (int)$cid;
    } catch (PDOException $e) { /* ο πίνακας clients ίσως δεν υπάρχει ακόμη */ }

    // Έλεγχος επικάλυψης με υπάρχοντα ραντεβού του θεραπευτή
    try {
        $newStart = strtotime($date . ' ' . $time . ':00');
        $newEnd = $newStart + $duration * 60;
        if ($practitioner_id) {
            $ov = $pdo->prepare("SELECT appointment_time, COALESCE(duration_min,60) dur FROM appointments WHERE appointment_date=? AND status<>'cancelled' AND (practitioner_id=? OR practitioner_id IS NULL)");
            $ov->execute([$date, $practitioner_id]);
        } else {
            $ov = $pdo->prepare("SELECT appointment_time, COALESCE(duration_min,60) dur FROM appointments WHERE appointment_date=? AND status<>'cancelled'");
            $ov->execute([$date]);
        }
        foreach ($ov->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $bs = strtotime($date . ' ' . $b['appointment_time']);
            $be = $bs + ((int)$b['dur']) * 60;
            if ($newStart < $be && $newEnd > $bs) {
                echo json_encode(['success' => false, 'message' => 'Η ώρα μόλις κλείστηκε. Επίλεξε άλλη.']); exit;
            }
        }
    } catch (Throwable $e) { /* αν λείπουν στήλες, συνεχίζουμε στο insert */ }

    // Επανέλεγχος διαθεσιμότητας (αποφυγή race condition λόγω UNIQUE KEY στη βάση)
    try {
        $stmt = $pdo->prepare("INSERT INTO appointments (client_name, client_email, client_phone, appointment_date, appointment_time, notes, status, client_id, practitioner_id, appt_type, duration_min) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $date, $time . ':00', $notes, $client_id, $practitioner_id, $type, $duration]);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S22') { // δεν έχει γίνει ακόμη migration (χωρίς νέες στήλες)
            $stmt = $pdo->prepare("INSERT INTO appointments (client_name, client_email, client_phone, appointment_date, appointment_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
            $stmt->execute([$name, $email, $phone, $date, $time . ':00', $notes]);
        } else {
            throw $e;
        }
    }

    // Ειδοποιήσεις email (με .ics για το ημερολόγιο του πελάτη)
    $formatted_date = $d->format('d/m/Y');
    $pract = $practitioner_id ? get_practitioner($practitioner_id) : null;
    $pract_line = $pract ? ("\nΘεραπευτής: " . $pract['name']) : '';
    $type_line = "\nΤύπος: " . appt_type_label($type);
    require_once __DIR__ . '/../includes/ics.php';
    $ics = build_ics([
        'start'       => $date . ' ' . $time . ':00',
        'duration'    => $duration,
        'summary'     => 'Ραντεβού διατροφικής συνεδρίας' . ($pract ? ' — ' . $pract['name'] : ''),
        'description' => $notes ?: 'Ραντεβού',
        'organizer'   => SITE_ADMIN_EMAIL,
        'uid'         => 'appt-' . md5($email . $date . $time) . '@dietoffice',
    ]);
    send_notification_email(
        $email,
        'Επιβεβαίωση Ραντεβού',
        "Γεια σου {$name},\n\nΤο ραντεβού σου επιβεβαιώθηκε για {$formatted_date} στις {$time}.{$pract_line}{$type_line}\n\nΕπισυνάπτεται αρχείο για να το προσθέσεις στο ημερολόγιό σου.\n\nΘα τα πούμε τότε!",
        ['ics' => $ics]
    );
    send_notification_email(
        SITE_ADMIN_EMAIL,
        'Νέο Ραντεβού',
        "Νέο ραντεβού από {$name} ({$email}, {$phone}) στις {$formatted_date} {$time}.{$pract_line}{$type_line}\nΣημειώσεις: {$notes}"
    );

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // duplicate slot
        echo json_encode(['success' => false, 'message' => 'Η ώρα μόλις κλείστηκε από άλλον πελάτη. Επίλεξε άλλη.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την καταχώρηση.']);
    }
}
