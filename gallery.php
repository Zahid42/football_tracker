<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Gallery'; $activePage = 'gallery.php';
require __DIR__ . '/includes/header.php';
$cats = ['Match Photos', 'Team Photos', 'Tournament Photos', 'Training Photos', 'Trophies', 'Personal'];
$cat = get_str('category'); if (!in_array($cat, $cats, true)) { $cat = ''; }
$st = db()->prepare('SELECT * FROM gallery' . ($cat ? ' WHERE category = ?' : '') . ' ORDER BY photo_date DESC, id DESC');
$st->execute($cat ? [$cat] : []);
$items = $st->fetchAll();
echo public_page_title('Photo Gallery', count($items) . ' photo' . (count($items) === 1 ? '' : 's'));
?>
<div class="container py-4">
  <ul class="nav nav-pills stat-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $cat === '' ? 'active' : '' ?>" href="gallery.php">All</a></li>
    <?php foreach ($cats as $c): ?><li class="nav-item"><a class="nav-link <?= $cat === $c ? 'active' : '' ?>" href="?category=<?= urlencode($c) ?>"><?= e($c) ?></a></li><?php endforeach; ?>
  </ul>
  <?php if (!$items): ?><div class="ft-card p-4 text-center text-muted-ft">No photos here yet.</div><?php endif; ?>
  <div class="gallery-grid">
    <?php foreach ($items as $g): ?>
      <figure><a href="#" data-lightbox="<?= e(upload_url($g['image'])) ?>" data-caption="<?= e(($g['caption'] ?? '') . ($g['photo_date'] ? ' - ' . fmt_date($g['photo_date']) : '')) ?>"><img src="<?= e(upload_url($g['image'])) ?>" alt="<?= e($g['caption'] ?? '') ?>" loading="lazy"></a>
        <figcaption><b><?= e($g['caption'] ?: $g['category']) ?></b><small><?= e($g['category']) ?> &middot; <?= e(fmt_date($g['photo_date'])) ?></small></figcaption></figure>
    <?php endforeach; ?>
  </div>
</div>
<div class="modal fade" id="lightbox" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content bg-dark text-white">
  <div class="modal-body p-2 text-center"><img id="lbImg" class="img-fluid rounded" alt=""><div id="lbCap" class="mt-2 small"></div></div>
  <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button></div></div></div>
<?php
$extraScripts = '<script>document.querySelectorAll("[data-lightbox]").forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();document.getElementById("lbImg").src=a.dataset.lightbox;document.getElementById("lbCap").textContent=a.dataset.caption||"";bootstrap.Modal.getOrCreateInstance(document.getElementById("lightbox")).show();});});</script>';
require __DIR__ . '/includes/footer.php';
