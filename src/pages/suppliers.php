<?php
/**
 * Lieferantenverwaltung mit Dokumenten-Regal.
 * Jeder Lieferant hat beliebig viele PDF-Nachweise, die beim Auftrag
 * "Fremdproduziert" automatisch ins interne PDF eingebunden werden.
 */

if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM suppliers WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Lieferant gelöscht.');
    redirect('suppliers');
}
if (($_GET['delDoc'] ?? '') !== '') {
    $sid = (int)($_GET['s'] ?? 0);
    db()->prepare("DELETE FROM supplier_docs WHERE id=?")->execute([(int)$_GET['delDoc']]);
    flash('Dokument entfernt.');
    redirect('suppliers', $sid ? ['edit' => $sid] : []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'new';

    if ($action === 'new') {
        db()->prepare("INSERT INTO suppliers (name,country,eu,contact) VALUES (?,?,?,?)")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['country'] ?? 'Deutschland'),
            isset($_POST['eu']) ? 1 : 0, trim($_POST['contact'] ?? ''),
        ]);
        flash('Lieferant angelegt.');
        redirect('suppliers', ['edit' => (int)db()->lastInsertId()]);
    }

    if ($action === 'update') {
        $sid = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE suppliers SET name=?,country=?,eu=?,contact=? WHERE id=?")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['country'] ?? 'Deutschland'),
            isset($_POST['eu']) ? 1 : 0, trim($_POST['contact'] ?? ''), $sid,
        ]);
        flash('Lieferant aktualisiert.');
        redirect('suppliers', ['edit' => $sid]);
    }

    if ($action === 'updatedoc') {
        $did = (int)($_POST['doc_id'] ?? 0);
        $sid = (int)($_POST['supplier_id'] ?? 0);
        if ($did) {
            db()->prepare("UPDATE supplier_docs SET label=?, valid_until=? WHERE id=?")->execute([
                trim($_POST['label'] ?? '') ?: 'Nachweis',
                trim($_POST['valid_until'] ?? ''),
                $did,
            ]);
            flash('Dokument aktualisiert.');
        }
        redirect('suppliers', $sid ? ['edit' => $sid] : []);
    }

    if ($action === 'adddoc') {
        $sid = (int)($_POST['supplier_id'] ?? 0);
        try {
            $f = handle_upload('doc_file', 'supdoc');
            if ($f) {
                db()->prepare("INSERT INTO supplier_docs (supplier_id,label,file,valid_until) VALUES (?,?,?,?)")->execute([
                    $sid,
                    trim($_POST['label'] ?? '') ?: 'Nachweis',
                    $f,
                    trim($_POST['valid_until'] ?? ''),
                ]);
                flash('Dokument hinzugefügt.');
            } else {
                flash('Bitte eine Datei auswählen.');
            }
        } catch (Throwable $ex) {
            flash('Upload-Fehler: ' . $ex->getMessage());
        }
        redirect('suppliers', ['edit' => $sid]);
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editSup = $editId ? db()->query("SELECT * FROM suppliers WHERE id=$editId")->fetch() : null;
$editDocs = $editSup
    ? db()->query("SELECT * FROM supplier_docs WHERE supplier_id=$editId ORDER BY id")->fetchAll()
    : [];

$rows = db()->query("SELECT s.*, (SELECT COUNT(*) FROM supplier_docs d WHERE d.supplier_id=s.id) AS n_docs FROM suppliers s ORDER BY s.name")->fetchAll();

ob_start(); ?>
<h1>Lieferanten (Zukauf)</h1>
<p class="lead">Für Schachteln, die ihr fremdproduziert bezieht (z. B. Packex). Alle hinterlegten Dokumente werden im Wizard automatisch ins <b>interne PDF</b> eingebunden, sobald ihr diesen Lieferanten auswählt.</p>

<?php if (!$editSup): ?>
<div class="card">
  <h3>Neuen Lieferanten anlegen</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="new">
    <div class="row">
      <div><label>Name<?= info('Der Firmenname wird nur intern verwendet – erscheint nie auf dem Kunden-PDF.') ?><input type="text" name="name" required placeholder="Packex GmbH"></label></div>
      <div><label>Land<input type="text" name="country" value="Deutschland"></label></div>
    </div>
    <label><input type="checkbox" name="eu" value="1" checked> Sitz in der EU<?= info('EU-Lieferant → ihr stützt euch auf dessen Konformitätserklärung (Own-Brand). Nicht-EU → ihr werdet Importeur mit erweiterten Pflichten.') ?></label>
    <label>Kontakt <span class="hint">(optional)</span><input type="text" name="contact"></label>
    <div class="btn-row"><button class="btn" type="submit">Lieferant anlegen &amp; Dokumente hinterlegen →</button></div>
  </form>
</div>
<?php else: ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h3 style="margin:0"><?= e($editSup['name']) ?></h3>
    <a href="<?= url('suppliers') ?>" class="muted">← zurück zur Übersicht</a>
  </div>
  <form method="post" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int)$editSup['id'] ?>">
    <div class="row">
      <div><label>Name<input type="text" name="name" required value="<?= e($editSup['name']) ?>"></label></div>
      <div><label>Land<input type="text" name="country" value="<?= e($editSup['country']) ?>"></label></div>
    </div>
    <label><input type="checkbox" name="eu" value="1" <?= $editSup['eu'] ? 'checked' : '' ?>> Sitz in der EU</label>
    <label>Kontakt<input type="text" name="contact" value="<?= e($editSup['contact']) ?>"></label>
    <div class="btn-row"><button class="btn secondary" type="submit">Speichern</button></div>
  </form>
</div>

<div class="card">
  <h3>Dokumente <span class="muted" style="font-weight:400;font-size:14px">(werden bei Auswahl dieses Lieferanten automatisch ins interne PDF eingebettet)</span></h3>
  <?php if (!$editDocs): ?>
    <p class="muted">Noch keine Dokumente hinterlegt.</p>
  <?php else: ?>
    <table class="list">
      <tr><th style="width:38%">Bezeichnung</th><th>Datei</th><th style="width:22%">Gültig bis</th><th style="width:14%"></th><th></th></tr>
      <?php foreach ($editDocs as $d):
        $v = doc_validity($d['valid_until']);
        $vPill = ['ok' => 'ok', 'soon' => 'warn', 'expired' => 'warn', 'none' => 'na'][$v['state']];
        $statusInfo = $v['state'] !== 'none' ? '<span class="pill ' . $vPill . '" style="margin-top:4px;display:inline-block">' . e($v['label']) . '</span>' : '';
      ?>
        <tr>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="updatedoc">
            <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
            <input type="hidden" name="supplier_id" value="<?= (int)$editSup['id'] ?>">
            <td><input type="text" name="label" value="<?= e($d['label']) ?>" required style="padding:6px 8px;font-size:13px"></td>
            <td><a href="<?= url('pdf', ['file' => $d['file']]) ?>" target="_blank">öffnen</a></td>
            <td>
              <input type="date" name="valid_until" value="<?= e($d['valid_until']) ?>" style="padding:6px 8px;font-size:13px">
              <?= $statusInfo ?>
            </td>
            <td><button class="btn secondary" type="submit" style="padding:5px 12px;font-size:13px">Speichern</button></td>
            <td><a class="muted" href="<?= url('suppliers', ['delDoc' => $d['id'], 's' => $editSup['id']]) ?>" onclick="return confirm('Dokument entfernen?')">entfernen</a></td>
          </form>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h4 style="margin-top:16px">Dokument hinzufügen</h4>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="adddoc">
    <input type="hidden" name="supplier_id" value="<?= (int)$editSup['id'] ?>">
    <div class="row">
      <div><label>Bezeichnung<?= info('Kurzer sprechender Name des Dokuments, z. B. „Konformitätserklärung Kartons", „REACH", „PPWR-Kundeninfo".') ?><input type="text" name="label" required placeholder="z. B. Konformitätserklärung Kartons"></label></div>
      <div><label>Gültig bis <span class="hint">(optional)</span><?= info('Ablaufdatum, falls im Dokument angegeben (Packex nennt z. B. „Gültigkeit 2 Jahre"). Das Tool warnt 60 Tage vor Ablauf.') ?><input type="date" name="valid_until"></label></div>
    </div>
    <label>Datei<input type="file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg" required></label>
    <div class="btn-row"><button class="btn" type="submit">+ Dokument hinzufügen</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3>Hinterlegte Lieferanten (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?>
    <p class="muted">Noch keine Lieferanten hinterlegt.</p>
  <?php else: ?>
    <table class="list">
      <tr><th>Name</th><th>EU</th><th>Dokumente</th><th>Kontakt</th><th></th></tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= url('suppliers', ['edit' => $r['id']]) ?>"><?= e($r['name']) ?></a></td>
          <td><?= $r['eu'] ? '<span class="pill ok">EU</span>' : '<span class="pill warn">Nicht-EU</span>' ?></td>
          <td><?= (int)$r['n_docs'] ?></td>
          <td class="muted"><?= e($r['contact']) ?></td>
          <td>
            <a href="<?= url('suppliers', ['edit' => $r['id']]) ?>">bearbeiten</a><br>
            <a class="muted" href="<?= url('suppliers', ['del' => $r['id']]) ?>" onclick="return confirm('Lieferant und alle seine Dokumente löschen?')">löschen</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Lieferanten', $content);
