<?php
$p = producer();
$counts = [
    'papers'    => db()->query("SELECT COUNT(*) FROM papers")->fetchColumn(),
    'materials' => db()->query("SELECT COUNT(*) FROM materials")->fetchColumn(),
    'suppliers' => db()->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
    'jobs'      => db()->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
];
$profileReady = $p['company'] !== '' && $p['signer_name'] !== '';

ob_start(); ?>
<h1>Willkommen</h1>
<p class="lead">Erstellen Sie geführt eine EU-Konformitätserklärung (PPWR) für Ihre Faltschachteln – für Eigenproduktion oder Zukauf.</p>

<div class="card">
  <div class="btn-row">
    <a class="btn" href="<?= url('wizard') ?>">➕ Neue Erklärung starten</a>
    <a class="btn secondary" href="<?= url('jobs') ?>">Erklärungen &amp; Wiederholaufträge</a>
  </div>
</div>

<h2>Einrichtung (einmalig)</h2>
<div class="grid">
  <div class="card">
    <h3>1 · Firmenprofil</h3>
    <p class="muted">Ihre Daten als Hersteller – stehen dann automatisch in jeder Erklärung.</p>
    <p><?= $profileReady ? '<span class="pill ok">eingerichtet</span>' : '<span class="pill warn">noch offen</span>' ?></p>
    <a href="<?= url('producer') ?>">Profil bearbeiten →</a>
  </div>
  <div class="card">
    <h3>2 · Papiere <span class="hint">(optional)</span></h3>
    <p class="muted">Materialdatenblätter (z. B. Invercote G) einmal hinterlegen – dann per Dropdown wählbar.</p>
    <p><span class="pill <?= $counts['papers'] ? 'ok' : 'na' ?>"><?= (int)$counts['papers'] ?> hinterlegt</span></p>
    <a href="<?= url('papers') ?>">Papiere verwalten →</a>
  </div>
</div>

<div class="note">Nur das Firmenprofil ist Pflicht. Danach genügt für jede Erklärung eine kurze Eingabemaske – bei Wiederholaufträgen in unter einer Minute.</div>
<?php
$content = ob_get_clean();
layout('Start · ' . APP_NAME, $content);
