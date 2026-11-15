<?php
/** Public navbar. Expects $activePage from header.php. */
declare(strict_types=1);
?>
<nav class="navbar navbar-expand-lg ft-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= e(purl('index.php')) ?>">
      <i class="bi bi-dribbble"></i> <?= e(setting('site_title', 'Football Tracker')) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav"
            aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class="bi bi-list"></i>
    </button>
    <div class="collapse navbar-collapse" id="publicNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php $multi = count(players_list()) > 1; foreach (public_nav() as $item): if ((!empty($item['multi']) && !$multi) || (!empty($item['reg']) && setting('registration_open', '1') !== '1')) { continue; } ?>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === basename($item['href']) || ($activePage === 'match-details.php' && $item['href'] === 'matches.php') ? 'active' : '' ?>"
               href="<?= e(!empty($item['multi']) || !empty($item['reg']) ? url($item['href']) : purl($item['href'])) ?>"><?= e($item['label']) ?></a>
          </li>
        <?php endforeach; ?>
        <li class="nav-item ms-lg-2">
          <button class="btn btn-sm btn-outline-theme" type="button" data-theme-toggle aria-label="Switch theme (Dark, Light, Ocean)" title="Switch theme: Dark / Light / Ocean">
            <i class="bi bi-circle-half"></i>
          </button>
        </li>
        <li class="nav-item">
          <a class="btn btn-sm btn-accent" href="<?= e(url('admin/')) ?>"><i class="bi bi-lock"></i> Admin</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
