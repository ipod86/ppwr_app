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
         has_lamination,supplier_doc,contour_file,batch,date_issued,signer_name,signer_role,place,internal_note)
        VALUES ('self',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
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

<form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>

    <label>Produkt / Bezeichnung *
        <input type="text" name="product_name" value="<?= $v('product_name') ?>" required placeholder="z. B. Faltschachtel Testwerkzeug">
    </label>

    <div class="row">
        <div><label>Artikel-/Auftragsnr. <span class="hint">(optional)</span><input type="text" name="article_no" value="<?= $v('article_no') ?>"></label></div>
        <div><label>Charge / Los <span class="hint">(optional)</span><input type="text" name="batch" value="<?= $v('batch') ?>"></label></div>
    </div>

    <label>Außenmaße in mm <span class="hint">(optional)</span></label>
    <div class="row">
        <div><input type="text" name="length_mm" value="<?= $v('length_mm') ?>" placeholder="Länge"></div>
        <div><input type="text" name="width_mm" value="<?= $v('width_mm') ?>" placeholder="Breite"></div>
        <div><input type="text" name="height_mm" value="<?= $v('height_mm') ?>" placeholder="Höhe"></div>
    </div>

    <label>Papier / Karton</label>
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

    <label style="font-weight:400;margin-top:12px"><input type="checkbox" name="has_lamination" value="1" <?= !empty($pf['has_lamination']) ? 'checked' : '' ?>> Kaschierung / Folie / Heißfolie vorhanden <span class="hint">(sonst: recyclingfähige Papierverpackung)</span></label>

    <label>Stanzkontur <span class="hint">(optional, PDF/SVG/PNG – wird in beide PDFs eingebunden)</span>
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
    <label>Bezugsquelle / Herstellung <span class="hint">(z. B. „Eigenproduktion" oder „Zukauf Packex")</span>
        <input type="text" name="internal_note" value="<?= $v('internal_note') ?>">
    </label>
    <label>Interner Nachweis <span class="hint">(optional, z. B. Lieferanten-DoC – nur im internen PDF)</span>
        <input type="file" name="internal_doc" accept=".pdf,.png,.jpg,.jpeg">
    </label>

    <div class="row" style="margin-top:12px">
        <div><label>DoC-Nummer<input type="text" name="doc_number" value="<?= $v('doc_number', next_doc_number()) ?>"></label></div>
        <div><label>Ausstellungsdatum<input type="text" name="date_issued" value="<?= $v('date_issued', date('d.m.Y')) ?>"></label></div>
    </div>

    <div class="btn-row"><button class="btn" type="submit" name="action" value="generate">✓ Beide PDFs erstellen</button></div>
</form>
<?php
$content = ob_get_clean();
layout('Neue Erklärung', $content);
