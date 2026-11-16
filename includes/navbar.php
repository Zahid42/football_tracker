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
      <?php if ($activePage !== 'index.php') { echo search_box('navSearch', 'nav-search ms-lg-auto my-2 my-lg-0'); } else { echo '<span class="ms-lg-auto"></span>'; } ?>
      <ul class="navbar-nav align-items-lg-center gap-lg-1 ms-lg-3">
        <?php $multi = count(players_list()) > 1; foreach (public_nav() as $item): if ((!empty($item['multi']) && !$multi) || (!empty($item['reg']) && setting('registration_open', '1') !== '1')) { continue; } ?>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === basename($item['href']) || ($activePage === 'match-details.php' && $item['href'] === 'matches.php') ? 'active' : '' ?>"
               href="<?= e(!empty($item['global']) ? url($item['href']) : purl($item['href'])) ?>"><?= e($item['label']) ?></a>
          </li>
        <?php endforeach; ?>
        <li class="nav-item ms-lg-2">
          <button class="btn btn-sm btn-outline-theme" type="button" data-theme-toggle aria-label="Switch theme (Dark, Light, Ocean)" title="Switch theme: Dark / Light / Ocean">
            <i class="bi bi-circle-half"></i>
          </button>
        </li>
        <?php if ($mem = current_member()): ?>
          <li class="nav-item"><a class="btn btn-sm btn-outline-theme<?= $activePage === 'account.php' ? ' active' : '' ?>" href="<?= e(url('account.php')) ?>"><i class="bi bi-person-circle"></i> My account</a></li>
        <?php elseif (setting('registration_open', '1') === '1'): ?>
          <li class="nav-item"><a class="btn btn-sm btn-outline-theme" href="<?= e(url('signin.php')) ?>">Sign in</a></li>
          <li class="nav-item"><a class="btn btn-sm btn-accent" href="<?= e(url('signup.php')) ?>">Sign up</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-sm btn-outline-theme" href="<?= e(url('signin.php')) ?>">Sign in</a></li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="btn btn-sm btn-outline-theme" href="<?= e(url('admin/')) ?>" title="Admin"><i class="bi bi-lock"></i><span class="d-lg-none"> Admin</span></a>
        </li>
      </ul>
    </div>
  </div>
</nav>
