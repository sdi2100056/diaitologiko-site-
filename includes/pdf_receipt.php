<?php
/**
 * Δημιουργία PDF απόδειξης πληρωμής με tFPDF (Unicode/ελληνικά).
 * Επιστρέφει το PDF ως string (bytes).
 *
 * $d: ['order_id','date','client_name','client_email','service','amount',
 *      'payment_ref','brand','office_email']
 */
if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', __DIR__ . '/lib/tfpdf/font/');
}
require_once __DIR__ . '/lib/tfpdf/tFPDF.php';

function build_receipt_pdf(array $d) {
    $brand = $d['brand'] ?? 'Διαιτολογικό Γραφείο';

    $pdf = new tFPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
    $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);

    // Header
    $pdf->SetFillColor(14, 148, 136);
    $pdf->Rect(0, 0, 210, 34, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('DejaVu', 'B', 20);
    $pdf->SetXY(15, 11);
    $pdf->Cell(0, 10, $brand, 0, 1, 'L');

    // Τίτλος
    $pdf->SetTextColor(12, 31, 26);
    $pdf->SetFont('DejaVu', 'B', 16);
    $pdf->SetXY(15, 46);
    $pdf->Cell(0, 10, 'ΑΠΟΔΕΙΞΗ ΠΛΗΡΩΜΗΣ', 0, 1, 'L');

    $pdf->SetFont('DejaVu', '', 11);
    $pdf->SetTextColor(80, 90, 86);
    $pdf->SetX(15);
    $pdf->Cell(0, 7, 'Αρ. παραστατικού: #' . str_pad((string)$d['order_id'], 6, '0', STR_PAD_LEFT), 0, 1, 'L');
    $pdf->SetX(15);
    $pdf->Cell(0, 7, 'Ημερομηνία: ' . $d['date'], 0, 1, 'L');

    $pdf->Ln(6);
    // Στοιχεία πελάτη
    $pdf->SetTextColor(12, 31, 26);
    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetX(15); $pdf->Cell(0, 8, 'Στοιχεία πελάτη', 0, 1, 'L');
    $pdf->SetFont('DejaVu', '', 11);
    $pdf->SetTextColor(60, 74, 70);
    $pdf->SetX(15); $pdf->Cell(0, 7, ($d['client_name'] ?: '—'), 0, 1, 'L');
    if (!empty($d['client_email'])) { $pdf->SetX(15); $pdf->Cell(0, 7, $d['client_email'], 0, 1, 'L'); }

    $pdf->Ln(6);
    // Πίνακας
    $pdf->SetFont('DejaVu', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(4, 121, 91);
    $pdf->SetX(15);
    $pdf->Cell(130, 10, '  Περιγραφή', 0, 0, 'L', true);
    $pdf->Cell(50, 10, 'Ποσό  ', 0, 1, 'R', true);

    $pdf->SetFont('DejaVu', '', 11);
    $pdf->SetTextColor(20, 30, 26);
    $pdf->SetFillColor(243, 248, 244);
    $pdf->SetX(15);
    $pdf->Cell(130, 11, '  ' . ($d['service'] ?: 'Υπηρεσία'), 0, 0, 'L', true);
    $pdf->Cell(50, 11, number_format((float)$d['amount'], 2, ',', '.') . ' €  ', 0, 1, 'R', true);

    // Σύνολο
    $pdf->SetFont('DejaVu', 'B', 13);
    $pdf->SetTextColor(12, 31, 26);
    $pdf->SetX(15);
    $pdf->Cell(130, 12, '', 0, 0);
    $pdf->Cell(50, 12, 'Σύνολο: ' . number_format((float)$d['amount'], 2, ',', '.') . ' €  ', 0, 1, 'R');

    if (!empty($d['payment_ref'])) {
        $pdf->Ln(3);
        $pdf->SetFont('DejaVu', '', 10);
        $pdf->SetTextColor(108, 123, 118);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'Κωδικός συναλλαγής: ' . $d['payment_ref'], 0, 1, 'L');
    }

    // Υποσημείωση
    $pdf->SetY(-40);
    $pdf->SetFont('DejaVu', '', 9);
    $pdf->SetTextColor(140, 150, 146);
    $pdf->SetX(15);
    $pdf->MultiCell(180, 5, 'Η παρούσα απόδειξη επιβεβαιώνει την πληρωμή. Δεν αποτελεί επίσημο φορολογικό παραστατικό (τιμολόγιο) εκτός αν συνοδεύεται από αντίστοιχη έκδοση μέσω myDATA/ΑΑΔΕ.');

    return $pdf->Output('receipt.pdf', 'S');
}

/**
 * Εναλλακτική εκτυπώσιμη απόδειξη σε HTML (fallback αν λείπει το mbstring).
 * Επιστρέφει πλήρη HTML σελίδα με κουμπί εκτύπωσης/αποθήκευσης ως PDF.
 */
function receipt_html(array $d) {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $amount = number_format((float)$d['amount'], 2, ',', '.') . ' €';
    $no = '#' . str_pad((string)$d['order_id'], 6, '0', STR_PAD_LEFT);
    $ref = !empty($d['payment_ref']) ? '<p class="muted">Κωδικός συναλλαγής: ' . $e($d['payment_ref']) . '</p>' : '';
    return '<!DOCTYPE html><html lang="el"><head><meta charset="UTF-8">
<title>Απόδειξη ' . $e($no) . '</title><style>
*{box-sizing:border-box}body{font-family:Segoe UI,Arial,sans-serif;color:#0C1F1A;margin:0;background:#eef4f0}
.sheet{max-width:720px;margin:24px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px -14px rgba(9,45,38,.25)}
.hd{background:linear-gradient(135deg,#0E9488,#04795B);color:#fff;padding:26px 32px}
.hd h1{margin:0;font-size:22px}.bd{padding:32px}
h2{font-size:18px;margin:0 0 6px}.muted{color:#6C7B76;font-size:14px;margin:2px 0}
table{width:100%;border-collapse:collapse;margin:20px 0}
th{background:#04795B;color:#fff;text-align:left;padding:12px}th.r,td.r{text-align:right}
td{padding:12px;border-bottom:1px solid #E1EAE5}
.total{font-size:20px;font-weight:800;text-align:right;margin-top:8px}
.note{color:#8C968E;font-size:12px;margin-top:24px;line-height:1.5}
.bar{max-width:720px;margin:12px auto;text-align:right}
.btn{background:#FF6B54;color:#fff;border:0;border-radius:10px;padding:11px 20px;font-weight:700;cursor:pointer;font-size:15px}
@media print{.bar{display:none}body{background:#fff}.sheet{box-shadow:none;margin:0}}
</style></head><body>
<div class="bar"><button class="btn" onclick="window.print()">Εκτύπωση / Αποθήκευση ως PDF</button></div>
<div class="sheet">
  <div class="hd"><h1>' . $e($d['brand'] ?? 'Διαιτολογικό Γραφείο') . '</h1></div>
  <div class="bd">
    <h2>Απόδειξη πληρωμής</h2>
    <p class="muted">Αρ. παραστατικού: ' . $e($no) . '</p>
    <p class="muted">Ημερομηνία: ' . $e($d['date']) . '</p>
    <h2 style="margin-top:22px">Στοιχεία πελάτη</h2>
    <p class="muted">' . $e($d['client_name'] ?: '—') . ($d['client_email'] ? '<br>' . $e($d['client_email']) : '') . '</p>
    <table><thead><tr><th>Περιγραφή</th><th class="r">Ποσό</th></tr></thead>
    <tbody><tr><td>' . $e($d['service'] ?: 'Υπηρεσία') . '</td><td class="r">' . $e($amount) . '</td></tr></tbody></table>
    <div class="total">Σύνολο: ' . $e($amount) . '</div>
    ' . $ref . '
    <p class="note">Η παρούσα απόδειξη επιβεβαιώνει την πληρωμή. Δεν αποτελεί επίσημο φορολογικό παραστατικό (τιμολόγιο) εκτός αν συνοδεύεται από αντίστοιχη έκδοση μέσω myDATA/ΑΑΔΕ.</p>
  </div>
</div></body></html>';
}
