<?php
/** Public footer. */
declare(strict_types=1);
?>
</main>
<footer class="ft-footer">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <span><i class="bi bi-dribbble"></i> <?= e(setting('site_title', 'Football Tracker')) ?></span>
    <span class="text-muted-ft">&copy; <?= date('Y') ?> &middot; Personal football profile &amp; match tracker</span>
  </div>
</footer>
<script src="<?= e(url("assets/vendor/bootstrap/bootstrap.bundle.min.js")) ?>"></script>
<script src="<?= e(url("assets/vendor/chartjs/chart.umd.min.js")) ?>"></script>
<script src="<?= e(url('assets/js/app.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/app.js')) ?>"></script>
<?php if (!empty($extraScripts)) { echo $extraScripts; } ?>
</body>
</html>
