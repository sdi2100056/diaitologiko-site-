<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Υπηρεσίες';
$services = get_active_services();
$individual = array_values(array_filter($services, fn($s) => ($s['audience'] ?? 'individual') !== 'corporate'));
$corporate  = array_values(array_filter($services, fn($s) => ($s['audience'] ?? '') === 'corporate'));
include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="section">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Υπηρεσίες</span>
                <h1>Πακέτα συνεδριών & <span class="grad-text">ψηφιακοί οδηγοί</span></h1>
                <p class="lede">Επίλεξε το πακέτο που ταιριάζει στους στόχους σου. Η πληρωμή γίνεται με ασφάλεια μέσω κάρτας.</p>
            </div>

            <div class="services-grid">
                <?php if (empty($individual)): ?>
                    <p>Οι υπηρεσίες θα ενημερωθούν σύντομα.</p>
                <?php endif; ?>

                <?php foreach ($individual as $s): ?>
                    <div class="service-card reveal">
                        <?php if ($s['type'] === 'session_package'): ?>
                            <div class="service-portions" aria-hidden="true">
                                <?php $max_dots=8; $filled=min((int)$s['sessions_count'],$max_dots); for($i=1;$i<=$max_dots;$i++): ?>
                                    <span class="portion-dot <?= $i<=$filled?'filled':'' ?>"></span>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <span class="service-tag">E-book</span>
                        <?php endif; ?>

                        <h3><?= e($s['name']) ?></h3>
                        <p><?= e($s['description']) ?></p>
                        <div class="service-price"><?= number_format($s['price'], 2) ?>€
                            <?php if ($s['type']==='session_package'): ?><span>/ <?= (int)$s['sessions_count'] ?> συνεδρί<?= $s['sessions_count']==1?'α':'ες' ?></span><?php endif; ?>
                        </div>
                        <form action="api/viva-checkout.php" method="POST">
                            <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="width:100%;">Αγορά
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($corporate): ?>
            <div class="section-head center reveal" style="margin-top:60px">
                <span class="eyebrow">Για επιχειρήσεις</span>
                <h2>Εταιρικά προγράμματα διατροφής</h2>
                <p class="lede">Προγράμματα ευεξίας &amp; διατροφικής υποστήριξης για ομάδες και εταιρίες.</p>
            </div>
            <div class="services-grid">
                <?php foreach ($corporate as $s): ?>
                    <div class="service-card reveal">
                        <span class="service-tag">Για εταιρίες</span>
                        <h3><?= e($s['name']) ?></h3>
                        <p><?= e($s['description']) ?></p>
                        <div class="service-price"><?= number_format($s['price'], 2) ?>€</div>
                        <form action="api/viva-checkout.php" method="POST">
                            <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="width:100%;">Ενδιαφέρομαι
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section section-dim">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Πώς λειτουργεί</span>
                <h2>Από την αγορά στην πρώτη σου συνεδρία</h2>
            </div>
            <div class="timeline">
                <div class="tl-step reveal" data-delay="1"><div class="tl-num">1</div><h3>Επιλογή πακέτου</h3><p>Διάλεξε το πακέτο και ολοκλήρωσε την ασφαλή πληρωμή με κάρτα.</p></div>
                <div class="tl-step reveal" data-delay="2"><div class="tl-num">2</div><h3>Προγραμματισμός</h3><p>Κλείνεις τα ραντεβού σου online, στις ώρες που σε βολεύουν.</p></div>
                <div class="tl-step reveal" data-delay="3"><div class="tl-num">3</div><h3>Ξεκινάμε</h3><p>Στην πρώτη συνάντηση χτίζουμε το εξατομικευμένο σου πλάνο.</p></div>
            </div>
        </div>
    </section>

    <section class="section-ink">
        <div class="container cta-band reveal">
            <span class="eyebrow">Έχεις απορίες;</span>
            <h2>Δεν είσαι σίγουρη/ος ποιο πακέτο ταιριάζει;</h2>
            <p>Στείλε μας μήνυμα και θα σε καθοδηγήσουμε στην καλύτερη επιλογή για σένα.</p>
            <a href="contact.php" class="btn btn-primary">Επικοινώνησε μαζί μας</a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
