<?php /** @var string $content @var string $title */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><a href="<?= url('dashboard') ?>">📦 PPWR-Konformitätserklärung</a></div>
  <nav>
    <a href="<?= url('dashboard') ?>"<?= active('dashboard') ?>>Start</a>
    <a href="<?= url('wizard') ?>"<?= active('wizard') ?>>Neue Erklärung</a>
    <a href="<?= url('jobs') ?>"<?= active('jobs') ?>>Erklärungen</a>
    <span class="sep"></span>
    <a href="<?= url('papers') ?>"<?= active('papers') ?>>Papiere</a>
    <a href="<?= url('suppliers') ?>"<?= active('suppliers') ?>>Lieferanten</a>
    <a href="<?= url('documents') ?>"<?= active('documents') ?>>Dokumente</a>
    <a href="<?= url('templates') ?>"<?= active('templates') ?>>Kunden-Muster</a>
    <a href="<?= url('producer') ?>"<?= active('producer') ?>>Firmenprofil</a>
    <a href="<?= url('backup') ?>"<?= active('backup') ?>>Backup</a>
  </nav>
</header>
<main>
<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>
<?= $content ?>
</main>
<script>
// Info-Icons: Klick öffnet Tooltip (Mobil), erneuter Klick / Klick außerhalb schließt.
document.addEventListener('click', function (e) {
    var openOnes = document.querySelectorAll('.info-tip.open');
    var tip = e.target.closest ? e.target.closest('.info-tip') : null;
    // alle offenen schließen, außer dem gerade geklickten
    for (var i = 0; i < openOnes.length; i++) {
        if (openOnes[i] !== tip) { openOnes[i].classList.remove('open'); }
    }
    if (tip) {
        e.preventDefault();
        tip.classList.toggle('open');
    }
});
</script>
<footer class="foot">
  <span>PPWR-Tool · VO (EU) 2025/40 · interne Arbeitsgrundlage, keine Rechtsberatung</span>
</footer>
</body>
</html>
