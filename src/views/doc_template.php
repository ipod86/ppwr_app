<?php
/**
 * Neutrale, kompakte Konformitätserklärung. Immer identisch – verrät nichts
 * über Eigenproduktion oder Zukauf. Verfügbar: $job, $paper, $producer.
 */
$dim = trim(($job['length_mm'] ?: '') . ' × ' . ($job['width_mm'] ?: '') . ' × ' . ($job['height_mm'] ?: '') . ' mm', ' ×m');
$dim = ($job['length_mm'] || $job['width_mm'] || $job['height_mm'])
    ? trim($job['length_mm'] . ' × ' . $job['width_mm'] . ' × ' . $job['height_mm']) . ' mm' : '—';
$grammage = trim(($paper['grammage'] ?? '') !== '' ? $paper['grammage'] . ' g/m²' . (($paper['thickness_um'] ?? '') !== '' ? ' (' . $paper['thickness_um'] . ' µm)' : '') : '');
$addr = trim(($producer['street'] ?? '') . ', ' . ($producer['zip'] ?? '') . ' ' . ($producer['city'] ?? '') . ', ' . ($producer['country'] ?? ''), ' ,');
?>
<style>
  body { font-family: sans-serif; font-size: 9.6pt; color: #1a1f2b; line-height: 1.45; }
  h1 { font-size: 19pt; color: #14425f; margin: 0 0 2px; }
  .eyebrow { color: #1f6f8b; font-size: 8pt; letter-spacing: 2px; font-weight: bold; }
  .subtitle { color: #5a6672; font-size: 10pt; margin: 0 0 2px; }
  .legalref { color: #33414f; font-size: 8.6pt; font-weight: bold; }
  .rule { border-top: 4px solid #14425f; margin: 4px 0 8px; }
  h2 { font-size: 12pt; color: #fff; background: #14425f; padding: 4px 8px; margin: 15px 0 6px; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 8px; font-size: 8.6pt; }
  th { background: #14425f; color: #fff; text-align: left; padding: 3px 6px; }
  td { border: 1px solid #cdd6de; padding: 3px 6px; vertical-align: top; }
  .lbl { color: #14425f; font-weight: bold; }
  .quote { background: #f2f6f8; border-left: 3px solid #1f6f8b; padding: 5px 10px; font-style: italic; }
  .ok { color: #1a7a4b; font-weight: bold; } .warn { color: #a86400; font-weight: bold; } .na { color: #4a6b8a; font-weight: bold; }
  .small { font-size: 8pt; color: #5a6672; }
  .sigline { border-top: 1px solid #333; width: 60%; padding-top: 3px; margin-top: 26px; font-size: 8pt; color: #5a6672; }
</style>

<div class="eyebrow">EU DECLARATION OF CONFORMITY · VERPACKUNGSVERORDNUNG</div>
<h1>EU-Konformitätserklärung</h1>
<p class="subtitle">Faltschachtel<?= $dim !== '—' ? ' ' . e($dim) : '' ?><?= $job['product_name'] ? ' · ' . e($job['product_name']) : '' ?></p>
<p class="legalref">gemäß Artikel 39 i.&nbsp;V.&nbsp;m. Anhang VIII der Verordnung (EU) 2025/40 (PPWR)</p>
<div class="rule"></div>

<h2>Teil A — Konformitätserklärung (Anhang VIII PPWR)</h2>

<p><span class="lbl">Nr.:</span> <?= e($job['doc_number']) ?></p>

<p><span class="lbl">1. Verpackung:</span> Faltschachtel<?= $dim !== '—' ? ', Außenmaß ' . e($dim) : '' ?><?= ($paper['name'] ?? '') ? ', aus ' . e($paper['name']) . ($grammage ? ', ' . e($grammage) : '') : '' ?>.
Artikel-/Auftragsnr.: <?= e($job['article_no'] ?: '—') ?><?= $job['batch'] ? ' · Charge/Los: ' . e($job['batch']) : '' ?>.</p>

<p><span class="lbl">2. Hersteller:</span> <?= e($producer['company'] ?: '«Firmenname»') ?><?= $addr ? ', ' . e($addr) : '' ?><?= ($producer['vat'] ?? '') ? ' · USt-IdNr.: ' . e($producer['vat']) : '' ?>.</p>

<p><span class="lbl">3.</span></p>
<div class="quote">Die alleinige Verantwortung für die Ausstellung dieser Konformitätserklärung trägt der Hersteller.</div>

<p><span class="lbl">4. Gegenstand:</span> Die oben bezeichnete Faltschachtel (Verkaufs-/Primärverpackung).</p>

<p><span class="lbl">5.</span></p>
<div class="quote">Der Gegenstand der Erklärung entspricht den einschlägigen Nachhaltigkeitsanforderungen der Artikel 5 bis 12 der Verordnung (EU) 2025/40 (PPWR), soweit zum Zeitpunkt des Inverkehrbringens anwendbar. Der Nachweis erfolgte im Verfahren der internen Fertigungskontrolle (Modul A) nach Artikel 38 i. V. m. Anhang VII; die technische Dokumentation wird vorgehalten.</div>

<p><span class="lbl">6. Harmonisierte Normen:</span> <span class="small">Zum Ausstellungszeitpunkt keine einschlägigen harmonisierten Normen/gemeinsamen Spezifikationen (Art. 36/37) veröffentlicht; Nachweis über die technische Dokumentation (Anhang VII).</span></p>
<p><span class="lbl">7. Notifizierte Stelle:</span> entfällt (Modul A).</p>
<p><span class="lbl">8. Zusatz:</span> Die zugrunde liegenden Nachweise werden in der technischen Dokumentation vorgehalten.</p>

<p style="margin-top:10px"><span class="lbl">Für und im Namen von:</span> <?= e($producer['company'] ?: '«Firmenname»') ?> ·
Ort/Datum: <?= e($job['place'] ?: ($producer['place'] ?? '')) ?>, <?= e($job['date_issued'] ?: date('d.m.Y')) ?> ·
<?= e($job['signer_name'] ?: ($producer['signer_name'] ?? '')) ?><?= ($job['signer_role'] ?: ($producer['signer_role'] ?? '')) ? ', ' . e($job['signer_role'] ?: $producer['signer_role']) : '' ?></p>
<div class="sigline">Unterschrift</div>

<h2>Teil B — Technische Dokumentation (Anhang VII PPWR)</h2>
<table>
  <tr><td class="lbl" style="width:30%">Kategorie</td><td>Verkaufsverpackung (Primärverpackung), Faltschachtel</td></tr>
  <?php if ($dim !== '—'): ?><tr><td class="lbl">Außenmaße</td><td><?= e($dim) ?></td></tr><?php endif; ?>
  <tr><td class="lbl">Werkstoff</td><td><?= e($paper['name'] ?? '—') ?><?= ($paper['manufacturer'] ?? '') ? ' — ' . e($paper['manufacturer']) : '' ?><?= ($paper['structure'] ?? '') ? '; ' . e($paper['structure']) : '' ?></td></tr>
  <?php if ($grammage): ?><tr><td class="lbl">Grammatur / Dicke</td><td><?= e($grammage) ?></td></tr><?php endif; ?>
  <?php if (!empty($paper['food_contact'])): ?><tr><td class="lbl">Lebensmittelkontakt</td><td>geeignet (1935/2004, BfR XXXVI – als Materialattribut dokumentiert)</td></tr><?php endif; ?>
  <tr><td class="lbl">Recyclingfähigkeit</td><td><?= empty($job['has_lamination']) ? 'faserbasiert, ohne Kaschierung/Folie → recyclingfähige Papierverpackung' : 'Kaschierung/Folie vorhanden – gesondert zu bewerten' ?></td></tr>
</table>

<table>
  <tr><th style="width:8%">Art.</th><th style="width:30%">Anforderung</th><th>Bewertung / Status</th></tr>
  <tr><td>5</td><td>Stoffe, die Anlass zur Besorgnis geben (PFAS; Schwermetalle ≤ 100 mg/kg)</td><td><span class="warn">Nachweise vorgehalten</span> (Material- und Lieferantenunterlagen zu Farben/Toner/Kleber).</td></tr>
  <?php
    // Art. 6: Werkstoff-Aussage aus Papier + Downgrade durch Kaschierung
    $paperRecy = trim($paper['recyclable_note'] ?? '');
    if (!empty($job['has_lamination'])) {
      $art6 = '<span class="warn">gesondert zu prüfen</span> — Kaschierung/Folie vorhanden';
    } elseif ($paperRecy !== '') {
      $art6 = '<span class="ok">erfüllt</span> — Werkstoff: ' . e($paperRecy);
    } else {
      $art6 = '<span class="ok">erfüllt</span> — faserbasiert, ohne Kaschierung/Folie';
    }
    // Art. 7: Rezyklatanteil
    $rc = trim($paper['recycled_content'] ?? '');
    $art7 = $rc !== '' ? e($rc) : '<span class="na">n. a.</span> (faserbasiert)';
    // Art. 8/9: Kompostierbarkeit
    $art89 = !empty($paper['compostable'])
      ? '<span class="ok">EN 13432 zertifiziert</span>'
      : 'für diesen Verpackungstyp nicht verpflichtend';
    // Art. 10: Minimierung
    $art10 = e(trim($job['minimization_note'] ?? '') ?: 'Maßanfertigung');
    // Art. 11: Wiederverwendbarkeit
    $art11 = (($job['reusable'] ?? 'einweg') === 'mehrweg')
      ? '<span class="ok">Mehrweg</span> — wiederverwendbar konzipiert'
      : 'Einweg-Verkaufsverpackung';
    // Art. 12: Kennzeichnung – Materialcode nur reinschreiben, wenn er beim
    // Papier hinterlegt IST und beim Auftrag ausdrücklich als "aufgedruckt"
    // markiert wurde. Sonst rechtlich sicherer Standardvermerk.
    $mc = trim($paper['material_code'] ?? '');
    $marked = !empty($job['mark_material_code']);
    if ($mc !== '' && $marked) {
        $art12 = e($mc) . ' (auf der Schachtel aufgedruckt)';
    } elseif (($job['marking_note'] ?? '') !== '') {
        $art12 = e(trim($job['marking_note'])); // Fallback für Altbestand
    } else {
        $art12 = 'harmonisierte Kennzeichnung nach Vorgabe anzubringen';
    }
  ?>
  <tr><td>6</td><td>Recyclingfähigkeit</td><td><?= $art6 ?></td></tr>
  <tr><td>7</td><td>Rezyklatanteil</td><td><?= $art7 ?></td></tr>
  <tr><td>8/9</td><td>Kompostierbarkeit</td><td><?= $art89 ?></td></tr>
  <tr><td>10</td><td>Minimierung / Leerraum</td><td><?= $art10 ?></td></tr>
  <tr><td>11</td><td>Wiederverwendbarkeit</td><td><?= $art11 ?></td></tr>
  <tr><td>12</td><td>Kennzeichnung</td><td><?= $art12 ?></td></tr>
</table>

<p class="small">Verfahren: interne Fertigungskontrolle (Modul A), keine notifizierte Stelle. Technische Dokumentation und Erklärung sind 10 Jahre vorzuhalten. Arbeitshilfe, keine Rechtsberatung.</p>

<?php if (!empty($internal)): ?>
<h2 style="background:#7a4a00">Teil C — Interne Dokumentation (NICHT für den Kunden)</h2>
<table>
  <tr><td class="lbl" style="width:30%">Bezugsquelle / Herstellung</td><td><?= e($job['internal_note'] ?? '') !== '' ? e($job['internal_note']) : '— (nicht angegeben)' ?></td></tr>
  <tr><td class="lbl">Charge / Los</td><td><?= e($job['batch'] ?: '—') ?></td></tr>
  <tr><td class="lbl">Papier-Konformitätserklärung</td><td><?= !empty($paper['doc_file']) ? 'beigefügt als Anlage' : '— nicht hinterlegt' ?></td></tr>
  <tr><td class="lbl">Papier-Herstellerdatenblatt</td><td><?= !empty($paper['spec_file']) ? 'beigefügt als Anlage' : '— nicht hinterlegt' ?></td></tr>
  <tr><td class="lbl">Interner Nachweis</td><td><?= !empty($job['supplier_doc']) ? 'beigefügt als Anlage (siehe folgende Seiten)' : '— nicht hinterlegt' ?></td></tr>
  <tr><td class="lbl">Vorgehalten (Anhang VII)</td><td>Material-/Kartonnachweis; Lieferanten- bzw. Farb-/Toner-/Kleber-Konformität; ggf. Recyclingfähigkeits- und Leerraumbewertung</td></tr>
</table>
<p class="small">Dieses interne Dokument enthält alle Angaben für eine Marktüberwachungsprüfung und ist eigenständig – es benötigt kein Online-Tool. Es ist <b>nicht</b> Bestandteil des an den Kunden ausgegebenen PDFs.</p>
<?php endif; ?>
