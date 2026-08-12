</main>
<?php if (!empty($use_charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="assets/portal.js"></script>
<?php if (!empty($inline_js)): ?><script><?= $inline_js ?></script><?php endif; ?>
</body>
</html>
