<?php
if (($_GET['del'] ?? '') !== '') {
    $j = db()->query("SELECT pdf_path FROM jobs WHERE id=" . (int)$_GET['del'])->fetch();
    if ($j && $j['pdf_path'] && is_file(PDF_DIR . '/' . $j['pdf_path'])) {
        @unlink(PDF_DIR . '/' . $j['pdf_path']);
    }
    db()->prepare("DELETE FROM jobs WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Erklärung gelöscht.');
    redirect('jobs');
}

/* Wiederholauftrag: bestehende Erklärung als Vorlage in den Assistenten laden */
if (($_GET['repeat'] ?? '') !== '') {
    $j = db()->query("SELECT * FROM jobs WHERE id=" . (int)$_GET['repeat'])->fetch();
    if ($j) {
        $mode = str_starts_with($j['mode'], 'buyin') ? 'buyin' : 'self';
        $steps = ($mode === 'self')
            ? ['product', 'material', 'print', 'contour', 'trace', 'review']
            : ['supplier', 'product', 'paper', 'supplierdoc', 'contour', 'trace', 'review'];
        $_SESSION['wiz'] = [
            'mode' => $mode,
            'step' => array_search('trace', $steps, true), // direkt zu Kennzeichnung springen
            'data' => [
                'product_name' => $j['product_name'], 'article_no' => $j['article_no'],
                'packed_item' => $j['packed_item'], 'intended_use' => $j['intended_use'],
                'length_mm' => $j['length_mm'], 'width_mm' => $j['width_mm'], 'height_mm' => $j['height_mm'],
                'paper_id' => (int)$j['paper_id'], 'print_method' => $j['print_method'],
                'has_lamination' => (int)$j['has_lamination'],
                'material_ids' => json_decode($j['material_ids'] ?: '[]', true) ?: [],
                'supplier_id' => (int)$j['supplier_id'], 'supplier_doc' => $j['supplier_doc'],
                'contour_file' => $j['contour_file'], 'batch' => '', // Charge pro Auftrag neu
                'production_date' => date('d.m.Y'),
                'doc_number' => next_doc_number(), // neue Nummer
                'place' => $j['place'], 'date_issued' => date('d.m.Y'),
                'signer_name' => $j['signer_name'], 'signer_role' => $j['signer_role'],
            ],
        ];
        flash('Als Wiederholauftrag geladen – bitte Charge/Datum prüfen.');
        redirect('wizard');
    }
}

$rows = db()->query("SELECT j.*, p.name AS paper_name FROM jobs j LEFT JOIN papers p ON p.id=j.paper_id ORDER BY j.id DESC")->fetchAll();
$modeLabel = ['self' => ['Eigenprod.', 'self'], 'buyin_eu' => ['Zukauf EU', 'buy'], 'buyin_noneu' => ['Zukauf Nicht-EU', 'buy']];

ob_start(); ?>
<h1>Erklärungen</h1>
<p class="lead">Alle erstellten Konformitätserklärungen. „Wiederholen" übernimmt alle Daten – ideal für Folgeaufträge.</p>
<div class="card">
  <div class="btn-row" style="margin-top:0"><a class="btn" href="<?= url('wizard') ?>">➕ Neue Erklärung</a></div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted">Noch keine Erklärungen erstellt.</p>
  <?php else: ?>
  <table class="list">
    <tr><th>DoC-Nummer</th><th>Produkt</th><th>Typ</th><th>Papier</th><th>Erstellt</th><th>PDF</th><th></th></tr>
    <?php foreach ($rows as $r): $ml = $modeLabel[$r['mode']] ?? ['?', 'na']; ?>
      <tr>
        <td><?= e($r['doc_number']) ?></td>
        <td><?= e($r['product_name'] ?: '—') ?><br><span class="muted" style="font-size:12px"><?= e(trim($r['length_mm'] . '×' . $r['width_mm'] . '×' . $r['height_mm'])) ?> mm</span></td>
        <td><span class="pill <?= $ml[1] ?>"><?= e($ml[0]) ?></span></td>
        <td class="muted"><?= e($r['paper_name'] ?? '—') ?></td>
        <td class="muted"><?= e($r['created_at']) ?></td>
        <td><?= $r['pdf_path'] ? '<a class="btn secondary" style="padding:4px 10px" href="' . url('pdf', ['job' => $r['id']]) . '" target="_blank">PDF</a>' : '<span class="muted">–</span>' ?></td>
        <td>
          <a href="<?= url('jobs', ['repeat' => $r['id']]) ?>">wiederholen</a><br>
          <a class="muted" href="<?= url('jobs', ['del' => $r['id']]) ?>" onclick="return confirm('Erklärung löschen?')">löschen</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Erklärungen', $content);
