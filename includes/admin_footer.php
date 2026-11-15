<?php
/** Admin footer. Closes containers opened in admin_header.php. */
declare(strict_types=1);
?>
  </div><!-- /.admin-content -->
</div><!-- /.admin-main -->
<script src="<?= e(url("assets/vendor/bootstrap/bootstrap.bundle.min.js")) ?>"></script>
<script src="<?= e(url("assets/vendor/chartjs/chart.umd.min.js")) ?>"></script>
<script src="<?= e(url('assets/js/app.js') . '?v=' . (int)@filemtime(ROOT_PATH . '/assets/js/app.js')) ?>"></script>
<?php if (!empty($extraScripts)) { echo $extraScripts; } ?>
</body>
</html>
