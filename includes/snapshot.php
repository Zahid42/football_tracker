<?php
/** Snapshot button + scripts, included by both footers. */
declare(strict_types=1);
?>
<button type="button" class="snap-fab" id="snapFab" aria-label="Take a snapshot of this page"><i class="bi bi-camera"></i><span>Snapshot</span></button>
<script>window.FT_SNAP = <?= json_encode(['site' => setting('site_title', 'Football Tracker'), 'host' => (string)($_SERVER['HTTP_HOST'] ?? '')]) ?>;</script>
<script src="<?= e(url('assets/vendor/html2canvas/html2canvas.min.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/snapshot.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/snapshot.js')) ?>" defer></script>
