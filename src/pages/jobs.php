<?php
if (($_GET['del'] ?? '') !== '') {
    $j = db()->query("SELECT pdf_path, pdf_intern FROM jobs WHERE id=" . (int)$_GET['del'])->fetch();
    foreach (['pdf_path', 'pdf_intern'] as $c) {
        if ($j && !empty($j[$c]) && is_file(PDF_DIR . '/' . $j[$c])) {
            @unlink(PDF_DIR . '/' . $j[$c]);
        }
    }
    db()->prepare("DELETE FROM jobs WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Erklärung gelöscht.');
    redirect('jobs');
}

/* Wiederholauftrag: Daten in die Einseiten-Maske vorladen */
if (($_GET['repeat'] ?? '') !== '') {
    $j = db()->query("SELECT * FROM jobs WHERE id=" . (int)$_GET['repeat'])->fetch();
    if ($j) {
        $_SESSION['prefill'] = [
            'product_name' => $j['product_name'], 'article_no' => $j['article_no'],
            'length_mm' => $j['length_mm'], 'width_mm' => $j['width_mm'], 'height_mm' => $j['height_mm'],
            'paper_id' => (int)$j['paper_id'],
            'mode' => (str_starts_with($j['mode'] ?? 'self', 'buyin') ? 'buyin' : 'self'),
            'supplier_id' => (int)($j['supplier_id'] ?? 0),
            'has_lamination' => (int)$j['has_lamination'],
            'package_kind' => $j['package_kind'] ?? 'faltschachtel',
            'package_kind_other' => $j['package_kind_other'] ?? '',
        ];
        flash('Als Wiederholauftrag geladen – bitte Charge/Datum prüfen.');
        redirect('wizard');
    }
}

$q  = trim((string)($_GET['q'] ?? ''));
$fL = ($_GET['fL'] ?? '') === '1'; // Filter: nur mit Kaschierung/Folie

$sql = "SELECT j.*, p.name AS paper_name FROM jobs j LEFT JOIN papers p ON p.id=j.paper_id WHERE 1=1";
$args = [];
if ($q !== '') {
    $sql .= " AND (j.doc_number LIKE ? OR j.product_name LIKE ? OR j.article_no LIKE ? OR j.batch LIKE ? OR p.name LIKE ?)";
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like, $like, $like);
}
if ($fL) {
    $sql .= " AND j.has_lamination = 1";
}
$sql .= " ORDER BY j.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

ob_start(); ?>
<h1>Erklärungen</h1>
<p class="lead">Alle erstellten Erklärungen. „Wiederholen" übernimmt die Daten – nur Charge/Datum anpassen.</p>
<div class="card">
  <div class="btn-row" style="margin-top:0">
    <a class="btn" href="<?= url('wizard') ?>">➕ Neue Erklärung</a>
    <form method="get" style="display:flex;gap:8px;flex:1;margin-left:12px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="p" value="jobs">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Suche: DoC-Nr., Produkt, Auftrag, Charge, Papier" style="flex:1;min-width:220px">
      <label style="margin:0;font-weight:400;font-size:13px"><input type="checkbox" name="fL" value="1" <?= $fL ? 'checked' : '' ?>> nur mit Kaschierung</label>
      <button class="btn secondary" type="submit" style="padding:6px 14px">Filtern</button>
      <?php if ($q !== '' || $fL): ?><a href="<?= url('jobs') ?>" class="muted">zurücksetzen</a><?php endif; ?>
    </form>
  </div>
  <?php if ($q !== '' || $fL): ?><p class="muted" style="margin-top:8px;font-size:13px"><?= count($rows) ?> Treffer</p><?php endif; ?>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted">Noch keine Erklärungen erstellt.</p>
  <?php else: ?>
  <table class="list">
    <tr><th>DoC-Nummer</th><th>Produkt</th><th>Papier</th><th>Erstellt</th><th>Kunden-PDF</th><th>Internes PDF</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['doc_number']) ?></td>
        <td><?= e($r['product_name'] ?: '—') ?><br><span class="muted" style="font-size:12px"><?= e(trim($r['length_mm'] . '×' . $r['width_mm'] . '×' . $r['height_mm'], '×')) ?><?= trim($r['length_mm'] . $r['width_mm'] . $r['height_mm']) ? ' mm' : '' ?></span></td>
        <td class="muted"><?= e($r['paper_name'] ?? '—') ?></td>
        <td class="muted"><?= e($r['created_at']) ?></td>
        <td><?= $r['pdf_path'] ? '<a class="btn secondary" style="padding:4px 10px" href="' . url('pdf', ['job' => $r['id']]) . '" target="_blank">Kunde</a>' : '<span class="muted">–</span>' ?></td>
        <td><?= !empty($r['pdf_intern']) ? '<a class="btn secondary" style="padding:4px 10px" href="' . url('pdf', ['job' => $r['id'], 'v' => 'intern']) . '" target="_blank">intern</a>' : '<span class="muted">–</span>' ?></td>
        <td>
          <a href="<?= url('jobs', ['repeat' => $r['id']]) ?>">wiederholen</a><br>
          <a class="muted" href="<?= url('jobs', ['del' => $r['id']]) ?>" onclick="return confirm('Erklärung löschen?')">löschen</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<div class="note">Der Spalte „intern" hinterlegte Nachweis (z. B. Lieferanten-DoC) dient nur eurer 10-Jahres-Ablage und erscheint <b>nicht</b> im Kunden-PDF.</div>
<?php
$content = ob_get_clean();
layout('Erklärungen', $content);
