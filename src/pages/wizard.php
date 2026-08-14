<?php
/**
 * Kompakte Einseiten-Maske: nur die nötigsten Felder.
 * Kein Eigenproduktion/Zukauf-Zweig mehr – das erzeugte PDF ist immer neutral
 * und identisch. Ein optionaler interner Nachweis (z. B. Lieferanten-DoC) wird
 * gespeichert, erscheint aber NICHT im PDF.
 */
$papers = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();
$boxes  = db()->query("SELECT * FROM boxes ORDER BY name")->fetchAll();
$prod   = producer();

$pf = $_SESSION['prefill'] ?? [];
unset($_SESSION['prefill']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contour = '';
    $internal = '';
    try {
        $contour = handle_upload('contour_file', 'contour');
    } catch (Throwable $ex) {
        flash('Stanzkontur-Upload: ' . $ex->getMessage());
    }
    // optionale Schachtel-Vorlage übernimmt Kontur/Maße
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
    try {
        $internal = handle_upload('internal_doc', 'intern');
    } catch (Throwable $ex) {
        flash('Interner Nachweis: ' . $ex->getMessage());
    }

    $doc_number = trim($_POST['doc_number'] ?? '') ?: next_doc_number();
    $stmt = db()->prepare("INSERT INTO jobs
        (mode,doc_number,product_name,article_no,length_mm,width_mm,height_mm,paper_id,
         has_lamination,supplier_doc,contour_file,batch,date_issued,signer_name,signer_role,place)
        VALUES ('self',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
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
    ]);
    $jobId = (int)db()->lastInsertId();
    $job = db()->query("SELECT * FROM jobs WHERE id=$jobId")->fetch();
    $paper = $job['paper_id'] ? (db()->query("SELECT * FROM papers WHERE id=" . (int)$job['paper_id'])->fetch() ?: []) : [];

    try {
        $pdf = generate_doc_pdf($job, $paper, $prod, [], null);
        db()->prepare("UPDATE jobs SET pdf_path=? WHERE id=?")->execute([basename($pdf), $jobId]);
        bump_doc_counter();
        flash('Konformitätserklärung „' . $doc_number . '" erstellt.');
        redirect('jobs');
    } catch (Throwable $ex) {
        flash('PDF-Erstellung fehlgeschlagen: ' . $ex->getMessage());
        redirect('jobs');
    }
}

// Vorbelegung (Neu oder Wiederholauftrag)
$v = fn(string $k, string $d = '') => e($pf[$k] ?? $d);

ob_start(); ?>
<h1>Neue Konformitätserklärung</h1>
<p class="lead">Nur das Nötigste ausfüllen – der Rest kommt automatisch aus dem Firmenprofil. Das PDF ist immer neutral.</p>

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
        <div><label>Charge / Los <span class="hint">(optional)</span><input type="text" name="batch"></label></div>
    </div>

    <label>Außenmaße in mm <span class="hint">(optional)</span></label>
    <div class="row">
        <div><input type="text" name="length_mm" value="<?= $v('length_mm') ?>" placeholder="Länge"></div>
        <div><input type="text" name="width_mm" value="<?= $v('width_mm') ?>" placeholder="Breite"></div>
        <div><input type="text" name="height_mm" value="<?= $v('height_mm') ?>" placeholder="Höhe"></div>
    </div>

    <?php if ($papers): ?>
    <label>Papier / Karton
        <select name="paper_id">
            <option value="">— keines —</option>
            <?php foreach ($papers as $pp): ?>
                <option value="<?= $pp['id'] ?>" <?= (int)($pf['paper_id'] ?? 0) === (int)$pp['id'] ? 'selected' : '' ?>>
                    <?= e($pp['name']) ?><?= $pp['manufacturer'] ? ' — ' . e($pp['manufacturer']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php else: ?>
    <div class="note">Tipp: Unter <a href="<?= url('papers') ?>">Papiere</a> einmal z. B. Invercote G hinterlegen – dann hier wählbar.</div>
    <?php endif; ?>

    <label style="font-weight:400;margin-top:12px"><input type="checkbox" name="has_lamination" value="1"> Kaschierung / Folie / Heißfolie vorhanden <span class="hint">(sonst gilt: recyclingfähige Papierverpackung)</span></label>

    <label>Stanzkontur <span class="hint">(optional, PDF/SVG/PNG – wird ins PDF eingebunden)</span>
        <input type="file" name="contour_file" accept=".pdf,.svg,.png,.jpg,.jpeg">
    </label>
    <?php if ($boxes): ?>
        <label>… oder aus Schachtel-Vorlage
            <select name="box_id">
                <option value="">— keine —</option>
                <?php foreach ($boxes as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= e($b['name']) ?> (<?= e($b['length_mm']) ?>×<?= e($b['width_mm']) ?>×<?= e($b['height_mm']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>

    <label>Interner Nachweis <span class="hint">(optional, z. B. Lieferanten-DoC bei Zukauf – bleibt intern, erscheint NICHT im PDF)</span>
        <input type="file" name="internal_doc" accept=".pdf,.png,.jpg,.jpeg">
    </label>

    <div class="row" style="margin-top:12px">
        <div><label>DoC-Nummer<input type="text" name="doc_number" value="<?= e(next_doc_number()) ?>"></label></div>
        <div><label>Ausstellungsdatum<input type="text" name="date_issued" value="<?= e(date('d.m.Y')) ?>"></label></div>
    </div>

    <div class="btn-row"><button class="btn" type="submit">✓ Erklärung erstellen</button></div>
</form>
<?php
$content = ob_get_clean();
layout('Neue Erklärung', $content);
