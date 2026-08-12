(function () {
  'use strict';

  // ---- Mobile sidebar ----
  var sidebar = document.getElementById('sidebar');
  var menuBtn = document.getElementById('menuBtn');
  var scrim = document.getElementById('scrim');
  function closeSide() { if (sidebar) sidebar.classList.remove('open'); if (scrim) scrim.classList.remove('show'); }
  if (menuBtn) menuBtn.addEventListener('click', function () {
    sidebar.classList.toggle('open');
    scrim.classList.toggle('show');
  });
  if (scrim) scrim.addEventListener('click', closeSide);

  // ---- Confirm before destructive submit (form-level) ----
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
  // ---- Confirm on buttons that submit via form="..." attribute ----
  document.querySelectorAll('button[data-fconfirm]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      if (!window.confirm(b.getAttribute('data-fconfirm'))) e.preventDefault();
    });
  });

  // ---- Charts (dashboard) ----
  var data = window.__adminCharts;
  if (data && window.Chart) {
    var css = getComputedStyle(document.documentElement);
    var emerald = css.getPropertyValue('--emerald').trim() || '#0E9488';
    var emerald2 = css.getPropertyValue('--emerald-2').trim() || '#10B981';
    var coral = css.getPropertyValue('--coral').trim() || '#FF6B54';
    var amber = css.getPropertyValue('--amber').trim() || '#FFB443';
    var muted = css.getPropertyValue('--muted').trim() || '#6C7B76';

    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.color = muted;

    var revEl = document.getElementById('revChart');
    if (revEl) {
      var ctx = revEl.getContext('2d');
      var grad = ctx.createLinearGradient(0, 0, 0, 200);
      grad.addColorStop(0, 'rgba(16,185,129,.9)');
      grad.addColorStop(1, 'rgba(14,148,136,.35)');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.revLabels,
          datasets: [{
            label: 'Έσοδα (€)',
            data: data.revValues,
            backgroundColor: grad,
            borderRadius: 8,
            maxBarThickness: 46
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function (c) { return c.parsed.y.toLocaleString('el-GR', { minimumFractionDigits: 2 }) + ' €'; } } }
          },
          scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { callback: function (v) { return v + ' €'; } } },
            x: { grid: { display: false } }
          }
        }
      });
    }

    var apptEl = document.getElementById('apptChart');
    if (apptEl) {
      new Chart(apptEl.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Επιβεβαιωμένα', 'Σε αναμονή', 'Ακυρωμένα'],
          datasets: [{
            data: data.apptData,
            backgroundColor: [emerald2, amber, coral],
            borderWidth: 0,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false, cutout: '62%',
          plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } } }
        }
      });
    }
  }
})();
