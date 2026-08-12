<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Σχετικά';
include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="section">
        <div class="container about-layout">
            <div class="reveal">
                <div class="about-photo">
                    <svg viewBox="0 0 200 240" fill="none">
                        <circle cx="100" cy="86" r="46" fill="#fff" opacity=".92"/>
                        <rect x="46" y="150" width="108" height="96" rx="18" fill="#fff" opacity=".92"/>
                    </svg>
                    <div class="badge-float">
                        <div class="feature-ico" style="width:38px;height:38px;margin:0;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.4 6.6L21 10l-5 4.4L17.4 21 12 17.3 6.6 21 8 14.4 3 10l6.6-1.4z"/></svg></div>
                        <div><b>Πιστοποιημένη Διαιτολόγος</b><span>[Αριθμός μητρώου / άδεια]</span></div>
                    </div>
                </div>
            </div>
            <div class="reveal">
                <span class="eyebrow">Σχετικά με εμένα</span>
                <h1>Γνώρισέ με</h1>
                <p class="lede">[Σύντομο βιογραφικό: σπουδές, εξειδίκευση, φιλοσοφία προσέγγισης. Γράψε 3–4 προτάσεις για το ποια είσαι και πώς δουλεύεις με τους πελάτες σου.]</p>
                <ul class="credentials">
                    <li><span class="year">[Έτος]</span><span>[Πτυχίο / Πανεπιστήμιο]</span></li>
                    <li><span class="year">[Έτος]</span><span>[Μεταπτυχιακό / Εξειδίκευση]</span></li>
                    <li><span class="year">[Έτος]</span><span>[Πιστοποίηση / Σεμινάριο]</span></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section section-dim">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Η φιλοσοφία μου</span>
                <h2>Αρχές που καθοδηγούν κάθε πλάνο</h2>
            </div>
            <div class="philosophy">
                <div class="phil-card reveal" data-delay="1">
                    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0112 5.5 5.5 5.5 0 0121.5 12c-2.5 4.5-9.5 9-9.5 9z"/></svg></div>
                    <strong>Χωρίς ενοχές</strong>
                    <p>Καμία τροφή δεν είναι «απαγορευμένη». Χτίζουμε ισορροπία, όχι στέρηση.</p>
                </div>
                <div class="phil-card reveal" data-delay="2">
                    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg></div>
                    <strong>Με βάση δεδομένα</strong>
                    <p>Αποφάσεις που στηρίζονται στην επιστήμη και στη δική σου πρόοδο.</p>
                </div>
                <div class="phil-card reveal" data-delay="3">
                    <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2"/><circle cx="10" cy="7" r="4"/></svg></div>
                    <strong>Δίπλα σου</strong>
                    <p>Συνεχής υποστήριξη ανάμεσα στις συνεδρίες, όχι μόνο κατά τη συνάντηση.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-ink">
        <div class="container cta-band reveal">
            <span class="eyebrow">Ας ξεκινήσουμε</span>
            <h2>Έτοιμη/ος να κάνεις το πρώτο βήμα;</h2>
            <p>Κλείσε ένα ραντεβού γνωριμίας και δες πώς μπορούμε να δουλέψουμε μαζί.</p>
            <a href="booking.php" class="btn btn-primary">Κλείσε Ραντεβού</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
