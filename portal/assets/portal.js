(function () {
  'use strict';
  var t = document.getElementById('pnavToggle');
  var l = document.getElementById('pnavLinks');
  if (t && l) t.addEventListener('click', function () { l.classList.toggle('open'); });

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  var p = window.__progress;
  if (p && window.Chart && document.getElementById('progressChart')) {
    var css = getComputedStyle(document.documentElement);
    var emerald = (css.getPropertyValue('--emerald') || '#0E9488').trim();
    var violet = (css.getPropertyValue('--violet') || '#7C6CF0').trim();
    var coral = (css.getPropertyValue('--coral') || '#FF6B54').trim();
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    var ds = [
      { type:'line', label:'Βάρος (kg)', data:p.weights, borderColor:emerald, backgroundColor:'rgba(14,148,136,.12)', tension:.3, fill:true, spanGaps:true, yAxisID:'y' },
      { type:'line', label:'ΔΜΣ', data:p.bmis, borderColor:violet, tension:.3, spanGaps:true, yAxisID:'y1' }
    ];
    if (p.target) ds.push({ type:'line', label:'Στόχος (kg)', data:p.target, borderColor:coral, borderDash:[6,5], pointRadius:0, tension:0, yAxisID:'y' });
    new Chart(document.getElementById('progressChart').getContext('2d'), {
      data: { labels: p.labels, datasets: ds },
      options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{ position:'bottom', labels:{ usePointStyle:true, padding:16 } } },
        scales:{
          y:{ position:'left', title:{ display:true, text:'kg' }, grid:{ color:'rgba(0,0,0,.05)' } },
          y1:{ position:'right', title:{ display:true, text:'ΔΜΣ' }, grid:{ display:false } }
        }
      }
    });
  }
})();

// Λίστα αγορών: αποθήκευση επιλογής (persist)
(function(){
  var boxes = document.querySelectorAll('.shop-check');
  if (!boxes.length) return;
  boxes.forEach(function(cb){
    cb.addEventListener('change', function(){
      var id = this.dataset.id, checked = this.checked ? '1':'0', label = this.closest('.shop-item');
      var body = 'action=toggle_item&item_id='+encodeURIComponent(id)+'&checked='+checked+'&_csrf='+encodeURIComponent(window.__csrf||'');
      fetch('plan.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
        .then(function(r){return r.json();})
        .then(function(d){ if(d&&d.ok&&label) label.classList.toggle('done', d.checked===1); })
        .catch(function(){});
    });
  });
})();
