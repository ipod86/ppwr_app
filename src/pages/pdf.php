<?php
/**
 * Liefert eine gespeicherte Datei aus:
 *   ?job=<id>   → erzeugtes Erklärungs-PDF (PDF_DIR)
 *   ?file=<name> → hochgeladene Datei (UPLOAD_DIR)
 * Pfad-Traversal wird durch basename() unterbunden.
 */

$path = '';
$downloadName = '';

if (($_GET['job'] ?? '') !== '') {
    $j = db()->query("SELECT j.*, p.name AS paper_name FROM jobs j LEFT JOIN papers p ON p.id=j.paper_id WHERE j.id=" . (int)$_GET['job'])->fetch();
    $col = (($_GET['v'] ?? '') === 'intern') ? 'pdf_intern' : 'pdf_path';
    if ($j && !empty($j[$col])) {
        $path = PDF_DIR . '/' . basename($j[$col]);
        // Sprechender Dateiname: Firma_Produkt_DoCNr[_intern].pdf
        $prod = producer();
        $parts = array_filter([
            safe_filename($prod['company'] ?? ''),
            safe_filename($j['product_name'] ?? ''),
            safe_filename($j['doc_number'] ?? ''),
        ]);
        $suffix = ($col === 'pdf_intern') ? '_intern' : '';
        $downloadName = (implode('_', $parts) ?: 'DoC') . $suffix . '.pdf';
    }
} elseif (($_GET['file'] ?? '') !== '') {
    $path = UPLOAD_DIR . '/' . basename((string)$_GET['file']);
    $downloadName = basename($path);
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
header('Content-Disposition: inline; filename="' . ($downloadName ?: basename($path)) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
