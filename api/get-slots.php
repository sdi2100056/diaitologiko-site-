<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? '';
$type = ($_GET['type'] ?? 'new') === 'followup' ? 'followup' : 'new';
$practitioner_id = isset($_GET['practitioner']) && ctype_digit((string)$_GET['practitioner']) ? (int)$_GET['practitioner'] : null;
$duration = appt_duration($type);

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ημερομηνία.']); exit;
}
$today = new DateTime('today');
if ($d < $today) { echo json_encode(['success' => false, 'message' => 'Δεν μπορείς να επιλέξεις παρελθοντική ημερομηνία.']); exit; }
if (is_date_blocked($date)) { echo json_encode(['success' => true, 'slots' => []]); exit; }

$day_of_week = (int)$d->format('w');
$pdo = get_db();

// Κανόνες διαθεσιμότητας: του θεραπευτή Ή κοινοί (practitioner_id IS NULL)
try {
    if ($practitioner_id) {
        $st = $pdo->prepare("SELECT * FROM availability WHERE active=1 AND day_of_week=? AND (practitioner_id=? OR practitioner_id IS NULL)");
        $st->execute([$day_of_week, $practitioner_id]);
    } else {
        $st = $pdo->prepare("SELECT * FROM availability WHERE active=1 AND day_of_week=?");
        $st->execute([$day_of_week]);
    }
    $day_rules = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // fallback αν δεν έχει γίνει migration για practitioner_id
    $st = $pdo->prepare("SELECT * FROM availability WHERE active=1 AND day_of_week=?");
    $st->execute([$day_of_week]);
    $day_rules = $st->fetchAll(PDO::FETCH_ASSOC);
}
if (!$day_rules) { echo json_encode(['success' => true, 'slots' => []]); exit; }

// Κλεισμένα ραντεβού (του θεραπευτή) με τη διάρκειά τους → διαστήματα
$booked = [];
try {
    if ($practitioner_id) {
        $bs = $pdo->prepare("SELECT appointment_time, COALESCE(duration_min,60) dur FROM appointments WHERE appointment_date=? AND status<>'cancelled' AND (practitioner_id=? OR practitioner_id IS NULL)");
        $bs->execute([$date, $practitioner_id]);
    } else {
        $bs = $pdo->prepare("SELECT appointment_time, COALESCE(duration_min,60) dur FROM appointments WHERE appointment_date=? AND status<>'cancelled'");
        $bs->execute([$date]);
    }
    foreach ($bs->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $bstart = strtotime($date . ' ' . $b['appointment_time']);
        $booked[] = [$bstart, $bstart + ((int)$b['dur']) * 60];
    }
} catch (Throwable $e) {
    foreach (get_booked_slots($date) as $t) { $s = strtotime($date . ' ' . $t); $booked[] = [$s, $s + 60*60]; }
}

$now = time();
$slots = [];
$seen = [];
foreach ($day_rules as $rule) {
    $winStart = strtotime($date . ' ' . $rule['start_time']);
    $winEnd   = strtotime($date . ' ' . $rule['end_time']);
    for ($s = $winStart; $s + $duration*60 <= $winEnd; $s += $duration*60) {
        $e = $s + $duration*60;
        $time_str = date('H:i', $s);
        if (isset($seen[$time_str])) continue;
        $seen[$time_str] = true;
        $overlap = false;
        foreach ($booked as [$bs2, $be2]) { if ($s < $be2 && $e > $bs2) { $overlap = true; break; } }
        $is_past = ($s < $now);
        $slots[] = ['time' => $time_str, 'available' => !$overlap && !$is_past];
    }
}
usort($slots, fn($a, $b) => strcmp($a['time'], $b['time']));

echo json_encode(['success' => true, 'slots' => $slots, 'duration' => $duration]);
