<?php
/**
 * Erzeugt ein Konformitätserklärungs-PDF.
 *   $internal = false  → Kunden-PDF (neutral): Erklärung + techn. Doku + Stanzkontur
 *   $internal = true   → internes PDF: zusätzlich Teil C (Bezugsquelle) und der
 *                        eingebettete interne Nachweis (z. B. Lieferanten-DoC)
 *
 * @return string absoluter Pfad zur erzeugten PDF-Datei
 */
function generate_doc_pdf(array $job, array $paper, array $producer, bool $internal = false, array $supplierDocs = []): string
{
    $mpdf = new \Mpdf\Mpdf([
        'mode'         => 'utf-8',
        'format'       => 'A4',
        'margin_left'  => 16,
        'margin_right' => 16,
        'margin_top'   => 18,
        'margin_bottom' => 16,
        'tempDir'      => sys_get_temp_dir(),
    ]);
    $mpdf->SetTitle('EU-Konformitätserklärung – ' . ($job['product_name'] ?: 'Faltschachtel'));
    $mpdf->SetAuthor($producer['company'] ?: 'Hersteller');

    ob_start();
    include __DIR__ . '/views/doc_template.php'; // nutzt $job, $paper, $producer, $internal
    $html = ob_get_clean();
    $mpdf->WriteHTML($html);

    // Stanzkontur (in beiden Varianten)
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

    // Nur im internen PDF: Papier-Konformitätserklärung + Datenblatt + interner Nachweis
    if ($internal) {
        $anhaenge = [
            ['file' => $paper['doc_file']  ?? '', 'label' => 'Anlage (intern): Konformitätserklärung des Papierherstellers'],
            ['file' => $paper['spec_file'] ?? '', 'label' => 'Anlage (intern): Papier-Herstellerdatenblatt'],
        ];
        foreach ($anhaenge as $a) {
            $p = $a['file'] ? upload_path($a['file']) : '';
            if (!$p || !is_file($p)) { continue; }
            try {
                if (is_pdf($p)) {
                    _append_pdf_pages($mpdf, $p, $a['label']);
                } elseif (is_image($p)) {
                    $mpdf->AddPage();
                    $mpdf->WriteHTML('<h2 style="font-family:sans-serif;color:#14425f;">' . e($a['label']) . '</h2>');
                    $mpdf->WriteHTML('<img src="' . e($p) . '" style="max-width:100%;max-height:230mm;">');
                }
            } catch (\Throwable $ex) {
                _append_note($mpdf, $a['label'] . ' konnte nicht eingebettet werden (' . e(basename($p)) . ').');
            }
        }

        $proof = $job['supplier_doc'] ? upload_path($job['supplier_doc']) : '';
        if ($proof && is_file($proof)) {
            try {
                if (is_pdf($proof)) {
                    _append_pdf_pages($mpdf, $proof, 'Anlage (intern): Nachweis / Lieferanten-DoC');
                } elseif (is_image($proof)) {
                    $mpdf->AddPage();
                    $mpdf->WriteHTML('<h2 style="font-family:sans-serif;color:#14425f;">Anlage (intern): Nachweis</h2>');
                    $mpdf->WriteHTML('<img src="' . e($proof) . '" style="max-width:100%;max-height:230mm;">');
                }
            } catch (\Throwable $ex) {
                _append_note($mpdf, 'Interner Nachweis konnte nicht eingebettet werden (' . e(basename($proof)) . ').');
            }
        }

        // Alle beim Lieferanten hinterlegten Dokumente einbetten
        foreach ($supplierDocs as $sd) {
            $p = !empty($sd['file']) ? upload_path($sd['file']) : '';
            if (!$p || !is_file($p)) { continue; }
            $label = 'Anlage (intern) – Lieferant: ' . ($sd['label'] ?: 'Nachweis');
            try {
                if (is_pdf($p)) {
                    _append_pdf_pages($mpdf, $p, $label);
                } elseif (is_image($p)) {
                    $mpdf->AddPage();
                    $mpdf->WriteHTML('<h2 style="font-family:sans-serif;color:#14425f;">' . e($label) . '</h2>');
                    $mpdf->WriteHTML('<img src="' . e($p) . '" style="max-width:100%;max-height:230mm;">');
                }
            } catch (\Throwable $ex) {
                _append_note($mpdf, $label . ' konnte nicht eingebettet werden.');
            }
        }
    }

    if (!is_dir(PDF_DIR)) {
        mkdir(PDF_DIR, 0775, true);
    }
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', $job['doc_number'] ?: ('DoC_' . date('Ymd_His')));
    // Job-ID als Präfix: verhindert, dass zwei Jobs mit (versehentlich) gleicher
    // DoC-Nummer sich gegenseitig die gespeicherte PDF überschreiben. Der
    // benutzerseitige Download-Dateiname (siehe pdf.php) bleibt davon unberührt.
    $fname = $job['id'] . '_' . $base . ($internal ? '_intern' : '') . '.pdf';
    $path = PDF_DIR . '/' . $fname;
    $mpdf->Output($path, \Mpdf\Output\Destination::FILE);
    return $path;
}

function _append_pdf_pages(\Mpdf\Mpdf $mpdf, string $file, string $heading): void
{
    $count = $mpdf->setSourceFile($file);
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
