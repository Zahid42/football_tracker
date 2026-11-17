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
<?php $tabbar = ['index.php' => ['Home', 'bi-house'], 'players.php' => ['Players', 'bi-person'], 'teams.php' => ['Teams', 'bi-shield'], 'competitions.php' => ['Tables', 'bi-trophy'], 'calendar.php' => ['Calendar', 'bi-calendar-week']]; ?>
<nav class="app-tabbar" aria-label="App navigation"><?php foreach ($tabbar as $f => [$l, $i]): $on = basename($_SERVER['SCRIPT_NAME']) === $f; ?><a class="<?= $on ? 'active' : '' ?>" href="<?= e(url($f)) ?>"><i class="bi <?= $i . ($on ? '-fill' : '') ?>"></i><span><?= $l ?></span></a><?php endforeach; ?></nav>
<button class="btn btn-accent app-install" id="appInstall" type="button" hidden><i class="bi bi-download"></i> Install app</button>
<script>window.FT_SW = <?= json_encode(url('sw.js')) ?>; window.FT_SUGGEST = <?= json_encode(url('search-suggest.php')) ?>;</script>
<script src="<?= e(url("assets/vendor/bootstrap/bootstrap.bundle.min.js")) ?>"></script>
<script src="<?= e(url("assets/vendor/chartjs/chart.umd.min.js")) ?>"></script>
<script src="<?= e(url('assets/js/app.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/app.js')) ?>"></script>
<?php require ROOT_PATH . '/includes/snapshot.php'; ?>
<?php if (!empty($extraScripts)) { echo $extraScripts; } ?>
</body>
</html>
