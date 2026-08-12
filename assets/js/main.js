document.addEventListener('DOMContentLoaded', function () {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Header shadow on scroll */
    const header = document.querySelector('.site-header');
    const progress = document.getElementById('scrollProgress');
    const toTop = document.getElementById('toTop');
    const onScroll = () => {
        const y = window.scrollY;
        if (header) header.classList.toggle('is-scrolled', y > 8);
        if (progress) {
            const h = document.documentElement.scrollHeight - window.innerHeight;
            progress.style.width = (h > 0 ? (y / h) * 100 : 0) + '%';
        }
        if (toTop) toTop.classList.toggle('show', y > 500);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    if (toTop) toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }));

    /* Mobile menu */
    const toggle = document.querySelector('.nav-toggle');
    const links = document.querySelector('.nav-links');
    if (toggle && links) {
        toggle.addEventListener('click', () => {
            const open = links.classList.toggle('is-open');
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
            links.classList.remove('is-open');
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }));
    }

    /* Scroll reveal */
    const revealEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && revealEls.length && !reduce) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (en.isIntersecting) { en.target.classList.add('is-visible'); obs.unobserve(en.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });
        revealEls.forEach(el => obs.observe(el));
    } else {
        revealEls.forEach(el => el.classList.add('is-visible'));
    }

    /* Count-up numbers */
    const counters = document.querySelectorAll('[data-count]');
    if (counters.length && 'IntersectionObserver' in window && !reduce) {
        const cObs = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (!en.isIntersecting) return;
                const el = en.target;
                const target = parseFloat(el.dataset.count);
                const suffix = el.dataset.suffix || '';
                const decimals = (el.dataset.count.split('.')[1] || '').length;
                const dur = 1400; const start = performance.now();
                const tick = (now) => {
                    const p = Math.min((now - start) / dur, 1);
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = (target * eased).toFixed(decimals) + suffix;
                    if (p < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
                cObs.unobserve(el);
            });
        }, { threshold: 0.5 });
        counters.forEach(c => cObs.observe(c));
    } else {
        counters.forEach(c => c.textContent = c.dataset.count + (c.dataset.suffix || ''));
    }

    /* 3D tilt */
    if (!reduce && window.matchMedia('(hover: hover)').matches) {
        document.querySelectorAll('[data-tilt]').forEach(card => {
            const max = 8;
            card.addEventListener('mousemove', (e) => {
                const r = card.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width - .5;
                const py = (e.clientY - r.top) / r.height - .5;
                card.style.transform = `perspective(800px) rotateY(${px * max}deg) rotateX(${-py * max}deg) translateY(-6px)`;
            });
            card.addEventListener('mouseleave', () => { card.style.transform = ''; });
        });
    }

    /* FAQ accordion */
    document.querySelectorAll('.faq-item').forEach(item => {
        const q = item.querySelector('.faq-q');
        const a = item.querySelector('.faq-a');
        q.addEventListener('click', () => {
            const open = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(o => {
                o.classList.remove('open'); o.querySelector('.faq-a').style.maxHeight = null;
            });
            if (!open) { item.classList.add('open'); a.style.maxHeight = a.scrollHeight + 'px'; }
        });
    });

    /* BMI + calorie calculator */
    const calc = document.getElementById('calc');
    if (calc) {
        const $ = id => document.getElementById(id);
        let sex = 'f', activity = 1.375, goal = 0;

        calc.querySelectorAll('.seg[data-group]').forEach(seg => {
            seg.querySelectorAll('button').forEach(b => {
                b.addEventListener('click', () => {
                    seg.querySelectorAll('button').forEach(x => x.classList.remove('on'));
                    b.classList.add('on');
                    const g = seg.dataset.group, v = b.dataset.val;
                    if (g === 'sex') sex = v;
                    if (g === 'activity') activity = parseFloat(v);
                    if (g === 'goal') goal = parseInt(v, 10);
                    compute();
                });
            });
        });
        ['age', 'height', 'weight'].forEach(id => $(id).addEventListener('input', compute));

        const bmiCats = [
            { max: 18.5, label: 'Λιποβαρές', color: '#38BDF8' },
            { max: 25,   label: 'Φυσιολογικό', color: '#10B981' },
            { max: 30,   label: 'Υπέρβαρο', color: '#FFB443' },
            { max: 999,  label: 'Παχυσαρκία', color: '#FF6B54' }
        ];

        function compute() {
            const age = parseFloat($('age').value);
            const h = parseFloat($('height').value);
            const w = parseFloat($('weight').value);
            if (!age || !h || !w) return;
            const hm = h / 100;
            const bmi = w / (hm * hm);
            const cat = bmiCats.find(c => bmi < c.max);
            $('bmiVal').textContent = bmi.toFixed(1);
            $('bmiCat').textContent = cat.label;
            $('bmiCat').previousElementSibling.style.background = cat.color;
            // Mifflin-St Jeor
            const bmr = 10 * w + 6.25 * h - 5 * age + (sex === 'm' ? 5 : -161);
            const tdee = bmr * activity;
            const targetKcal = tdee + goal;
            $('bmrVal').textContent = Math.round(bmr);
            $('tdeeVal').textContent = Math.round(tdee);
            $('targetVal').textContent = Math.round(targetKcal);
        }
        compute();
    }
});
