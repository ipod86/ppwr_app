<?php
/**
 * HTML-Vorlage der Konformitätserklärung (wird von generate_doc_pdf gerendert).
 * Verfügbar: $job, $paper, $producer, $materials, $supplier
 */
$dim = trim(($job['length_mm'] ?: '–') . ' × ' . ($job['width_mm'] ?: '–') . ' × ' . ($job['height_mm'] ?: '–') . ' mm');
$isBuyin = in_array($job['mode'], ['buyin_eu', 'buyin_noneu'], true);
$printLabels = ['offset' => 'Offsetdruck', 'toner' => 'Toner-Digitaldruck', 'other' => 'sonstiges Verfahren'];
$printLabel = $printLabels[$job['print_method']] ?? $job['print_method'];
$grammage = trim(($paper['grammage'] ?? '') . ($paper['thickness_um'] ? ' g/m² (' . $paper['thickness_um'] . ' µm)' : ($paper['grammage'] ? ' g/m²' : '')));
$matNames = array_map(fn($m) => $m['name'] . ' (' . kind_label($m['kind']) . ')', $materials);

$modeText = [
    'self'         => 'Eigenproduktion (Sie sind Hersteller der Verpackung).',
    'buyin_eu'     => 'Zukauf bei einem EU-Lieferanten; Weiterverkauf unter eigenem Namen (Eigenmarken-/Own-Brand-Regel → Sie gelten als Hersteller).',
    'buyin_noneu'  => 'Zukauf außerhalb der EU; Sie gelten als Importeur/Hersteller und tragen die volle Verantwortung.',
][$job['mode']] ?? '';
?>
<style>
  body { font-family: sans-serif; font-size: 9.4pt; color: #1a1f2b; line-height: 1.45; }
  h1 { font-size: 19pt; color: #14425f; margin: 0 0 2px; }
  .eyebrow { color: #1f6f8b; font-size: 8pt; letter-spacing: 2px; font-weight: bold; }
  .subtitle { color: #5a6672; font-size: 10pt; margin: 0 0 2px; }
  .legalref { color: #33414f; font-size: 8.6pt; font-weight: bold; }
  .rule { border-top: 4px solid #14425f; margin: 4px 0 8px; }
  h2 { font-size: 12pt; color: #fff; background: #14425f; padding: 4px 8px; margin: 16px 0 6px; }
  h3 { font-size: 10pt; color: #14425f; border-bottom: 1px solid #cdd6de; padding-bottom: 2px; margin: 12px 0 4px; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 8px; font-size: 8.5pt; }
  th { background: #14425f; color: #fff; text-align: left; padding: 3px 6px; }
  td { border: 1px solid #cdd6de; padding: 3px 6px; vertical-align: top; }
  .lbl { color: #14425f; font-weight: bold; }
  .quote { background: #f2f6f8; border-left: 3px solid #1f6f8b; padding: 5px 10px; font-style: italic; }
  .ph { background: #eef3f6; border: 1px solid #d5e0e6; color: #315; font-family: monospace; font-size: 8.2pt; }
  .ok { color: #1a7a4b; font-weight: bold; }
  .warn { color: #a86400; font-weight: bold; }
  .na { color: #4a6b8a; font-weight: bold; }
  .small { font-size: 8pt; color: #5a6672; }
  .sigline { border-top: 1px solid #333; width: 60%; padding-top: 3px; margin-top: 26px; font-size: 8pt; color: #5a6672; }
</style>

<div class="eyebrow">EU DECLARATION OF CONFORMITY · VERPACKUNGSVERORDNUNG</div>
<h1>EU-Konformitätserklärung</h1>
<p class="subtitle">Faltschachtel <?= e($dim) ?><?= $job['product_name'] ? ' · ' . e($job['product_name']) : '' ?></p>
<p class="legalref">gemäß Artikel 39 i.&nbsp;V.&nbsp;m. Anhang VIII der Verordnung (EU) 2025/40 (PPWR)</p>
<div class="rule"></div>

<table>
  <tr><td class="lbl" style="width:32%">Konformitätserklärung Nr.</td><td><?= e($job['doc_number']) ?></td></tr>
  <tr><td class="lbl">Fallkonstellation</td><td><?= e($modeText) ?></td></tr>
</table>

<h2>Teil A — Die Konformitätserklärung (Anhang VIII PPWR)</h2>

<p><span class="lbl">1. Verpackung (Produkt/Typ/Charge):</span><br>
Maßgefertigte Faltschachtel, Außenmaß <?= e($dim) ?>, aus <?= e($paper['name'] ?? 'Karton') ?><?= $grammage ? ', ' . e($grammage) : '' ?>.<br>
Artikel-/Auftragsnr.: <?= e($job['article_no'] ?: '—') ?> · Verwendungszweck: <?= e($job['intended_use'] ?: 'Verkaufsverpackung (Primärverpackung)') ?><?= $job['packed_item'] ? ' für „' . e($job['packed_item']) . '“' : '' ?>.</p>

<p><span class="lbl">2. Hersteller (verantwortlicher Wirtschaftsakteur):</span><br>
<?= e($producer['company'] ?: '«Firmenname»') ?>, <?= e(trim(($producer['street'] ?? '') . ', ' . ($producer['zip'] ?? '') . ' ' . ($producer['city'] ?? '') . ', ' . ($producer['country'] ?? ''), ' ,')) ?><br>
Kontakt: <?= e($producer['contact'] ?: '—') ?> · USt-IdNr.: <?= e($producer['vat'] ?: '—') ?></p>

<p><span class="lbl">3. Verantwortlichkeitserklärung:</span></p>
<div class="quote">Die alleinige Verantwortung für die Ausstellung dieser Konformitätserklärung trägt der Hersteller.</div>

<p><span class="lbl">4. Gegenstand der Erklärung (Rückverfolgbarkeit):</span><br>
Faltschachtel <?= e($dim) ?> aus <?= e($paper['name'] ?? 'Karton') ?>.
<?php if (!$isBuyin): ?>
Druck: <?= e($printLabel) ?><?= $job['has_lamination'] ? ', mit Kaschierung/Folie' : ', ohne Kaschierung/Folie' ?>.
<?php else: ?>
Bezug: fertig produziert/bedruckt vom Lieferanten<?= $supplier ? ' ' . e($supplier['name']) : '' ?>.
<?php endif; ?>
Charge/Los: <?= e($job['batch'] ?: '—') ?> · Produktionsdatum: <?= e($job['production_date'] ?: '—') ?>.</p>

<p><span class="lbl">5. Konformitätserklärung im engeren Sinn:</span></p>
<div class="quote">Der oben beschriebene Gegenstand entspricht den einschlägigen Nachhaltigkeitsanforderungen der <b>Artikel 5 bis 12 der Verordnung (EU) 2025/40 (PPWR)</b>, soweit zum Zeitpunkt des Inverkehrbringens anwendbar. Die Konformität wurde im Verfahren der internen Fertigungskontrolle (Modul A) nach <b>Artikel 38 i. V. m. Anhang VII</b> nachgewiesen; die technische Dokumentation wird vorgehalten (Teil B).</div>

<p><span class="lbl">6. Angewandte harmonisierte Normen / gemeinsame Spezifikationen:</span><br>
<span class="small">Zum Ausstellungszeitpunkt liegen keine einschlägigen harmonisierten Normen bzw. gemeinsamen Spezifikationen nach Art. 36/37 PPWR vor; die Konformität wurde anhand der technischen Dokumentation (Anhang VII) nachgewiesen. Herangezogene Werkstoff-/Prüfnormen: siehe Teil B.</span></p>

<p><span class="lbl">7. Notifizierte Stelle:</span> Entfällt (Modul A – interne Fertigungskontrolle).</p>

<p><span class="lbl">8. Zusätzliche Angaben:</span>
<?php if ($isBuyin): ?>
Materielle Grundlage ist die Konformitätserklärung des Lieferanten<?= $supplier ? ' ' . e($supplier['name']) : '' ?> nebst Papierspezifikation (siehe Anlage / Teil B).
<?php else: ?>
Materialnachweis Karton sowie Lieferantenerklärungen für Farben/Toner und Kleber werden vorgehalten (Teil B / Grundlagen-Ordner).
<?php endif; ?></p>

<p style="margin-top:10px"><span class="lbl">Unterzeichnet für und im Namen von:</span> <?= e($producer['company'] ?: '«Firmenname»') ?><br>
Ort und Datum: <?= e($job['place'] ?: ($producer['place'] ?? '')) ?>, <?= e($job['date_issued'] ?: date('d.m.Y')) ?><br>
Name: <?= e($job['signer_name'] ?: ($producer['signer_name'] ?? '')) ?> · Funktion: <?= e($job['signer_role'] ?: ($producer['signer_role'] ?? '')) ?></p>
<div class="sigline">Unterschrift</div>

<h2>Teil B — Technische Dokumentation (Anhang VII PPWR)</h2>

<h3>B.1 Werkstoff / Verpackung</h3>
<table>
  <tr><td class="lbl" style="width:32%">Verpackungskategorie</td><td>Verkaufsverpackung (Primärverpackung), Faltschachtel</td></tr>
  <tr><td class="lbl">Außenmaße</td><td><?= e($dim) ?></td></tr>
  <tr><td class="lbl">Werkstoff</td><td><?= e($paper['name'] ?? '—') ?><?= ($paper['manufacturer'] ?? '') ? ' — ' . e($paper['manufacturer']) : '' ?></td></tr>
  <tr><td class="lbl">Grammatur / Dicke</td><td><?= e($grammage ?: '—') ?></td></tr>
  <?php if (($paper['structure'] ?? '')): ?><tr><td class="lbl">Schichtaufbau</td><td><?= e($paper['structure']) ?></td></tr><?php endif; ?>
  <tr><td class="lbl">Lebensmittelkontakt-Eignung</td><td><?= !empty($paper['food_contact']) ? 'ja (1935/2004, BfR XXXVI – als Materialattribut dokumentiert)' : 'nicht ausgewiesen / nicht erforderlich' ?></td></tr>
  <?php if (!$isBuyin): ?>
  <tr><td class="lbl">Druckverfahren</td><td><?= e($printLabel) ?><?= $job['has_lamination'] ? ', mit Kaschierung/Folie' : ', ohne Kaschierung/Folie/Heißfolie' ?></td></tr>
  <tr><td class="lbl">Farben/Toner/Kleber</td><td><?= $matNames ? e(implode('; ', $matNames)) : '—' ?></td></tr>
  <?php else: ?>
  <tr><td class="lbl">Lieferant</td><td><?= $supplier ? e($supplier['name'] . ' (' . $supplier['country'] . ($supplier['eu'] ? ', EU' : ', Nicht-EU') . ')') : '—' ?></td></tr>
  <tr><td class="lbl">Lieferanten-DoC</td><td><?= $job['supplier_doc'] ? 'liegt vor (siehe Anlage)' : '<span class="warn">noch einzuholen</span>' ?></td></tr>
  <?php endif; ?>
</table>

<h3>B.2 Bewertung je Nachhaltigkeitsanforderung (Art. 5–12 PPWR)</h3>
<table>
  <tr><th style="width:6%">Art.</th><th style="width:22%">Anforderung</th><th>Bewertung</th><th style="width:16%">Status</th></tr>
  <tr><td>5</td><td>Stoffe, die Anlass zur Besorgnis geben (PFAS bei Lebensmittelkontakt; Schwermetalle ≤ 100 mg/kg)</td>
      <td>Konzentrations-/Anwesenheitsanforderung – nicht mengenabhängig. <?= $isBuyin ? 'Nachweis über Lieferanten-DoC.' : 'Nachweis = einmalige EuPIA-konforme Lieferantenerklärung für Farben/Toner + Kleber.' ?></td>
      <td class="warn">1× belegen · ab 12.08.2026</td></tr>
  <tr><td>6</td><td>Recyclingfähigkeit</td>
      <td><?php if (!empty($job['has_lamination'])): ?>Kaschierung/Folie vorhanden → Recyclingfähigkeit gesondert prüfen.<?php else: ?>Faserbasiert, ohne Kaschierung/Folie → recyclingfähige Papierverpackung. Treiber wären Kaschierung/Folie/Heißfolie, nicht der Farbauftrag.<?php endif; ?></td>
      <td class="<?= empty($job['has_lamination']) ? 'ok' : 'warn' ?>"><?= empty($job['has_lamination']) ? 'i. d. R. erfüllt' : 'prüfen' ?> · 🗓 2030</td></tr>
  <tr><td>7</td><td>Mindest-Rezyklatanteil</td><td>Richtet sich primär an Kunststoffverpackungen; für Frischfaserkarton als Quote nicht anwendbar.</td><td class="na">n. a.</td></tr>
  <tr><td>8/9</td><td>Kompostierbarkeit</td><td>Für diese Verpackung nicht verpflichtend.</td><td class="na">n. a.</td></tr>
  <tr><td>10</td><td>Minimierung / Leerraum</td><td>Maßanfertigung; Leerraumquote mit realen Maßen des Packguts zu belegen.</td><td class="warn">🗓 2030</td></tr>
  <tr><td>11</td><td>Wiederverwendbarkeit</td><td>Einwegverkaufsverpackung; nicht einschlägig.</td><td class="na">n. a.</td></tr>
  <tr><td>12</td><td>Kennzeichnung</td><td>Harmonisierte Kennzeichnung anbringen, sobald Durchführungsrechtsakt vorliegt.</td><td class="warn">🗓</td></tr>
</table>

<h3>B.3 Konformitätsbewertung & Aufbewahrung</h3>
<p class="small">Verfahren: interne Fertigungskontrolle (Modul A, Anhang VII), keine notifizierte Stelle. Technische Dokumentation und Konformitätserklärung sind <b>10 Jahre</b> ab Inverkehrbringen vorzuhalten und der Marktüberwachung auf Verlangen vorzulegen.</p>

<?php if ($isBuyin): ?>
<h3>B.4 Hinweis zur Fallkonstellation Zukauf</h3>
<p class="small">Da die Schachtel unter eigenem Namen ohne Herkunftsausweis weiterverkauft wird, gilt die Eigenmarken-/Own-Brand-Regel: der weiterverkaufende Betrieb wird rechtlich als Hersteller behandelt und stellt diese Erklärung auf Basis der Lieferantenunterlagen aus. Die Konformitätserklärung des Lieferanten ist beizufügen und zu archivieren.</p>
<?php endif; ?>

<p class="small" style="margin-top:10px">Erstellt mit dem PPWR-Konformitäts-Tool. Die inhaltliche Richtigkeit der produkt- und prüfspezifischen Angaben ist vor Unterzeichnung zu verifizieren. Keine Rechtsberatung.</p>
