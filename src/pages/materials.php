<?php
if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM materials WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Eintrag gelöscht.');
    redirect('materials');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $doc = handle_upload('doc_file', 'material');
        $kind = in_array($_POST['kind'] ?? '', ['ink', 'toner', 'adhesive', 'varnish'], true) ? $_POST['kind'] : 'ink';
        db()->prepare("INSERT INTO materials (kind,name,manufacturer,eupia,doc_file) VALUES (?,?,?,?,?)")->execute([
            $kind, trim($_POST['name'] ?? ''), trim($_POST['manufacturer'] ?? ''),
            isset($_POST['eupia']) ? 1 : 0, $doc,
        ]);
        flash('Eintrag gespeichert.');
    } catch (Throwable $ex) {
        flash('Fehler: ' . $ex->getMessage());
    }
    redirect('materials');
}

$rows = db()->query("SELECT * FROM materials ORDER BY kind,name")->fetchAll();

ob_start(); ?>
<h1>Farben / Toner / Kleber / Lack</h1>
<p class="lead">Einmalige Konformitätserklärungen Ihrer Lieferanten (EuPIA). Decken Artikel 5 (PFAS, Schwermetalle) für Ihr Standardsortiment ab.</p>

<div class="card">
  <h3>Neuen Eintrag hinzufügen</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row">
      <div><label>Art
        <select name="kind">
          <option value="ink">Druckfarbe (Offset)</option>
          <option value="toner">Toner (Digitaldruck)</option>
          <option value="adhesive">Klebstoff</option>
          <option value="varnish">Lack</option>
        </select></label></div>
      <div><label>Bezeichnung<input type="text" name="name" required placeholder="z. B. Bogenoffset-Skala Standard"></label></div>
    </div>
    <label>Hersteller<input type="text" name="manufacturer"></label>
    <label><input type="checkbox" name="eupia" value="1" checked> EuPIA-konform bestätigt (PFAS-/Schwermetall-Nachweis)</label>
    <label>Konformitätserklärung des Lieferanten (PDF)<input type="file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg"></label>
    <div class="btn-row"><button class="btn" type="submit">Speichern</button></div>
  </form>
</div>

<div class="card">
  <h3>Hinterlegte Einträge (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?><p class="muted">Noch nichts hinterlegt.</p><?php else: ?>
  <table class="list">
    <tr><th>Art</th><th>Bezeichnung</th><th>Hersteller</th><th>EuPIA</th><th>Nachweis</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(kind_label($r['kind'])) ?></td>
        <td><?= e($r['name']) ?></td>
        <td class="muted"><?= e($r['manufacturer']) ?></td>
        <td><?= $r['eupia'] ? '<span class="pill ok">ja</span>' : '<span class="pill warn">nein</span>' ?></td>
        <td><?= $r['doc_file'] ? '<a href="' . url('pdf', ['file' => $r['doc_file']]) . '" target="_blank">öffnen</a>' : '<span class="muted">–</span>' ?></td>
        <td><a class="muted" href="<?= url('materials', ['del' => $r['id']]) ?>" onclick="return confirm('Löschen?')">löschen</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Farben/Toner/Kleber', $content);
