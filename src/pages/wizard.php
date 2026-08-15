<?php
/**
 * Kompakte Einseiten-Maske. Erzeugt zwei PDFs:
 *   - Kunden-PDF (neutral)
 *   - internes PDF (mit Bezugsquelle + eingebettetem internem Nachweis)
 * Der "+ neues Papier"-Button speichert die Eingaben zwischen und kehrt
 * nach dem Anlegen automatisch hierher zurück.
 */
$papers    = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();
$suppliers = db()->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$prod      = producer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'generate';

    // Zwischenspeichern + zum Anlegen eines Papiers oder Lieferanten wechseln
    if ($action === 'addpaper' || $action === 'addsupplier') {
        $_SESSION['prefill'] = [
            'product_name' => trim($_POST['product_name'] ?? ''),
            'article_no'   => trim($_POST['article_no'] ?? ''),
            'length_mm'    => trim($_POST['length_mm'] ?? ''),
            'width_mm'     => trim($_POST['width_mm'] ?? ''),
            'height_mm'    => trim($_POST['height_mm'] ?? ''),
            'batch'        => trim($_POST['batch'] ?? ''),
            'has_lamination' => isset($_POST['has_lamination']) ? 1 : 0,
            'doc_number'   => trim($_POST['doc_number'] ?? ''),
            'date_issued'  => trim($_POST['date_issued'] ?? ''),
            'paper_id'     => (int)($_POST['paper_id'] ?? 0),
            'mode'         => (($_POST['mode'] ?? 'self') === 'buyin') ? 'buyin' : 'self',
            'supplier_id'  => (int)($_POST['supplier_id'] ?? 0),
            'minimization_note' => trim($_POST['minimization_note'] ?? ''),
            'reusable'     => $_POST['reusable'] ?? 'einweg',
            'marking_note' => trim($_POST['marking_note'] ?? ''),
        ];
        redirect($action === 'addpaper' ? 'papers' : 'suppliers', ['return' => 'wizard']);
    }

    // ── Erzeugen ────────────────────────────────────────────────────────
    $contour = '';
    try { $contour = handle_upload('contour_file', 'contour'); }
    catch (Throwable $ex) { flash('Stanzkontur-Upload: ' . $ex->getMessage()); }

    $length = trim($_POST['length_mm'] ?? '');
    $width  = trim($_POST['width_mm'] ?? '');
    $height = trim($_POST['height_mm'] ?? '');

    $internal = '';
    try { $internal = handle_upload('internal_doc', 'intern'); }
    catch (Throwable $ex) { flash('Interner Nachweis: ' . $ex->getMessage()); }

    $doc_number = trim($_POST['doc_number'] ?? '') ?: next_doc_number();
    $mode = (($_POST['mode'] ?? 'self') === 'buyin') ? 'buyin' : 'self';
    $supplierId = ($mode === 'buyin') ? ((int)($_POST['supplier_id'] ?? 0) ?: null) : null;

    // Bezugsquelle für Teil C wird automatisch aus Herstellungsart + Lieferant abgeleitet
    if ($mode === 'buyin' && $supplierId) {
        $sName = db()->query("SELECT name FROM suppliers WHERE id=$supplierId")->fetchColumn();
        $internal_note = $sName ? ('Zukauf – ' . $sName) : 'Zukauf';
    } else {
        $internal_note = 'Eigenproduktion';
    }

    // ── Pflichtprüfungen vor dem Erzeugen ────────────────────────────────
    $errors = [];
    if (($prod['company'] ?? '') === '' || ($prod['signer_name'] ?? '') === '') {
        $errors[] = 'Firmenprofil ist unvollständig (Firma/Unterzeichner fehlt) – bitte zuerst im Firmenprofil ergänzen.';
    }
    $selectedPaperId = (int)($_POST['paper_id'] ?? 0);
    if ($selectedPaperId) {
        $selectedPaper = db()->query("SELECT doc_file FROM papers WHERE id=" . $selectedPaperId)->fetch();
        if ($selectedPaper && $selectedPaper['doc_file'] === '') {
            $errors[] = 'Das gewählte Papier hat keine hinterlegte Konformitätserklärung – bitte erst bei „Papiere" nachtragen.';
        }
    }
    // Bei Fremdproduktion: muss ein Lieferant gewählt sein
    if ($mode === 'buyin' && !$supplierId) {
        $errors[] = 'Bei „Fremdproduziert" bitte einen Lieferanten auswählen (oder auf „Eigenproduktion" umstellen).';
    }
    $dupStmt = db()->prepare("SELECT COUNT(*) FROM jobs WHERE doc_number = ?");
    $dupStmt->execute([$doc_number]);
    if ((int)$dupStmt->fetchColumn() > 0) {
        $errors[] = 'DoC-Nummer „' . $doc_number . '" wird bereits verwendet – bitte eine andere Nummer vergeben.';
    }
    if ($errors) {
        $_SESSION['prefill'] = $_POST;
        flash(implode(' ', $errors));
        redirect('wizard');
    }

    db()->prepare("INSERT INTO jobs
        (mode,doc_number,product_name,article_no,length_mm,width_mm,height_mm,paper_id,
         has_lamination,supplier_doc,contour_file,batch,date_issued,signer_name,signer_role,place,
         internal_note,minimization_note,reusable,marking_note,supplier_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([
        $mode,
        $doc_number,
        trim($_POST['product_name'] ?? '') ?: 'Faltschachtel',
        trim($_POST['article_no'] ?? ''),
        $length, $width, $height,
        (int)($_POST['paper_id'] ?? 0) ?: null,
        isset($_POST['has_lamination']) ? 1 : 0,
        $internal, $contour,
        trim($_POST['batch'] ?? ''),
        trim($_POST['date_issued'] ?? '') ?: date('d.m.Y'),
        $prod['signer_name'] ?? '', $prod['signer_role'] ?? '', $prod['place'] ?? '',
        $internal_note,
        trim($_POST['minimization_note'] ?? '') ?: 'Maßanfertigung',
        in_array($_POST['reusable'] ?? '', ['einweg', 'mehrweg'], true) ? $_POST['reusable'] : 'einweg',
        trim($_POST['marking_note'] ?? ''),
        $supplierId,
    ]);
    $jobId = (int)db()->lastInsertId();
    $job = db()->query("SELECT * FROM jobs WHERE id=$jobId")->fetch();
    $paper = $job['paper_id'] ? (db()->query("SELECT * FROM papers WHERE id=" . (int)$job['paper_id'])->fetch() ?: []) : [];

    $supplierDocs = [];
    if (!empty($job['supplier_id'])) {
        $supplierDocs = db()->query("SELECT * FROM supplier_docs WHERE supplier_id=" . (int)$job['supplier_id'] . " ORDER BY id")->fetchAll();
    }

    try {
        $cust   = generate_doc_pdf($job, $paper, $prod, false, $supplierDocs);
        $intern = generate_doc_pdf($job, $paper, $prod, true,  $supplierDocs);
        db()->prepare("UPDATE jobs SET pdf_path=?, pdf_intern=? WHERE id=?")
            ->execute([basename($cust), basename($intern), $jobId]);
        bump_doc_counter();
        flash('Erklärung „' . $doc_number . '" erstellt – Kunden-PDF und internes PDF stehen bereit.');
        redirect('jobs');
    } catch (Throwable $ex) {
        flash('PDF-Erstellung fehlgeschlagen: ' . $ex->getMessage());
        redirect('jobs');
    }
}

// Vorbelegung (Neu / Wiederholauftrag / Rücksprung nach „+ neu")
$pf = $_SESSION['prefill'] ?? [];
unset($_SESSION['prefill']);
$v = fn(string $k, string $d = '') => e((string)($pf[$k] ?? $d));

ob_start(); ?>
<h1>Neue Konformitätserklärung</h1>
<p class="lead">Nur das Nötigste ausfüllen – der Rest kommt automatisch aus dem Firmenprofil. Es entstehen ein <b>neutrales Kunden-PDF</b> und ein <b>internes PDF</b>.</p>

<?php if (!$prod['company']): ?>
<div class="note warn">Bitte zuerst das <a href="<?= url('producer') ?>">Firmenprofil</a> ausfüllen – diese Daten erscheinen als Hersteller.</div>
<?php endif; ?>

<div class="note"><b>Zur Info (nur hier, nicht im PDF) – PPWR-Fristen:</b> Anforderungen an Stoffe (Art. 5) und die EU-Konformitätserklärung gelten ab <b>12.08.2026</b>; Recyclingfähigkeits­klassen sowie Minimierung/Leerraum ab <b>2030</b>; die harmonisierte Kennzeichnung greift mit dem jeweiligen Durchführungsrechtsakt.</div>

<form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>

    <label>Herstellungsart<?= info('„Eigenproduktion" = ihr druckt selbst. „Fremdproduziert" = ihr kauft die fertige Schachtel bei einem Vorlieferanten zu. Standardfall ist Eigenproduktion.') ?>
        <select name="mode" id="modeSel">
            <option value="self" <?= (($pf['mode'] ?? 'self') === 'self') ? 'selected' : '' ?>>Eigenproduktion</option>
            <option value="buyin" <?= (($pf['mode'] ?? '') === 'buyin') ? 'selected' : '' ?>>Fremdproduziert (Zukauf)</option>
        </select>
    </label>
    <div id="supplierRow" style="display:<?= (($pf['mode'] ?? '') === 'buyin') ? 'block' : 'none' ?>">
        <label>Lieferant<?= info('Wählt euren Vorlieferanten. Alle bei diesem Lieferanten hinterlegten Dokumente werden automatisch ins interne PDF eingebunden. Erscheint NICHT im Kunden-PDF.') ?></label>
        <div class="row" style="align-items:flex-end">
            <div style="flex:1 1 70%">
                <?php if (!$suppliers): ?>
                    <p class="hint" style="margin:6px 0">Noch kein Lieferant angelegt – „+ neuer Lieferant" klicken.</p>
                <?php else: ?>
                    <select name="supplier_id">
                        <option value="">— bitte wählen —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (int)($pf['supplier_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?><?= $s['eu'] ? '' : ' (Nicht-EU)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div style="flex:0 0 auto">
                <button class="btn secondary" type="submit" name="action" value="addsupplier" formnovalidate>+ neuer Lieferant</button>
            </div>
        </div>
    </div>

    <label>Produkt / Bezeichnung *<?= info('Wie soll die Schachtel im Dokument heißen? Frei wählbar. Steht später in der Erklärung als Bezeichnung des Auftrags, z. B. „Faltschachtel Testwerkzeug".') ?>
        <input type="text" name="product_name" value="<?= $v('product_name') ?>" required placeholder="z. B. Faltschachtel Testwerkzeug">
    </label>

    <div class="row">
        <div><label>Artikel-/Auftragsnr.<?= info('Eure interne Artikel- oder Auftragsnummer – hilft, das Dokument später zuzuordnen. Optional.') ?> <span class="hint">(optional)</span><input type="text" name="article_no" value="<?= $v('article_no') ?>"></label></div>
        <div><label>Charge / Los<?= info('Falls ihr in Chargen produziert, hier die Losnummer eintragen. Sorgt für Rückverfolgbarkeit. Optional.') ?> <span class="hint">(optional)</span><input type="text" name="batch" value="<?= $v('batch') ?>"></label></div>
    </div>

    <label>Außenmaße in mm<?= info('Länge × Breite × Höhe der fertigen Schachtel in Millimetern. Optional – wenn nicht bekannt, einfach leer lassen.') ?> <span class="hint">(optional)</span></label>
    <div class="row">
        <div><input type="text" name="length_mm" value="<?= $v('length_mm') ?>" placeholder="Länge"></div>
        <div><input type="text" name="width_mm" value="<?= $v('width_mm') ?>" placeholder="Breite"></div>
        <div><input type="text" name="height_mm" value="<?= $v('height_mm') ?>" placeholder="Höhe"></div>
    </div>

    <label>Papier / Karton<?= info('Aus welchem Karton wird die Schachtel gefertigt? Wähle aus der Liste – die Materialangaben werden dann automatisch übernommen. Wenn das Papier fehlt, unten „+ neues Papier" klicken.') ?></label>
    <div class="row" style="align-items:flex-end">
        <div style="flex:1 1 70%">
            <select name="paper_id">
                <option value="">— keines —</option>
                <?php foreach ($papers as $pp): ?>
                    <option value="<?= $pp['id'] ?>" <?= (int)($pf['paper_id'] ?? 0) === (int)$pp['id'] ? 'selected' : '' ?>>
                        <?= e($pp['name']) ?><?= $pp['manufacturer'] ? ' — ' . e($pp['manufacturer']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0 0 auto">
            <button class="btn secondary" type="submit" name="action" value="addpaper" formnovalidate>+ neues Papier</button>
        </div>
    </div>

    <label style="font-weight:400;margin-top:12px"><input type="checkbox" name="has_lamination" value="1" <?= !empty($pf['has_lamination']) ? 'checked' : '' ?>> Kaschierung / Folie / Heißfolie vorhanden<?= info('Nur ankreuzen, wenn die Schachtel mit Folie kaschiert, mit Heißfolienprägung veredelt oder ähnlich beschichtet ist. Solche Ausstattung senkt die Recyclingfähigkeit. Standard-Offset oder Digitaldruck ohne Beschichtung: leer lassen.') ?> <span class="hint">(sonst: recyclingfähige Papierverpackung)</span></label>

    <h4 style="margin:14px 0 4px;color:#14425f">Verpackungseigenschaften (PPWR)</h4>

    <label>Leerraum / Minimierung<?= info('PPWR Art. 10: Die Verpackung soll für ihren Inhalt richtig dimensioniert sein, damit kein unnötiger Leerraum entsteht. Bei einer Maßschachtel für ein bestimmtes Produkt genügt der Vermerk „Maßanfertigung" – das ist der Standardfall.') ?>
        <input type="text" name="minimization_note" value="<?= $v('minimization_note', 'Maßanfertigung') ?>" placeholder="Maßanfertigung">
    </label>

    <div class="row">
        <div><label>Wiederverwendbarkeit<?= info('PPWR Art. 11: Ist die Schachtel als wiederverwendbare Mehrweg-Verpackung konzipiert? Für klassische Faltschachteln ist die Antwort „Einweg" – das ist der Standardfall.') ?>
            <select name="reusable">
                <option value="einweg" <?= (($pf['reusable'] ?? 'einweg') === 'einweg') ? 'selected' : '' ?>>Einweg</option>
                <option value="mehrweg" <?= (($pf['reusable'] ?? '') === 'mehrweg') ? 'selected' : '' ?>>Mehrweg (wiederverwendbar)</option>
            </select>
        </label></div>
        <div><label>Materialkennzeichnung<?= info('PPWR Art. 12: Später kommt eine harmonisierte Kennzeichnung (Piktogramm mit Materialcode PAP 20/21/22 für Sortierung). Genaue Vorgabe steht noch aus – falls ihr sie bereits druckt, hier den Code oder „angebracht" eintragen, sonst leer lassen (Standard-Vermerk).') ?>
            <input type="text" name="marking_note" value="<?= $v('marking_note') ?>" placeholder="leer = Standard-Vermerk">
        </label></div>
    </div>

    <label>Stanzkontur<?= info('Die Stanzform der Schachtel als PDF, SVG oder Bild. Wird in beide PDFs als Anlage eingebettet. Optional – dient nur der Vollständigkeit der technischen Dokumentation.') ?> <span class="hint">(optional, PDF/SVG/PNG – wird in beide PDFs eingebunden)</span>
        <input type="file" name="contour_file" accept=".pdf,.svg,.png,.jpg,.jpeg">
    </label>

    <h3 style="margin-top:18px">Interne Angaben <span class="hint">(nur fürs interne PDF – erscheinen NICHT beim Kunden)</span></h3>
    <p class="hint" style="margin-top:-4px">Die Bezugsquelle wird automatisch aus deiner Auswahl unter „Herstellungsart" übernommen – Eigenproduktion bzw. „Zukauf – Lieferantenname". Alle beim Lieferanten hinterlegten Dokumente werden automatisch ins interne PDF eingebettet.</p>
    <label>Interner Nachweis<?= info('Optional: einmalige Zusatzdatei, die nur bei diesem Auftrag mit ins interne PDF soll (z. B. Sondererklärung, Prüfbericht). Standard-Lieferantendokumente sind schon abgedeckt.') ?> <span class="hint">(optional – nur im internen PDF)</span>
        <input type="file" name="internal_doc" accept=".pdf,.png,.jpg,.jpeg">
    </label>

    <div class="row" style="margin-top:12px">
        <div><label>DoC-Nummer<?= info('Eindeutige Nummer dieser Konformitätserklärung. Wird automatisch fortlaufend vergeben (z. B. DoC-2026-0001), kann aber überschrieben werden.') ?><input type="text" name="doc_number" value="<?= $v('doc_number', next_doc_number()) ?>"></label></div>
        <div><label>Ausstellungsdatum<?= info('Datum, an dem die Erklärung ausgestellt wird. Standard: heute.') ?><input type="text" name="date_issued" value="<?= $v('date_issued', date('d.m.Y')) ?>"></label></div>
    </div>

    <div class="btn-row"><button class="btn" type="submit" name="action" value="generate">✓ Beide PDFs erstellen</button></div>
</form>

<script>
(function () {
    var sel = document.getElementById('modeSel');
    var row = document.getElementById('supplierRow');
    if (!sel || !row) return;
    var sync = function () { row.style.display = (sel.value === 'buyin') ? 'block' : 'none'; };
    sel.addEventListener('change', sync);
    sync();
})();
</script>
<?php
$content = ob_get_clean();
layout('Neue Erklärung', $content);
