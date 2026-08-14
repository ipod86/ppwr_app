<?php
/**
 * Sortimentserklärungen zum Verschicken an Kunden.
 * Erzeugt aus Firmenprofil zwei Muster-PDFs auf Wilke-Briefkopf:
 *   ?do=ppwr   – Sortiments-Konformitätserklärung (VO (EU) 2025/40)
 *   ?do=reach  – REACH-Konformitätserklärung (VO (EG) Nr. 1907/2006)
 */
$prod = producer();
$act  = $_GET['do'] ?? '';

if ($act === 'ppwr' || $act === 'reach') {
    if (!$prod['company']) {
        exit('Bitte zuerst das Firmenprofil ausfüllen.');
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4',
        'margin_left' => 22, 'margin_right' => 22, 'margin_top' => 22, 'margin_bottom' => 22,
        'tempDir' => sys_get_temp_dir(),
    ]);

    $addrLine = trim(implode(', ', array_filter([
        $prod['street'],
        trim(($prod['zip'] ?? '') . ' ' . ($prod['city'] ?? '')),
        $prod['country'],
    ])));

    $head = '<style>
        body { font-family: sans-serif; font-size: 10pt; color: #1a1f2b; line-height: 1.55; }
        .letterhead { border-bottom: 3px solid #14425f; padding-bottom: 8px; margin-bottom: 20px; }
        .letterhead .company { color: #14425f; font-size: 16pt; font-weight: bold; }
        .letterhead .addr { color: #5a6672; font-size: 9pt; margin-top: 2px; }
        .meta { color: #5a6672; font-size: 9pt; text-align: right; margin: 14px 0 20px; }
        h1 { font-size: 13pt; color: #14425f; margin: 0 0 14px; }
        p { margin: 8px 0; text-align: justify; }
        ul { margin: 8px 0 8px 20px; padding: 0; }
        li { margin: 3px 0; }
        .sig { margin-top: 40px; }
        .sigline { border-top: 1px solid #333; width: 55%; padding-top: 4px; font-size: 9pt; color: #5a6672; }
        .foot { border-top: 1px solid #cdd6de; margin-top: 30px; padding-top: 8px; font-size: 7.5pt; color: #7a8590; }
    </style>';

    $letterhead = '<div class="letterhead">
        <div class="company">' . e($prod['company']) . '</div>
        <div class="addr">' . e($addrLine)
        . ($prod['contact'] ? ' &nbsp;·&nbsp; ' . e($prod['contact']) : '')
        . ($prod['vat'] ? ' &nbsp;·&nbsp; USt-IdNr. ' . e($prod['vat']) : '')
        . '</div></div>
        <div class="meta">' . e($prod['place'] ?: ($prod['city'] ?? '')) . ', ' . date('d.m.Y') . '</div>';

    $sig = '<div class="sig">
        <p>Mit freundlichen Grüßen</p>
        <div class="sigline">' . e($prod['signer_name'] ?: '«Name»') . '<br><span style="color:#5a6672">' . e($prod['signer_role'] ?: '«Funktion»') . ' · ' . e($prod['company']) . '</span></div>
    </div>';

    if ($act === 'ppwr') {
        $body = '<h1>Konformitätserklärung – EU-Verpackungsverordnung (PPWR)</h1>
            <p>Sehr geehrte Damen und Herren,</p>
            <p>hiermit bestätigen wir Ihnen, dass die von unserem Haus produzierten <b>Faltschachteln</b> den einschlägigen Anforderungen der <b>Verordnung (EU) 2025/40 des Europäischen Parlaments und des Rates vom 19. Dezember 2024 über Verpackungen und Verpackungsabfälle (PPWR)</b> entsprechen, insbesondere den Nachhaltigkeitsanforderungen der Artikel 5 bis 12, soweit diese zum Zeitpunkt des Inverkehrbringens anwendbar sind.</p>

            <p>Die Materialkonformität stützt sich auf die schriftlichen Konformitätserklärungen unserer Vorlieferanten:</p>
            <ul>
                <li><b>Kartonagen</b>: Wir verwenden ausschließlich Kartonqualitäten europäischer Hersteller, für die uns die entsprechenden Konformitätserklärungen zur Materialsicherheit (Schwermetalle nach Richtlinie 94/62/EG bzw. VerpackG § 5, keine absichtlich zugesetzten PFAS, ggf. Eignung für Lebensmittelkontakt nach VO (EG) Nr. 1935/2004 und BfR-Empfehlung XXXVI) vorliegen.</li>
                <li><b>Druckfarben und Toner</b>: Es werden ausschließlich Systeme eingesetzt, für die uns EuPIA-konforme Konformitätserklärungen der Hersteller vorliegen.</li>
                <li><b>Klebstoffe und Lacke</b>: Es kommen nur Produkte zum Einsatz, für die uns die entsprechenden Konformitätserklärungen der Hersteller vorliegen.</li>
            </ul>

            <p>Auf Anforderung stellen wir für einzelne Aufträge eine <b>auftragsspezifische EU-Konformitätserklärung nach Artikel 39 i. V. m. Anhang VIII PPWR</b> aus. Die zugehörige technische Dokumentation nach Anhang VII wird bei uns vorgehalten und der Marktüberwachung auf Verlangen zugänglich gemacht.</p>

            <p>Änderungen der eingesetzten Vorprodukte, die die Konformität beeinflussen könnten, werden von uns fortlaufend überwacht. Diese Erklärung gilt für unser aktuelles Produktions­sortiment; sie wird bei wesentlichen Änderungen aktualisiert.</p>

            <p>Für Rückfragen stehen wir Ihnen gerne zur Verfügung.</p>';
        $filename = 'PPWR-Konformitaetserklaerung_' . preg_replace('/[^A-Za-z0-9]/', '_', $prod['company']) . '.pdf';
    } else { // reach
        $body = '<h1>Konformitätserklärung – REACH-Verordnung (EG) Nr. 1907/2006</h1>
            <p>Sehr geehrte Damen und Herren,</p>
            <p>vielen Dank für Ihre Anfrage zur Einhaltung der REACH-Verordnung. Hiermit bestätigen wir Ihnen die REACH-Konformität der von uns produzierten Erzeugnisse (Faltschachteln) nach <b>Verordnung (EG) Nr. 1907/2006</b>.</p>

            <p>Unser Haus ist <b>kein Hersteller von Grundsubstanzen</b> im Sinne der REACH-Verordnung. Wir verarbeiten und verwenden Stoffe ausschließlich im Rahmen unserer Tätigkeit als <b>nachgeschalteter Anwender (Downstream User)</b> und stellen keine registrierungspflichtigen Stoffe her bzw. importieren diese nicht.</p>

            <p>Die Lieferanten der von uns eingesetzten Erzeugnisse (Kartonagen, Druckfarben, Toner, Klebstoffe, Lacke) sind verpflichtet, uns unaufgefordert zu informieren, sofern in den gelieferten Produkten <b>besonders besorgniserregende Stoffe (SVHC)</b> in einer Konzentration <b>über 0,1 Massenprozent</b> enthalten sind. Ergänzend lassen wir uns auch im negativen Fall regelmäßig schriftlich versichern, dass keine SVHC in einer Konzentration über 0,1 Massenprozent enthalten sind.</p>

            <p>Auf Basis der uns vorliegenden Lieferanteninformationen ist festzustellen, dass in den von uns gelieferten Erzeugnissen <b>keine SVHC gemäß Artikel 33 der REACH-Verordnung in einer Konzentration über 0,1 Massenprozent</b> enthalten sind.</p>

            <p>Wir verfolgen laufend die Änderungen der REACH-Verordnung sowie der SVHC-Kandidatenliste und aktualisieren diese Erklärung entsprechend.</p>

            <p><b>Haftungsausschluss:</b> Diese Erklärung basiert auf den uns von unseren Lieferanten zur Verfügung gestellten Informationen. Da wir selbst keine chemischen Analysen der einzelnen Komponenten durchführen, wird keine über die Sorgfaltspflichten eines nachgeschalteten Anwenders hinausgehende Gewähr übernommen.</p>

            <p>Für Rückfragen stehen wir Ihnen gerne zur Verfügung.</p>';
        $filename = 'REACH-Konformitaetserklaerung_' . preg_replace('/[^A-Za-z0-9]/', '_', $prod['company']) . '.pdf';
    }

    $footer = '<div class="foot">' . e($prod['company']);
    if ($addrLine) { $footer .= ' &nbsp;·&nbsp; ' . e($addrLine); }
    if ($prod['contact']) { $footer .= ' &nbsp;·&nbsp; ' . e($prod['contact']); }
    if ($prod['vat']) { $footer .= ' &nbsp;·&nbsp; USt-IdNr. ' . e($prod['vat']); }
    $footer .= '</div>';

    $mpdf->WriteHTML($head . $letterhead . $body . $sig . $footer);
    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
    exit;
}

$profileReady = !empty($prod['company']) && !empty($prod['signer_name']);

ob_start(); ?>
<h1>Musterdokumente für Kunden</h1>
<p class="lead">Zwei allgemeine Erklärungen, die Sie Ihren Kunden auf Anfrage schicken können. Auf Basis Ihres Firmenprofils individualisiert.</p>

<?php if (!$profileReady): ?>
<div class="note warn">Bitte zuerst <a href="<?= url('producer') ?>">das Firmenprofil</a> vollständig ausfüllen (Firma, Anschrift, Unterzeichner). Die Musterdokumente werden daraus generiert.</div>
<?php else: ?>

<div class="grid">
  <div class="card">
    <h3>PPWR-Sortimentserklärung</h3>
    <p class="muted">Bestätigt Ihren Kunden allgemein, dass Ihre Faltschachteln der EU-Verpackungsverordnung (VO (EU) 2025/40) entsprechen. Verweist auf die Vorlieferantennachweise und die auftragsspezifische Erklärung nach Art. 39.</p>
    <div class="btn-row"><a class="btn" href="<?= url('templates', ['do' => 'ppwr']) ?>" target="_blank">⬇︎ PPWR-Erklärung öffnen</a></div>
  </div>
  <div class="card">
    <h3>REACH-Erklärung</h3>
    <p class="muted">Ihre Rolle als nachgeschalteter Anwender (Downstream User) nach VO (EG) Nr. 1907/2006; SVHC unter 0,1 Massenprozent. Analog zu Packex' REACH-Dokument.</p>
    <div class="btn-row"><a class="btn" href="<?= url('templates', ['do' => 'reach']) ?>" target="_blank">⬇︎ REACH-Erklärung öffnen</a></div>
  </div>
</div>

<div class="note">Die Dokumente werden aus Ihrem Firmenprofil live erzeugt: Firma, Anschrift, Kontakt, Unterzeichner und Datum. Bei Änderungen im Profil einfach die Erklärung neu öffnen — sie ist immer aktuell.</div>

<?php endif; ?>
<?php
$content = ob_get_clean();
layout('Musterdokumente', $content);
