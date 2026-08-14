<?php
/**
 * Erzeugt das finale Konformitätserklärungs-PDF:
 *   1. Deckblatt + Erklärung + technische Doku (HTML -> mPDF)
 *   2. optional: Stanzkontur (PDF-Seiten importiert oder Bild eingebettet)
 *   3. optional: Lieferanten-DoC (PDF-Seiten importiert)  [Zukauf-Fall]
 *
 * @return string absoluter Pfad zur erzeugten PDF-Datei
 */
function generate_doc_pdf(array $job, array $paper, array $producer, array $materials, ?array $supplier): string
{
    $mpdf = new \Mpdf\Mpdf([
        'mode'        => 'utf-8',
        'format'      => 'A4',
        'margin_left' => 16,
        'margin_right' => 16,
        'margin_top'  => 18,
        'margin_bottom' => 16,
        'tempDir'     => sys_get_temp_dir(),
    ]);
    $mpdf->SetTitle('EU-Konformitätserklärung – ' . ($job['product_name'] ?: 'Faltschachtel'));
    $mpdf->SetAuthor($producer['company'] ?: 'Hersteller');

    // Hauptdokument
    ob_start();
    include __DIR__ . '/views/doc_template.php';
    $html = ob_get_clean();
    $mpdf->WriteHTML($html);

    // ── Stanzkontur anhängen ────────────────────────────────────────────
    $contour = $job['contour_file'] ? upload_path($job['contour_file']) : '';
    if ($contour && is_file($contour)) {
        try {
            if (is_pdf($contour)) {
                _append_pdf_pages($mpdf, $contour, 'Anlage: Stanzkontur');
            } elseif (is_image($contour)) {
                $mpdf->AddPage();
                $mpdf->WriteHTML('<h2 style="font-family:sans-serif;color:#14425f;">Anlage: Stanzkontur</h2>');
                $mpdf->WriteHTML('<img src="' . e($contour) . '" style="max-width:100%;max-height:230mm;">');
            }
        } catch (\Throwable $ex) {
            _append_note($mpdf, 'Stanzkontur konnte nicht eingebettet werden (' . e(basename($contour)) . '). Datei liegt separat vor.');
        }
    }

    // ── Lieferanten-DoC anhängen (Zukauf) ───────────────────────────────
    $supDoc = $job['supplier_doc'] ? upload_path($job['supplier_doc']) : '';
    if ($supDoc && is_file($supDoc) && is_pdf($supDoc)) {
        try {
            _append_pdf_pages($mpdf, $supDoc, 'Anlage: Konformitätserklärung des Lieferanten');
        } catch (\Throwable $ex) {
            _append_note($mpdf, 'Lieferanten-DoC konnte nicht eingebettet werden (' . e(basename($supDoc)) . '). Datei liegt separat vor.');
        }
    }

    if (!is_dir(PDF_DIR)) {
        mkdir(PDF_DIR, 0775, true);
    }
    $fname = preg_replace('/[^A-Za-z0-9_-]/', '_', $job['doc_number'] ?: ('DoC_' . date('Ymd_His'))) . '.pdf';
    $path = PDF_DIR . '/' . $fname;
    $mpdf->Output($path, \Mpdf\Output\Destination::FILE);
    return $path;
}

function _append_pdf_pages(\Mpdf\Mpdf $mpdf, string $file, string $heading): void
{
    $count = $mpdf->setSourceFile($file);
    // Trennseite mit Überschrift
    $mpdf->AddPage();
    $mpdf->WriteHTML('<h2 style="font-family:sans-serif;color:#14425f;">' . e($heading) . '</h2>');
    for ($i = 1; $i <= $count; $i++) {
        $tpl = $mpdf->importPage($i);
        $size = $mpdf->getTemplateSize($tpl);
        $orient = ($size['width'] > $size['height']) ? 'L' : 'P';
        $mpdf->AddPageByArray(['orientation' => $orient]);
        $mpdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
    }
}

function _append_note(\Mpdf\Mpdf $mpdf, string $text): void
{
    $mpdf->AddPage();
    $mpdf->WriteHTML('<div style="font-family:sans-serif;border:1px solid #a86400;background:#fff8ec;padding:10px;border-radius:5px;color:#7a4a00;">' . $text . '</div>');
}
