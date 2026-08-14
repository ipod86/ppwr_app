<?php
/**
 * Kompakte Einseiten-Maske. Erzeugt zwei PDFs:
 *   - Kunden-PDF (neutral)
 *   - internes PDF (mit Bezugsquelle + eingebettetem internem Nachweis)
 * "+ neu"-Buttons speichern die Eingaben zwischen und kehren nach dem Anlegen
 * eines Papiers/einer Vorlage automatisch hierher zurück.
 */
$papers = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();
$boxes  = db()->query("SELECT * FROM boxes ORDER BY name")->fetchAll();
$prod   = producer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'generate';

    // Zwischenspeichern + zum Anlegen wechseln (Rücksprung via return=wizard)
    if ($action === 'addpaper' || $action === 'addbox') {
        $_SESSION['prefill'] = [
            'product_name' => trim($_POST['product_name'] ?? ''),
            'article_no'   => trim($_POST['article_no'] ?? ''),
            'length_mm'    => trim($_POST['length_mm'] ?? ''),
            'width_mm'     => trim($_POST['width_mm'] ?? ''),
            'height_mm'    => trim($_POST['height_mm'] ?? ''),
            'batch'        => trim($_POST['batch'] ?? ''),
            'has_lamination' => isset($_POST['has_lamination']) ? 1 : 0,
            'internal_note' => trim($_POST['internal_note'] ?? ''),
            'doc_number'   => trim($_POST['doc_number'] ?? ''),
            'date_issued'  => trim($_POST['date_issued'] ?? ''),
            'paper_id'     => (int)($_POST['paper_id'] ?? 0),
            'box_id'       => (int)($_POST['box_id'] ?? 0),
        ];
        redirect($action === 'addpaper' ? 'papers' : 'boxes', ['return' => 'wizard']);
    }

    // ── Erzeugen ────────────────────────────────────────────────────────
    $contour = '';
    try { $contour = handle_upload('contour_file', 'contour'); }
    catch (Throwable $ex) { flash('Stanzkontur-Upload: ' . $ex->getMessage()); }

    $length = trim($_POST['length_mm'] ?? '');
    $width  = trim($_POST['width_mm'] ?? '');
    $height = trim($_POST['height_mm'] ?? '');
    if (!$contour && !empty($_POST['box_id'])) {
        $b = db()->query("SELECT * FROM boxes WHERE id=" . (int)$_POST['box_id'])->fetch();
        if ($b) {
            $contour = $b['contour_file'];
            $length = $length ?: $b['length_mm'];
            $width  = $width ?: $b['width_mm'];
            $height = $height ?: $b['height_mm'];
        }
    }
    $internal = '';
    try { $internal = handle_upload('internal_doc', 'intern'); }
    catch (Throwable $ex) { flash('Interner Nachweis: ' . $ex->getMessage()); }

    $doc_number = trim($_POST['doc_number'] ?? '') ?: next_doc_number();
    db()->prepare("INSERT INTO jobs
        (mode,doc_number,product_name,article_no,length_mm,width_mm,height_mm,paper_id,
         has_lamination,supplier_doc,contour_file,batch,date_issued,signer_name,signer_role,place,
         internal_note,minimization_note,reusable,marking_note)
        VALUES ('self',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
      ->execute([
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
        trim($_POST['internal_note'] ?? ''),
        trim($_POST['minimization_note'] ?? '') ?: 'Maßanfertigung',
        in_array($_POST['reusable'] ?? '', ['einweg', 'mehrweg'], true) ? $_POST['reusable'] : 'einweg',
        trim($_POST['marking_note'] ?? ''),
    ]);
    $jobId = (int)db()->lastInsertId();
    $job = db()->query("SELECT * FROM jobs WHERE id=$jobId")->fetch();
    $paper = $job['paper_id'] ? (db()->query("SELECT * FROM papers WHERE id=" . (int)$job['paper_id'])->fetch() ?: []) : [];

    try {
        $cust   = generate_doc_pdf($job, $paper, $prod, false);
        $intern = generate_doc_pdf($job, $paper, $prod, true);
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
    <?php if ($boxes): ?>
        <div class="row" style="align-items:flex-end">
            <div style="flex:1 1 70%"><label>… oder aus Schachtel-Vorlage
                <select name="box_id">
                    <option value="">— keine —</option>
                    <?php foreach ($boxes as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (int)($pf['box_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?> (<?= e($b['length_mm']) ?>×<?= e($b['width_mm']) ?>×<?= e($b['height_mm']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label></div>
            <div style="flex:0 0 auto"><button class="btn secondary" type="submit" name="action" value="addbox" formnovalidate>+ neue Vorlage</button></div>
        </div>
    <?php else: ?>
        <div><button class="btn secondary" type="submit" name="action" value="addbox" formnovalidate>+ Schachtel-Vorlage anlegen</button></div>
    <?php endif; ?>

    <h3 style="margin-top:18px">Interne Angaben <span class="hint">(nur fürs interne PDF – erscheinen NICHT beim Kunden)</span></h3>
    <label>Bezugsquelle / Herstellung<?= info('Nur intern: Woher stammt die Schachtel? „Eigenproduktion" oder z. B. „Zukauf Packex". Bei Behördenprüfung hilft es zu erklären, welche Nachweise (Farben/Toner-Erklärungen oder Lieferanten-DoC) einschlägig sind.') ?> <span class="hint">(z. B. „Eigenproduktion" oder „Zukauf Packex")</span>
        <input type="text" name="internal_note" value="<?= $v('internal_note') ?>">
    </label>
    <label>Interner Nachweis<?= info('Bei Zukauf: hier die Konformitätserklärung des Vorlieferanten hochladen. Wird ausschließlich ins interne PDF eingebettet und erscheint nie im Kunden-Dokument.') ?> <span class="hint">(optional, z. B. Lieferanten-DoC – nur im internen PDF)</span>
        <input type="file" name="internal_doc" accept=".pdf,.png,.jpg,.jpeg">
    </label>

    <div class="row" style="margin-top:12px">
        <div><label>DoC-Nummer<?= info('Eindeutige Nummer dieser Konformitätserklärung. Wird automatisch fortlaufend vergeben (z. B. DoC-2026-0001), kann aber überschrieben werden.') ?><input type="text" name="doc_number" value="<?= $v('doc_number', next_doc_number()) ?>"></label></div>
        <div><label>Ausstellungsdatum<?= info('Datum, an dem die Erklärung ausgestellt wird. Standard: heute.') ?><input type="text" name="date_issued" value="<?= $v('date_issued', date('d.m.Y')) ?>"></label></div>
    </div>

    <div class="btn-row"><button class="btn" type="submit" name="action" value="generate">✓ Beide PDFs erstellen</button></div>
</form>
<?php
$content = ob_get_clean();
layout('Neue Erklärung', $content);
