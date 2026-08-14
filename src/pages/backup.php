<?php
/**
 * Kompletter Export als ZIP: SQLite-Datei + alle Uploads + alle erzeugten PDFs.
 * Für die 10-Jahres-Aufbewahrung außerhalb des Servers.
 */

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
<?php
$content = ob_get_clean();
layout('Backup', $content);
