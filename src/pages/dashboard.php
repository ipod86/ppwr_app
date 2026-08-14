<?php
$p = producer();
$counts = [
    'papers'    => db()->query("SELECT COUNT(*) FROM papers")->fetchColumn(),
    'materials' => db()->query("SELECT COUNT(*) FROM materials")->fetchColumn(),
    'suppliers' => db()->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
    'jobs'      => db()->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
];
$profileReady = $p['company'] !== '' && $p['signer_name'] !== '';

// Offene Punkte für den "Fehlt-noch"-Balken
$openIssues = [];
if (!$profileReady) {
    $openIssues[] = ['t' => 'Firmenprofil unvollständig', 'l' => url('producer'), 'k' => 'warn'];
}
$noDoc = (int)db()->query("SELECT COUNT(*) FROM papers WHERE doc_file = ''")->fetchColumn();
if ($noDoc > 0) {
    $openIssues[] = ['t' => $noDoc . ' Papier' . ($noDoc === 1 ? '' : 'e') . ' ohne Konformitätserklärung', 'l' => url('papers'), 'k' => 'warn'];
}
$papersWithDate = db()->query("SELECT id, name, doc_valid_until FROM papers WHERE doc_valid_until != ''")->fetchAll();
$expired = 0; $soon = 0;
foreach ($papersWithDate as $pp) {
    $v = doc_validity($pp['doc_valid_until']);
    if ($v['state'] === 'expired') { $expired++; }
    elseif ($v['state'] === 'soon') { $soon++; }
}
if ($expired > 0) {
    $openIssues[] = ['t' => $expired . ' Papier-Konformitätserklärung' . ($expired === 1 ? '' : 'en') . ' abgelaufen', 'l' => url('papers'), 'k' => 'warn'];
}
if ($soon > 0) {
    $openIssues[] = ['t' => $soon . ' Konformitätserklärung' . ($soon === 1 ? '' : 'en') . ' läuft in ≤ 60 Tagen ab', 'l' => url('papers'), 'k' => 'warn'];
}

// Lieferantendokumente: Ablauf prüfen
$supDocs = db()->query("SELECT sd.valid_until, sd.label, s.name FROM supplier_docs sd JOIN suppliers s ON s.id=sd.supplier_id WHERE sd.valid_until != ''")->fetchAll();
$sExpired = 0; $sSoon = 0;
foreach ($supDocs as $sd) {
    $v = doc_validity($sd['valid_until']);
    if ($v['state'] === 'expired') { $sExpired++; }
    elseif ($v['state'] === 'soon') { $sSoon++; }
}
if ($sExpired > 0) {
    $openIssues[] = ['t' => $sExpired . ' Lieferanten-Dokument' . ($sExpired === 1 ? '' : 'e') . ' abgelaufen', 'l' => url('suppliers'), 'k' => 'warn'];
}
if ($sSoon > 0) {
    $openIssues[] = ['t' => $sSoon . ' Lieferanten-Dokument' . ($sSoon === 1 ? '' : 'e') . ' läuft in ≤ 60 Tagen ab', 'l' => url('suppliers'), 'k' => 'warn'];
}

ob_start(); ?>
<h1>Willkommen</h1>
<p class="lead">Erstellen Sie geführt eine EU-Konformitätserklärung (PPWR) für Ihre Faltschachteln – für Eigenproduktion oder Zukauf.</p>

<div class="card">
  <div class="btn-row">
    <a class="btn" href="<?= url('wizard') ?>">➕ Neue Erklärung starten</a>
    <a class="btn secondary" href="<?= url('jobs') ?>">Erklärungen &amp; Wiederholaufträge</a>
  </div>
</div>

<?php if ($openIssues): ?>
<div class="card" style="border-left:4px solid var(--warn)">
  <h3 style="margin-top:0;color:var(--warn)">⚠ Offene Punkte</h3>
  <ul style="margin:6px 0 0;padding-left:20px">
    <?php foreach ($openIssues as $i): ?>
      <li><a href="<?= $i['l'] ?>"><?= e($i['t']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php elseif ($profileReady && $counts['papers'] > 0): ?>
<div class="card" style="border-left:4px solid var(--ok)">
  <p style="margin:0;color:var(--ok)"><b>✓ Alles auf dem Laufenden</b> — keine offenen Punkte, keine ablaufenden Konformitätserklärungen.</p>
</div>
<?php endif; ?>

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
