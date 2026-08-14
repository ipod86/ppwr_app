<?php
/**
 * Geführter Assistent zur Erstellung einer Konformitätserklärung.
 * Zustand liegt in $_SESSION['wiz'] = ['mode'=>, 'step'=>int, 'data'=>[]].
 */

$papers    = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();
$materials = db()->query("SELECT * FROM materials ORDER BY kind,name")->fetchAll();
$suppliers = db()->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$boxes     = db()->query("SELECT * FROM boxes ORDER BY name")->fetchAll();
$prod      = producer();

function wiz_steps(string $mode): array
{
    return $mode === 'self'
        ? ['product', 'material', 'print', 'contour', 'trace', 'review']
        : ['supplier', 'product', 'paper', 'supplierdoc', 'contour', 'trace', 'review'];
}
$STEP_LABELS = [
    'product' => 'Produkt', 'material' => 'Material', 'print' => 'Druck',
    'contour' => 'Stanzkontur', 'trace' => 'Kennzeichnung', 'review' => 'Prüfen & Erstellen',
    'supplier' => 'Lieferant', 'paper' => 'Papier', 'supplierdoc' => 'Lieferanten-DoC',
];

if (isset($_GET['reset'])) {
    unset($_SESSION['wiz']);
    redirect('wizard');
}

/* ─────────────────────────── POST-Verarbeitung ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'setmode') {
        $mode = (($_POST['mode'] ?? 'self') === 'buyin') ? 'buyin' : 'self';
        $_SESSION['wiz'] = [
            'mode' => $mode, 'step' => 0,
            'data' => [
                'product_name' => '', 'article_no' => '', 'packed_item' => '',
                'intended_use' => 'Verkaufsverpackung (Primärverpackung)',
                'length_mm' => '', 'width_mm' => '', 'height_mm' => '',
                'paper_id' => 0, 'print_method' => 'offset', 'has_lamination' => 0,
                'material_ids' => [], 'supplier_id' => 0, 'supplier_doc' => '',
                'contour_file' => '', 'batch' => '', 'production_date' => date('d.m.Y'),
                'doc_number' => next_doc_number(),
                'place' => $prod['place'] ?? '', 'date_issued' => date('d.m.Y'),
                'signer_name' => $prod['signer_name'] ?? '', 'signer_role' => $prod['signer_role'] ?? '',
            ],
        ];
        redirect('wizard');
    }

    $w = $_SESSION['wiz'] ?? null;
    if (!$w) {
        redirect('wizard');
    }
    $steps = wiz_steps($w['mode']);
    $cur = $steps[$w['step']] ?? 'review';
    $d = $w['data'];

    if ($action === 'back') {
        $w['step'] = max(0, $w['step'] - 1);
        $_SESSION['wiz'] = $w;
        redirect('wizard');
    }

    if ($action === 'generate') {
        $mode = $w['mode'];
        $supplier = null;
        if ($mode === 'buyin') {
            $supplier = $d['supplier_id'] ? db()->query("SELECT * FROM suppliers WHERE id=" . (int)$d['supplier_id'])->fetch() : null;
            $mode = ($supplier && $supplier['eu']) ? 'buyin_eu' : 'buyin_noneu';
        }
        $matIds = array_map('intval', $d['material_ids'] ?? []);
        $stmt = db()->prepare("INSERT INTO jobs
            (mode,doc_number,product_name,article_no,packed_item,intended_use,length_mm,width_mm,height_mm,
             paper_id,print_method,has_lamination,material_ids,supplier_id,supplier_doc,contour_file,
             batch,production_date,place,date_issued,signer_name,signer_role)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $mode, $d['doc_number'], $d['product_name'], $d['article_no'], $d['packed_item'], $d['intended_use'],
            $d['length_mm'], $d['width_mm'], $d['height_mm'], (int)$d['paper_id'] ?: null,
            $d['print_method'], (int)$d['has_lamination'], json_encode(array_values($matIds)),
            (int)$d['supplier_id'] ?: null, $d['supplier_doc'], $d['contour_file'],
            $d['batch'], $d['production_date'], $d['place'], $d['date_issued'], $d['signer_name'], $d['signer_role'],
        ]);
        $jobId = (int)db()->lastInsertId();
        $job = db()->query("SELECT * FROM jobs WHERE id=$jobId")->fetch();

        $paper = $job['paper_id'] ? (db()->query("SELECT * FROM papers WHERE id=" . (int)$job['paper_id'])->fetch() ?: []) : [];
        $mats = [];
        if ($matIds) {
            $in = implode(',', array_map('intval', $matIds));
            $mats = db()->query("SELECT * FROM materials WHERE id IN ($in)")->fetchAll();
        }
        try {
            $pdf = generate_doc_pdf($job, $paper, $prod, $mats, $supplier);
            db()->prepare("UPDATE jobs SET pdf_path=? WHERE id=?")->execute([basename($pdf), $jobId]);
            bump_doc_counter();
            unset($_SESSION['wiz']);
            flash('Konformitätserklärung „' . $job['doc_number'] . '" wurde erstellt.');
            redirect('jobs');
        } catch (Throwable $ex) {
            flash('PDF-Erstellung fehlgeschlagen: ' . $ex->getMessage());
            redirect('wizard');
        }
    }

    /* Normales Speichern des aktuellen Schritts */
    switch ($cur) {
        case 'product':
            foreach (['product_name', 'article_no', 'packed_item', 'intended_use', 'length_mm', 'width_mm', 'height_mm'] as $k) {
                $d[$k] = trim($_POST[$k] ?? '');
            }
            break;
        case 'material':
        case 'paper':
            $d['paper_id'] = (int)($_POST['paper_id'] ?? 0);
            break;
        case 'print':
            $d['print_method'] = in_array($_POST['print_method'] ?? '', ['offset', 'toner', 'other'], true) ? $_POST['print_method'] : 'offset';
            $d['has_lamination'] = isset($_POST['has_lamination']) ? 1 : 0;
            $d['material_ids'] = array_map('intval', $_POST['material_ids'] ?? []);
            break;
        case 'supplier':
            $d['supplier_id'] = (int)($_POST['supplier_id'] ?? 0);
            break;
        case 'supplierdoc':
            try {
                $up = handle_upload('supplier_doc', 'supdoc');
                if ($up) {
                    $d['supplier_doc'] = $up;
                }
            } catch (Throwable $ex) {
                flash('Upload: ' . $ex->getMessage());
            }
            break;
        case 'contour':
            $choice = $_POST['contour_choice'] ?? 'none';
            if ($choice === 'box' && !empty($_POST['box_id'])) {
                $b = db()->query("SELECT * FROM boxes WHERE id=" . (int)$_POST['box_id'])->fetch();
                if ($b) {
                    $d['contour_file'] = $b['contour_file'];
                    if ($d['length_mm'] === '') { $d['length_mm'] = $b['length_mm']; }
                    if ($d['width_mm'] === '') { $d['width_mm'] = $b['width_mm']; }
                    if ($d['height_mm'] === '') { $d['height_mm'] = $b['height_mm']; }
                }
            } elseif ($choice === 'upload') {
                try {
                    $up = handle_upload('contour_file', 'contour');
                    if ($up) {
                        $d['contour_file'] = $up;
                    }
                } catch (Throwable $ex) {
                    flash('Upload: ' . $ex->getMessage());
                }
            } elseif ($choice === 'none') {
                $d['contour_file'] = '';
            }
            break;
        case 'trace':
            foreach (['doc_number', 'batch', 'production_date', 'place', 'date_issued', 'signer_name', 'signer_role'] as $k) {
                $d[$k] = trim($_POST[$k] ?? '');
            }
            break;
    }
    $w['data'] = $d;
    $w['step'] = min(count($steps) - 1, $w['step'] + 1);
    $_SESSION['wiz'] = $w;
    redirect('wizard');
}

/* ─────────────────────────── GET-Darstellung ─────────────────────────── */
$w = $_SESSION['wiz'] ?? null;

ob_start();

if (!$w) {
    /* Modus-Auswahl */ ?>
    <h1>Neue Konformitätserklärung</h1>
    <p class="lead">Zuerst die Grundsituation wählen – der Assistent zeigt danach nur die passenden Felder.</p>
    <form method="post" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="setmode">
        <label class="choice">
            <input type="radio" name="mode" value="self" checked>
            <b>Wir produzieren die Schachtel selbst</b> (Offset- oder Toner-Digitaldruck).<br>
            <span class="muted">Sie sind Hersteller und stellen die Erklärung aus.</span>
        </label>
        <label class="choice">
            <input type="radio" name="mode" value="buyin">
            <b>Wir kaufen die Schachtel fertig zu</b> (z. B. Packex) und verkaufen sie unter eigenem Namen.<br>
            <span class="muted">Eigenmarken-Regel: Sie gelten rechtlich als Hersteller; Grundlage ist die Lieferanten-DoC.</span>
        </label>
        <div class="btn-row"><button class="btn" type="submit">Weiter →</button></div>
    </form>
    <?php
    $content = ob_get_clean();
    layout('Neue Erklärung', $content);
    return;
}

$steps = wiz_steps($w['mode']);
$cur = $steps[$w['step']] ?? 'review';
$d = $w['data'];
$multipart = in_array($cur, ['contour', 'supplierdoc'], true);
?>
<h1>Neue Erklärung <span class="muted" style="font-size:15px">(<?= $w['mode'] === 'self' ? 'Eigenproduktion' : 'Zukauf' ?>)</span></h1>

<ul class="steps">
<?php foreach ($steps as $i => $s): ?>
    <li class="<?= $i < $w['step'] ? 'done' : ($i === $w['step'] ? 'current' : '') ?>"><?= e($STEP_LABELS[$s]) ?></li>
<?php endforeach; ?>
</ul>

<form method="post" class="card" <?= $multipart ? 'enctype="multipart/form-data"' : '' ?>>
<?= csrf_field() ?>
<?php switch ($cur):
    case 'supplier': ?>
    <h2>Lieferant</h2>
    <?php if (!$suppliers): ?>
        <div class="note warn">Noch kein Lieferant hinterlegt. Bitte zuerst unter <a href="<?= url('suppliers') ?>">Lieferanten</a> anlegen (z. B. Packex).</div>
    <?php else: ?>
        <label>Lieferant der fertigen Schachtel
            <select name="supplier_id" required>
                <option value="">— bitte wählen —</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$d['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= e($s['name']) ?> (<?= e($s['country']) ?><?= $s['eu'] ? ', EU' : ', Nicht-EU' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="note">EU-Lieferant → Sie stützen sich auf dessen DoC. Nicht-EU → Sie werden Importeur mit erweiterten Pflichten.</div>
    <?php endif; ?>
    <?php break;

    case 'product': ?>
    <h2>Produkt &amp; Maße</h2>
    <div class="row">
        <div><label>Produkt-/Schachtelname<input type="text" name="product_name" value="<?= e($d['product_name']) ?>" placeholder="z. B. Faltschachtel Testwerkzeug"></label></div>
        <div><label>Artikel-/Auftragsnummer<input type="text" name="article_no" value="<?= e($d['article_no']) ?>"></label></div>
    </div>
    <div class="row">
        <div><label>Verpacktes Produkt<input type="text" name="packed_item" value="<?= e($d['packed_item']) ?>" placeholder="z. B. Testwerkzeug"></label></div>
        <div><label>Verwendungszweck<input type="text" name="intended_use" value="<?= e($d['intended_use']) ?>"></label></div>
    </div>
    <label>Außenmaße (mm)</label>
    <div class="row">
        <div><input type="text" name="length_mm" value="<?= e($d['length_mm']) ?>" placeholder="Länge z. B. 100"></div>
        <div><input type="text" name="width_mm" value="<?= e($d['width_mm']) ?>" placeholder="Breite z. B. 100"></div>
        <div><input type="text" name="height_mm" value="<?= e($d['height_mm']) ?>" placeholder="Höhe z. B. 100"></div>
    </div>
    <?php break;

    case 'material':
    case 'paper': ?>
    <h2>Papier / Karton</h2>
    <?php if (!$papers): ?>
        <div class="note warn">Noch kein Papier hinterlegt. Bitte zuerst unter <a href="<?= url('papers') ?>">Papiere</a> anlegen (z. B. Invercote G).</div>
    <?php else: ?>
        <label>Verwendetes Papier
            <select name="paper_id" required>
                <option value="">— bitte wählen —</option>
                <?php foreach ($papers as $pp): ?>
                    <option value="<?= $pp['id'] ?>" <?= (int)$d['paper_id'] === (int)$pp['id'] ? 'selected' : '' ?>>
                        <?= e($pp['name']) ?><?= $pp['manufacturer'] ? ' — ' . e($pp['manufacturer']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($cur === 'paper'): ?><div class="note">Beim Zukauf: die vom Lieferanten bereitgestellte Papierspezifikation hier als Papier hinterlegen/wählen.</div><?php endif; ?>
    <?php endif; ?>
    <?php break;

    case 'print': ?>
    <h2>Druck &amp; Veredelung</h2>
    <label>Druckverfahren
        <select name="print_method">
            <option value="offset" <?= $d['print_method'] === 'offset' ? 'selected' : '' ?>>Offsetdruck</option>
            <option value="toner" <?= $d['print_method'] === 'toner' ? 'selected' : '' ?>>Toner-Digitaldruck</option>
            <option value="other" <?= $d['print_method'] === 'other' ? 'selected' : '' ?>>sonstiges</option>
        </select>
    </label>
    <label><input type="checkbox" name="has_lamination" value="1" <?= $d['has_lamination'] ? 'checked' : '' ?>> Kaschierung / Folie / Heißfolienprägung vorhanden</label>
    <div class="note <?= $d['has_lamination'] ? 'warn' : '' ?>">Ohne Kaschierung/Folie bleibt die Schachtel i. d. R. recyclingfähig. Der geringe Farb-/Tonerauftrag ist dafür nicht ausschlaggebend.</div>
    <label>Verwendete Farben/Toner/Kleber <span class="hint">(Mehrfachauswahl; einmalige EuPIA-Nachweise)</span></label>
    <?php if (!$materials): ?>
        <div class="note warn">Noch nichts unter <a href="<?= url('materials') ?>">Farben/Toner/Kleber</a> hinterlegt.</div>
    <?php else: foreach ($materials as $m): ?>
        <label style="font-weight:400">
            <input type="checkbox" name="material_ids[]" value="<?= $m['id'] ?>" <?= in_array((int)$m['id'], array_map('intval', $d['material_ids'] ?? []), true) ? 'checked' : '' ?>>
            <?= e($m['name']) ?> <span class="muted">(<?= e(kind_label($m['kind'])) ?><?= $m['eupia'] ? ', EuPIA' : '' ?>)</span>
        </label>
    <?php endforeach; endif; ?>
    <?php break;

    case 'supplierdoc': ?>
    <h2>Konformitätserklärung des Lieferanten</h2>
    <p class="muted">Beim Zukauf ist die Lieferanten-DoC die materielle Grundlage. Sie wird ins finale PDF eingebunden und mit archiviert.</p>
    <label>Lieferanten-DoC (PDF)<input type="file" name="supplier_doc" accept=".pdf"></label>
    <?php if ($d['supplier_doc']): ?><div class="note">Bereits hochgeladen: <?= e($d['supplier_doc']) ?> (leer lassen = beibehalten)</div><?php endif; ?>
    <div class="note warn">Fehlt die Lieferanten-DoC, sollten Sie sie beim Lieferanten anfordern, bevor Sie unter eigenem Namen weiterverkaufen.</div>
    <?php break;

    case 'contour': ?>
    <h2>Stanzkontur (optional)</h2>
    <label class="choice"><input type="radio" name="contour_choice" value="none" checked> Keine / später</label>
    <?php if ($boxes): ?>
    <label class="choice"><input type="radio" name="contour_choice" value="box"> Aus Schachtel-Vorlage:
        <select name="box_id">
            <?php foreach ($boxes as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?> (<?= e($b['length_mm']) ?>×<?= e($b['width_mm']) ?>×<?= e($b['height_mm']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <label class="choice"><input type="radio" name="contour_choice" value="upload"> Neue Datei hochladen (PDF/SVG/PNG):
        <input type="file" name="contour_file" accept=".pdf,.svg,.png,.jpg,.jpeg">
    </label>
    <?php if ($d['contour_file']): ?><div class="note">Aktuell zugeordnet: <?= e($d['contour_file']) ?></div><?php endif; ?>
    <?php break;

    case 'trace': ?>
    <h2>Kennzeichnung &amp; Unterschrift</h2>
    <div class="row">
        <div><label>DoC-Nummer<input type="text" name="doc_number" value="<?= e($d['doc_number']) ?>"></label></div>
        <div><label>Charge / Los<input type="text" name="batch" value="<?= e($d['batch']) ?>"></label></div>
    </div>
    <div class="row">
        <div><label>Produktionsdatum<input type="text" name="production_date" value="<?= e($d['production_date']) ?>"></label></div>
        <div><label>Ausstellungsdatum<input type="text" name="date_issued" value="<?= e($d['date_issued']) ?>"></label></div>
    </div>
    <div class="row">
        <div><label>Ausstellungsort<input type="text" name="place" value="<?= e($d['place']) ?>"></label></div>
    </div>
    <div class="row">
        <div><label>Unterzeichner – Name<input type="text" name="signer_name" value="<?= e($d['signer_name']) ?>"></label></div>
        <div><label>Unterzeichner – Funktion<input type="text" name="signer_role" value="<?= e($d['signer_role']) ?>"></label></div>
    </div>
    <?php break;

    case 'review':
        $paperRow = $d['paper_id'] ? db()->query("SELECT * FROM papers WHERE id=" . (int)$d['paper_id'])->fetch() : null;
        $supRow = $d['supplier_id'] ? db()->query("SELECT * FROM suppliers WHERE id=" . (int)$d['supplier_id'])->fetch() : null;
        $matSel = [];
        foreach ($materials as $m) {
            if (in_array((int)$m['id'], array_map('intval', $d['material_ids'] ?? []), true)) {
                $matSel[] = $m['name'];
            }
        }
        ?>
    <h2>Prüfen &amp; erstellen</h2>
    <table class="summary">
        <tr><td>DoC-Nummer</td><td><?= e($d['doc_number']) ?></td></tr>
        <tr><td>Produkt</td><td><?= e($d['product_name'] ?: '—') ?> <?= $d['article_no'] ? '(' . e($d['article_no']) . ')' : '' ?></td></tr>
        <tr><td>Maße</td><td><?= e(trim($d['length_mm'] . ' × ' . $d['width_mm'] . ' × ' . $d['height_mm'])) ?> mm</td></tr>
        <tr><td>Papier</td><td><?= e($paperRow['name'] ?? '—') ?></td></tr>
        <?php if ($w['mode'] === 'self'): ?>
        <tr><td>Druck</td><td><?= e($d['print_method']) ?><?= $d['has_lamination'] ? ', mit Kaschierung' : ', ohne Kaschierung' ?></td></tr>
        <tr><td>Farben/Toner/Kleber</td><td><?= $matSel ? e(implode(', ', $matSel)) : '—' ?></td></tr>
        <?php else: ?>
        <tr><td>Lieferant</td><td><?= e($supRow['name'] ?? '—') ?><?= $supRow ? ($supRow['eu'] ? ' (EU)' : ' (Nicht-EU)') : '' ?></td></tr>
        <tr><td>Lieferanten-DoC</td><td><?= $d['supplier_doc'] ? e($d['supplier_doc']) : '<span class="pill warn">fehlt</span>' ?></td></tr>
        <?php endif; ?>
        <tr><td>Stanzkontur</td><td><?= $d['contour_file'] ? e($d['contour_file']) : '—' ?></td></tr>
        <tr><td>Unterzeichner</td><td><?= e($d['signer_name'] ?: '—') ?>, <?= e($d['signer_role']) ?></td></tr>
    </table>
    <div class="note">Mit „Erklärung erstellen" wird das PDF erzeugt (Erklärung + technische Doku + eingebundene Stanzkontur/Lieferanten-DoC) und gespeichert.</div>
    <input type="hidden" name="action" value="generate">
    <?php break;

endswitch; ?>

<div class="btn-row">
    <?php if ($w['step'] > 0): ?>
        <button class="btn secondary" type="submit" name="action" value="back" formnovalidate>← Zurück</button>
    <?php endif; ?>
    <?php if ($cur === 'review'): ?>
        <button class="btn" type="submit">✓ Erklärung erstellen</button>
    <?php else: ?>
        <button class="btn" type="submit">Weiter →</button>
    <?php endif; ?>
    <a class="muted" href="<?= url('wizard', ['reset' => 1]) ?>" style="margin-left:auto">abbrechen</a>
</div>
</form>
<?php
$content = ob_get_clean();
layout('Neue Erklärung', $content);
