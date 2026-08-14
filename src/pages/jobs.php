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

/* Wiederholauftrag: Daten in die Einseiten-Maske vorladen */
if (($_GET['repeat'] ?? '') !== '') {
    $j = db()->query("SELECT * FROM jobs WHERE id=" . (int)$_GET['repeat'])->fetch();
    if ($j) {
        $_SESSION['prefill'] = [
            'product_name' => $j['product_name'], 'article_no' => $j['article_no'],
            'length_mm' => $j['length_mm'], 'width_mm' => $j['width_mm'], 'height_mm' => $j['height_mm'],
            'paper_id' => (int)$j['paper_id'],
        ];
        flash('Als Wiederholauftrag geladen – bitte Charge/Datum prüfen.');
        redirect('wizard');
    }
}

$rows = db()->query("SELECT j.*, p.name AS paper_name FROM jobs j LEFT JOIN papers p ON p.id=j.paper_id ORDER BY j.id DESC")->fetchAll();

ob_start(); ?>
<h1>Erklärungen</h1>
<p class="lead">Alle erstellten Erklärungen. „Wiederholen" übernimmt die Daten – nur Charge/Datum anpassen.</p>
<div class="card"><div class="btn-row" style="margin-top:0"><a class="btn" href="<?= url('wizard') ?>">➕ Neue Erklärung</a></div></div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted">Noch keine Erklärungen erstellt.</p>
  <?php else: ?>
  <table class="list">
    <tr><th>DoC-Nummer</th><th>Produkt</th><th>Papier</th><th>Erstellt</th><th>PDF</th><th>intern</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['doc_number']) ?></td>
        <td><?= e($r['product_name'] ?: '—') ?><br><span class="muted" style="font-size:12px"><?= e(trim($r['length_mm'] . '×' . $r['width_mm'] . '×' . $r['height_mm'], '×')) ?><?= trim($r['length_mm'] . $r['width_mm'] . $r['height_mm']) ? ' mm' : '' ?></span></td>
        <td class="muted"><?= e($r['paper_name'] ?? '—') ?></td>
        <td class="muted"><?= e($r['created_at']) ?></td>
        <td><?= $r['pdf_path'] ? '<a class="btn secondary" style="padding:4px 10px" href="' . url('pdf', ['job' => $r['id']]) . '" target="_blank">PDF</a>' : '<span class="muted">–</span>' ?></td>
        <td><?= $r['supplier_doc'] ? '<a href="' . url('pdf', ['file' => $r['supplier_doc']]) . '" target="_blank">Nachweis</a>' : '<span class="muted">–</span>' ?></td>
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
