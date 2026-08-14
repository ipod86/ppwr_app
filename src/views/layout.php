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
    <a href="<?= url('materials') ?>"<?= active('materials') ?>>Farben/Toner/Kleber</a>
    <a href="<?= url('suppliers') ?>"<?= active('suppliers') ?>>Lieferanten</a>
    <a href="<?= url('boxes') ?>"<?= active('boxes') ?>>Schachtel-Vorlagen</a>
    <a href="<?= url('producer') ?>"<?= active('producer') ?>>Firmenprofil</a>
  </nav>
</header>
<main>
<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>
<?= $content ?>
</main>
<footer class="foot">
  <span>PPWR-Tool · VO (EU) 2025/40 · interne Arbeitsgrundlage, keine Rechtsberatung</span>
</footer>
</body>
</html>
