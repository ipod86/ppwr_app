<?php
/**
 * Kompletter Export als ZIP: SQLite-Datei + alle Uploads + alle erzeugten PDFs.
 * Für die 10-Jahres-Aufbewahrung außerhalb des Servers.
 *
 * Außerdem: Selbst-Update von GitHub (öffentliches Repo ipod86/ppwr_app).
 * Lädt main.zip, sichert den aktuellen Code nach data/_backup/, kopiert die
 * neue Version über die Installation (data/ bleibt dabei unberührt).
 */

const UPDATE_REPO = 'ipod86/ppwr_app';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    csrf_check();
    set_time_limit(0);
    ignore_user_abort(true); // Update zu Ende laufen lassen, auch wenn der Browser-Tab geschlossen wird

    try {
        $latest = json_decode(http_get('https://api.github.com/repos/' . UPDATE_REPO . '/commits/main', 15), true);
        $newSha = $latest['sha'] ?? '';
        if (!$newSha) {
            throw new RuntimeException('Konnte neueste Version nicht von GitHub ermitteln.');
        }

        // ZIP laden und in ein Temp-Verzeichnis entpacken
        $zipData = http_get('https://github.com/' . UPDATE_REPO . '/archive/refs/heads/main.zip', 90);
        $tmpZip = tempnam(sys_get_temp_dir(), 'ppwr_update_');
        file_put_contents($tmpZip, $zipData);
        unset($zipData);

        $tmpExtract = sys_get_temp_dir() . '/ppwr_update_' . uniqid();
        mkdir($tmpExtract, 0775, true);
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            throw new RuntimeException('ZIP-Datei konnte nicht geöffnet werden.');
        }
        $zip->extractTo($tmpExtract);
        $zip->close();
        @unlink($tmpZip);

        // GitHub verpackt den Inhalt in einen Unterordner "ppwr_app-main"
        $entries = array_values(array_diff(scandir($tmpExtract), ['.', '..']));
        $sourceDir = $tmpExtract . '/' . ($entries[0] ?? '');
        if (count($entries) !== 1 || !is_dir($sourceDir)) {
            throw new RuntimeException('Unerwarteter Inhalt im heruntergeladenen ZIP.');
        }

        // Sicherheitskopie des aktuellen Codes VOR dem Überschreiben
        $backupDir = DATA_DIR . '/_backup/vorher-' . date('Y-m-d_His');
        copy_dir_recursive(APP_ROOT, $backupDir, ['data']);

        // Neue Version über die Installation kopieren
        copy_dir_recursive($sourceDir, APP_ROOT, ['data']);
        remove_dir_recursive($tmpExtract);

        file_put_contents(DATA_DIR . '/version.txt', $newSha);
        flash('Update erfolgreich auf ' . substr($newSha, 0, 7) . ' – Sicherheitskopie des vorherigen Stands liegt in data/_backup/.');
    } catch (Throwable $ex) {
        flash('Update fehlgeschlagen: ' . $ex->getMessage());
    }
    redirect('backup');
}

if (($_GET['do'] ?? '') === '1') {
    if (!class_exists('ZipArchive')) {
        exit('PHP-Erweiterung ZipArchive ist nicht verfügbar.');
    }
    $zipPath = tempnam(sys_get_temp_dir(), 'ppwr_backup_');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        exit('Konnte kein Backup erzeugen.');
    }
    // 1. Datenbank
    if (is_file(DB_FILE)) {
        $zip->addFile(DB_FILE, 'data/app.db');
    }
    // 2. Uploads + PDFs rekursiv
    $baseLen = strlen(UPLOAD_DIR) + 1;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $rel = substr($f->getPathname(), $baseLen);
        $zip->addFile($f->getPathname(), 'data/uploads/' . $rel);
    }
    // 3. README beilegen
    $prod = producer();
    $readme = "PPWR-Tool Backup\n"
            . "Erstellt: " . date('d.m.Y H:i:s') . "\n"
            . "Firma:   " . ($prod['company'] ?? '') . "\n\n"
            . "Struktur:\n"
            . "  data/app.db          – SQLite-Datenbank (Papiere, Aufträge, Firmenprofil)\n"
            . "  data/uploads/*       – hochgeladene Datenblätter, Konformitätserklärungen, Stanzkonturen, interne Nachweise\n"
            . "  data/uploads/pdf/*   – erzeugte Kunden- und interne PDF-Erklärungen\n\n"
            . "Wiederherstellen: Ordner data/ in eine frische Installation entpacken.\n";
    $zip->addFromString('README.txt', $readme);
    $zip->close();

    $fname = 'ppwr_backup_' . date('Y-m-d') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

$versionFile = DATA_DIR . '/version.txt';
$currentSha  = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '';
$latestSha   = null;
$latestMsg   = '';
try {
    $latest    = json_decode(http_get('https://api.github.com/repos/' . UPDATE_REPO . '/commits/main', 8), true);
    $latestSha = $latest['sha'] ?? null;
    $latestMsg = trim(explode("\n", $latest['commit']['message'] ?? '')[0] ?? '');
} catch (Throwable $ex) {
    // GitHub nicht erreichbar - Seite trotzdem anzeigen, nur ohne Versionsvergleich
}
$updateAvailable = $latestSha && $latestSha !== $currentSha;

$stats = [
    'papers'  => (int)db()->query("SELECT COUNT(*) FROM papers")->fetchColumn(),
    'jobs'    => (int)db()->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'uploads' => count(glob(UPLOAD_DIR . '/*.*') ?: []),
    'pdfs'    => count(glob(PDF_DIR . '/*.pdf') ?: []),
    'dbsize'  => is_file(DB_FILE) ? filesize(DB_FILE) : 0,
];

ob_start(); ?>
<h1>Backup / Export</h1>
<p class="lead">Kompletter Datenbestand als ZIP für die 10-Jahres-Aufbewahrung – am besten monatlich herunterladen und extern sichern.</p>

<div class="card">
  <table class="summary">
    <tr><td>Papiere hinterlegt</td><td><?= $stats['papers'] ?></td></tr>
    <tr><td>Erklärungen erstellt</td><td><?= $stats['jobs'] ?></td></tr>
    <tr><td>Hochgeladene Nachweise</td><td><?= $stats['uploads'] ?></td></tr>
    <tr><td>Erzeugte PDFs</td><td><?= $stats['pdfs'] ?></td></tr>
    <tr><td>Datenbank-Größe</td><td><?= round($stats['dbsize'] / 1024, 1) ?> KB</td></tr>
  </table>
  <div class="btn-row"><a class="btn" href="<?= url('backup', ['do' => 1]) ?>">⬇︎ Backup als ZIP herunterladen</a></div>
</div>

<div class="note">Das ZIP enthält die vollständige Datenbank <b>und</b> alle hochgeladenen Dokumente und PDFs. Damit lässt sich der Stand jederzeit auf einer neuen Installation wiederherstellen – oder unabhängig vom Tool archivieren.</div>

<div class="card">
  <h3 style="margin-top:0">Update</h3>
  <p class="muted">Installierte Version: <code><?= $currentSha ? e(substr($currentSha, 0, 7)) : 'unbekannt' ?></code></p>
  <?php if ($latestSha): ?>
    <p class="muted">Neueste Version auf GitHub: <code><?= e(substr($latestSha, 0, 7)) ?></code><?= $latestMsg ? ' — ' . e($latestMsg) : '' ?></p>
    <?php if ($updateAvailable): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <div class="btn-row">
          <button class="btn" type="submit" onclick="return confirm('Auf die neueste Version aktualisieren? Eine Sicherheitskopie des aktuellen Codes wird automatisch angelegt.')">⬆︎ Update auf neueste Version</button>
        </div>
      </form>
    <?php else: ?>
      <p><span class="pill ok">Aktuell</span></p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">GitHub aktuell nicht erreichbar – Versionsvergleich nicht möglich.</p>
  <?php endif; ?>
  <div class="note">Beim Update wird der aktuelle Code vorher automatisch nach <code>data/_backup/</code> gesichert (Rollback: Ordnerinhalt zurückkopieren). <code>data/</code> selbst (Datenbank, Uploads, PDFs) bleibt beim Update immer unberührt.</div>
</div>
<?php
$content = ob_get_clean();
layout('Backup', $content);
