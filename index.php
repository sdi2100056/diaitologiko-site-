<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Αρχική';
$featured = array_slice(get_active_services(), 0, 3);
$json_ld = json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => biz_name(),
    'description' => 'Εξατομικευμένη διατροφική καθοδήγηση, πακέτα συνεδριών και ψηφιακοί οδηγοί.',
    'url' => base_url() . '/',
    'email' => setting('contact_email', '') ?: null,
    'telephone' => setting('contact_phone', '') ?: null,
    'address' => setting('address', '') ? ['@type'=>'PostalAddress','streetAddress'=>setting('address','')] : null,
    'priceRange' => '€€',
], fn($v) => $v !== null && $v !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
include __DIR__ . '/includes/header.php';
?>

<main>
    <!-- HERO -->
    <section class="hero container">
        <div class="hero-bg"></div>
        <div>
            <h1>Μια ισορροπημένη σχέση με το <span class="grad-text">φαγητό</span>, βήμα-βήμα.</h1>
            <p class="lede">Εξατομικευμένα προγράμματα διατροφής, βασισμένα σε δεδομένα και στην καθημερινότητά σου — όχι σε γενικές οδηγίες.</p>
            <div class="hero-actions">
                <a href="booking.php" class="btn btn-primary">Κλείσε Ραντεβού
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="services.php" class="btn btn-outline">Δες τις Υπηρεσίες</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><strong data-count="250" data-suffix="+">0</strong><span>Πελάτες σε εξέλιξη</span></div>
                <div class="hero-stat"><strong data-count="12" data-suffix=" έτη">0</strong><span>Εμπειρία</span></div>
                <div class="hero-stat"><strong data-count="4.9">0</strong><span>Μέση αξιολόγηση</span></div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="plate-ring">
                <div class="plate-core"><b>ισορροπία</b><small>σε κάθε πιάτο</small></div>
            </div>
            <span class="plate-chip c1"><i style="background:#10B981"></i> Πρωτεΐνη</span>
            <span class="plate-chip c2"><i style="background:#FFB443"></i> Υδατάνθρακες</span>
            <span class="plate-chip c3"><i style="background:#FF6B54"></i> Λαχανικά</span>
        </div>
    </section>

    <!-- MARQUEE -->
    <div class="marquee" aria-hidden="true">
        <div class="marquee-track">
            <span>Εξατομίκευση</span><span>Επιστημονική τεκμηρίωση</span><span>Online ραντεβού</span><span>Συνεχής υποστήριξη</span><span>Χωρίς στερήσεις</span><span>Ρεαλιστικοί στόχοι</span>
            <span>Εξατομίκευση</span><span>Επιστημονική τεκμηρίωση</span><span>Online ραντεβού</span><span>Συνεχής υποστήριξη</span><span>Χωρίς στερήσεις</span><span>Ρεαλιστικοί στόχοι</span>
        </div>
    </div>

    <!-- FEATURES -->
    <section class="section">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Γιατί εμάς</span>
                <h2>Μια προσέγγιση φτιαγμένη για να κρατήσει</h2>
            </div>
            <div class="features">
                <div class="feature reveal" data-tilt data-delay="1">
                    <div class="feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.4 6.6L21 10l-5 4.4L17.4 21 12 17.3 6.6 21 8 14.4 3 10l6.6-1.4z"/></svg></div>
                    <strong>Πιστοποιημένη προσέγγιση</strong>
                    <p>Επιστημονικά τεκμηριωμένες μέθοδοι, όχι μόδες.</p>
                </div>
                <div class="feature reveal" data-tilt data-delay="2">
                    <div class="feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></div>
                    <strong>Online ραντεβού</strong>
                    <p>Κλείσε ώρα μέσα σε δευτερόλεπτα.</p>
                </div>
                <div class="feature reveal" data-tilt data-delay="3">
                    <div class="feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0112 5.5 5.5 5.5 0 0121.5 12c-2.5 4.5-9.5 9-9.5 9z"/></svg></div>
                    <strong>Εξατομίκευση</strong>
                    <p>Πλάνο προσαρμοσμένο απόλυτα σε σένα.</p>
                </div>
                <div class="feature reveal" data-tilt data-delay="4">
                    <div class="feature-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M4 9h16M9 4v16"/></svg></div>
                    <strong>Ψηφιακό υλικό</strong>
                    <p>Οδηγοί & εργαλεία πάντα διαθέσιμα.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TIMELINE -->
    <section class="section section-dim">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Πώς δουλεύουμε</span>
                <h2>Τρία βήματα προς το πρόγραμμά σου</h2>
            </div>
            <div class="timeline">
                <div class="tl-step reveal" data-delay="1"><div class="tl-num">1</div><h3>Αρχική Αξιολόγηση</h3><p>Καταγράφουμε ιστορικό, συνήθειες και στόχους σε μια πρώτη συνάντηση.</p></div>
                <div class="tl-step reveal" data-delay="2"><div class="tl-num">2</div><h3>Εξατομικευμένο Πλάνο</h3><p>Σχεδιάζουμε πρόγραμμα προσαρμοσμένο στην καθημερινότητά σου.</p></div>
                <div class="tl-step reveal" data-delay="3"><div class="tl-num">3</div><h3>Συνεχής Παρακολούθηση</h3><p>Τακτικές συνεδρίες για προσαρμογές και σταθερή πρόοδο.</p></div>
            </div>
        </div>
    </section>

    <!-- FEATURED SERVICES -->
    <?php if (!empty($featured)): ?>
    <section class="section">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Δημοφιλή πακέτα</span>
                <h2>Ξεκίνα με το πακέτο που σου ταιριάζει</h2>
            </div>
            <div class="services-grid">
                <?php foreach ($featured as $s): ?>
                    <div class="service-card reveal">
                        <?php if ($s['type'] === 'session_package'): ?>
                            <div class="service-portions" aria-hidden="true">
                                <?php $max=8; $filled=min((int)$s['sessions_count'],$max); for($i=1;$i<=$max;$i++): ?>
                                    <span class="portion-dot <?= $i<=$filled?'filled':'' ?>"></span>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <span class="service-tag">E-book</span>
                        <?php endif; ?>
                        <h3><?= e($s['name']) ?></h3>
                        <p><?= e($s['description']) ?></p>
                        <div class="service-price"><?= number_format($s['price'], 0) ?>€
                            <?php if ($s['type']==='session_package'): ?><span>/ <?= (int)$s['sessions_count'] ?> συνεδρί<?= $s['sessions_count']==1?'α':'ες' ?></span><?php endif; ?>
                        </div>
                        <a href="services.php" class="btn btn-brand">Περισσότερα</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CALCULATOR -->
    <section class="section section-dim">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Δωρεάν εργαλείο</span>
                <h2>Υπολόγισε τον ΔΜΣ & τις θερμίδες σου</h2>
                <p>Μια γρήγορη πρώτη εικόνα. Για ακριβές, εξατομικευμένο πλάνο, κλείσε ραντεβού.</p>
            </div>
            <div class="calc reveal" id="calc">
                <div class="calc-form">
                    <h3>Τα στοιχεία σου</h3>
                    <p class="hint">Συμπλήρωσε και δες αποτελέσματα σε πραγματικό χρόνο.</p>

                    <div class="calc-field" style="margin-bottom:16px;">
                        <label>Φύλο</label>
                        <div class="seg" data-group="sex">
                            <button type="button" data-val="f" class="on">Γυναίκα</button>
                            <button type="button" data-val="m">Άνδρας</button>
                        </div>
                    </div>

                    <div class="calc-row">
                        <div class="calc-field"><label>Ηλικία</label><input type="number" id="age" value="30" min="14" max="100"></div>
                        <div class="calc-field"><label>Ύψος (cm)</label><input type="number" id="height" value="168" min="120" max="220"></div>
                    </div>
                    <div class="calc-field" style="margin-bottom:16px;"><label>Βάρος (kg)</label><input type="number" id="weight" value="68" min="35" max="250"></div>

                    <div class="calc-field" style="margin-bottom:16px;">
                        <label>Επίπεδο δραστηριότητας</label>
                        <div class="seg" data-group="activity">
                            <button type="button" data-val="1.2">Χαμηλό</button>
                            <button type="button" data-val="1.375" class="on">Μέτριο</button>
                            <button type="button" data-val="1.55">Υψηλό</button>
                            <button type="button" data-val="1.725">Πολύ υψηλό</button>
                        </div>
                    </div>

                    <div class="calc-field">
                        <label>Στόχος</label>
                        <div class="seg" data-group="goal">
                            <button type="button" data-val="-500">Απώλεια</button>
                            <button type="button" data-val="0" class="on">Διατήρηση</button>
                            <button type="button" data-val="400">Αύξηση</button>
                        </div>
                    </div>
                </div>

                <div class="calc-result">
                    <div class="rlabel">Δείκτης Μάζας Σώματος</div>
                    <div class="calc-big" id="bmiVal">—</div>
                    <div class="calc-bmi-cat"><i></i><span id="bmiCat">—</span></div>

                    <div class="calc-divider"></div>

                    <div class="rlabel">Θερμιδικές ανάγκες (kcal/ημέρα)</div>
                    <div class="calc-kcal">
                        <div><small>Μεταβολισμός</small><b id="bmrVal">—</b></div>
                        <div><small>Συντήρηση</small><b id="tdeeVal">—</b></div>
                        <div><small>Στόχος</small><b id="targetVal">—</b></div>
                    </div>
                    <p class="calc-note">Ενδεικτικός υπολογισμός (Mifflin-St Jeor). Δεν αποτελεί ιατρική ή διαιτολογική συμβουλή.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="section">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Τι λένε οι πελάτες</span>
                <h2>Ιστορίες προόδου</h2>
            </div>
            <div class="testimonials-grid">
                <?php foreach ([['Μ','[Όνομα]'],['Κ','[Όνομα]'],['Ν','[Όνομα]']] as $t): ?>
                <div class="testimonial-card reveal">
                    <div class="testimonial-stars">★★★★★</div>
                    <p class="testimonial-quote">"[Εντύπωση πελάτη — αντικατέστησε με πραγματική μαρτυρία μόλις έχεις διαθέσιμη.]"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><?= $t[0] ?></div>
                        <div><strong><?= $t[1] ?></strong><span>Πελάτης</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section section-dim">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">Συχνές ερωτήσεις</span>
                <h2>Ό,τι θέλεις να ξέρεις πριν ξεκινήσεις</h2>
            </div>
            <div class="faq reveal">
                <div class="faq-item"><button class="faq-q">Πόσο διαρκεί μια συνεδρία;<span class="plus"></span></button><div class="faq-a"><div class="faq-a-inner">Η αρχική αξιολόγηση διαρκεί περίπου 45–60 λεπτά, ενώ οι συνεδρίες παρακολούθησης 20–30 λεπτά ανάλογα με τις ανάγκες σου.</div></div></div>
                <div class="faq-item"><button class="faq-q">Χρειάζεται να ακολουθήσω αυστηρή δίαιτα;<span class="plus"></span></button><div class="faq-a"><div class="faq-a-inner">Όχι. Ο στόχος είναι μια βιώσιμη σχέση με το φαγητό, χωρίς στερήσεις — ένα πλάνο που ταιριάζει στη ζωή σου.</div></div></div>
                <div class="faq-item"><button class="faq-q">Γίνονται συνεδρίες εξ αποστάσεως;<span class="plus"></span></button><div class="faq-a"><div class="faq-a-inner">Ναι, υπάρχει η δυνατότητα online συνεδριών μέσω βιντεοκλήσης, με το ίδιο υλικό υποστήριξης.</div></div></div>
                <div class="faq-item"><button class="faq-q">Πώς γίνεται η πληρωμή;<span class="plus"></span></button><div class="faq-a"><div class="faq-a-inner">Με ασφάλεια μέσω κάρτας για τα ψηφιακά πακέτα, ή επιτόπου κατά τη συνεδρία. Θα λάβεις πάντα απόδειξη.</div></div></div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="section-ink">
        <div class="container cta-band reveal">
            <span class="eyebrow">Ξεκίνα σήμερα</span>
            <h2>Το πρώτο σου βήμα είναι ένα ραντεβού</h2>
            <p>Διάλεξε ημερομηνία και ώρα που σε βολεύει — τα υπόλοιπα τα χτίζουμε μαζί.</p>
            <a href="booking.php" class="btn btn-primary">Δες διαθέσιμες ώρες
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>