<?php
/**
 * Δημιουργία περιεχομένου .ics (iCalendar) για ένα ραντεβού.
 * $o: ['start'=>'Y-m-d H:i:s','duration'=>int λεπτά,'summary','description','location','uid','organizer']
 */
function build_ics(array $o) {
    $tzLocal = new DateTimeZone('Europe/Athens');
    $tzUtc   = new DateTimeZone('UTC');
    $start = new DateTime($o['start'], $tzLocal); $start->setTimezone($tzUtc);
    $end = clone $start; $end->modify('+' . (int)($o['duration'] ?? 45) . ' minutes');

    $esc = function ($s) {
        return preg_replace(['/([,;\\\\])/', '/\r?\n/'], ['\\\\$1', '\\n'], (string)$s);
    };
    $uid = $o['uid'] ?? (uniqid('appt', true) . '@dietoffice');
    $now = (new DateTime('now', $tzUtc))->format('Ymd\THis\Z');

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Diet Office//Portal//EL',
        'CALSCALE:GREGORIAN',
        'METHOD:REQUEST',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $now,
        'DTSTART:' . $start->format('Ymd\THis\Z'),
        'DTEND:' . $end->format('Ymd\THis\Z'),
        'SUMMARY:' . $esc($o['summary'] ?? 'Ραντεβού'),
        'DESCRIPTION:' . $esc($o['description'] ?? ''),
        'LOCATION:' . $esc($o['location'] ?? ''),
    ];
    if (!empty($o['organizer'])) $lines[] = 'ORGANIZER:mailto:' . $o['organizer'];
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'BEGIN:VALARM';
    $lines[] = 'TRIGGER:-PT24H';
    $lines[] = 'ACTION:DISPLAY';
    $lines[] = 'DESCRIPTION:Υπενθύμιση ραντεβού';
    $lines[] = 'END:VALARM';
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
}
