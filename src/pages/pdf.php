<?php
/**
 * Liefert eine gespeicherte Datei aus:
 *   ?job=<id>   → erzeugtes Erklärungs-PDF (PDF_DIR)
 *   ?file=<name> → hochgeladene Datei (UPLOAD_DIR)
 * Pfad-Traversal wird durch basename() unterbunden.
 */

$path = '';

if (($_GET['job'] ?? '') !== '') {
    $j = db()->query("SELECT pdf_path FROM jobs WHERE id=" . (int)$_GET['job'])->fetch();
    if ($j && $j['pdf_path']) {
        $path = PDF_DIR . '/' . basename($j['pdf_path']);
    }
} elseif (($_GET['file'] ?? '') !== '') {
    $path = UPLOAD_DIR . '/' . basename((string)$_GET['file']);
}

if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = [
    'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml',
][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
