<?php
/**
 * Allgemeine Dokumentenablage.
 * Freitextkategorien (mit Autocomplete aus bereits verwendeten).
 * Dokumente werden NICHT ins Kunden-/Interne PDF eingebettet – reine Ablage.
 */

if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM documents WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Dokument gelöscht.');
    redirect('documents');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'new';

    if ($action === 'new') {
        try {
            $file = handle_upload('doc_file', 'gendoc');
            if (!$file) {
                flash('Bitte eine Datei auswählen.');
                redirect('documents');
            }
            db()->prepare("INSERT INTO documents (category,label,supplier,file,valid_until,note) VALUES (?,?,?,?,?,?)")->execute([
                trim($_POST['category'] ?? ''),
                trim($_POST['label'] ?? '') ?: 'Dokument',
                trim($_POST['supplier'] ?? ''),
                $file,
                trim($_POST['valid_until'] ?? ''),
                trim($_POST['note'] ?? ''),
            ]);
            flash('Dokument gespeichert.');
        } catch (Throwable $ex) {
            flash('Fehler: ' . $ex->getMessage());
        }
        redirect('documents');
    }

    if ($action === 'update') {
        $did = (int)($_POST['doc_id'] ?? 0);
        if (!$did) { redirect('documents'); }
        try {
            $existing = db()->query("SELECT file FROM documents WHERE id=$did")->fetch();
            $file = handle_upload('doc_file', 'gendoc');
            if (!$file) { $file = $existing['file'] ?? ''; }
            db()->prepare("UPDATE documents SET category=?,label=?,supplier=?,file=?,valid_until=?,note=? WHERE id=?")->execute([
                trim($_POST['category'] ?? ''),
                trim($_POST['label'] ?? '') ?: 'Dokument',
                trim($_POST['supplier'] ?? ''),
                $file,
                trim($_POST['valid_until'] ?? ''),
                trim($_POST['note'] ?? ''),
                $did,
            ]);
            flash('Dokument aktualisiert.');
        } catch (Throwable $ex) {
            flash('Fehler: ' . $ex->getMessage());
        }
        redirect('documents');
    }
}

// Filter/Suche
$fCat = trim((string)($_GET['cat'] ?? ''));
$q    = trim((string)($_GET['q']   ?? ''));

$sql = "SELECT * FROM documents WHERE 1=1";
$args = [];
if ($fCat !== '') { $sql .= " AND category = ?"; $args[] = $fCat; }
if ($q !== '')    {
    $sql .= " AND (label LIKE ? OR supplier LIKE ? OR note LIKE ?)";
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like);
}
$sql .= " ORDER BY category, label";
$stmt = db()->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

// Kategorie-Liste für Filter-Dropdown und datalist
$cats = array_column(db()->query("SELECT DISTINCT category FROM documents WHERE category != '' ORDER BY category")->fetchAll(), 'category');

// Bearbeitungsmodus
$editId = (int)($_GET['edit'] ?? 0);
$editD  = $editId ? db()->query("SELECT * FROM documents WHERE id=$editId")->fetch() : null;
$ed = fn(string $k, string $d = '') => e((string)($editD[$k] ?? $d));

$prod = producer();

ob_start(); ?>
<h1>Dokumente</h1>
<p class="lead">Zentrale Ablage für alle Nachweise, die keinem konkreten Papier oder Zukauf-Lieferanten zugeordnet sind – z. B. Farb-/Toner-Konformitäten, EuPIA-Blätter, Klebstoffe, Sicherheitsdatenblätter, eigene Zertifikate. <b>Diese Dokumente werden nicht ins Kunden- oder interne PDF eingebunden.</b></p>

<?php if ($prod['company']): ?>
<div class="card" style="background:#f4f7f9">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <div>
      <b>Live-Kunden-Muster</b> <span class="hint">(werden aus dem Firmenprofil erzeugt, nicht als Datei gespeichert)</span>
    </div>
    <div class="btn-row" style="margin:0">
      <a class="btn secondary" style="padding:5px 12px" href="<?= url('templates', ['do' => 'ppwr']) ?>" target="_blank">PPWR-Sortimentserklärung</a>
      <a class="btn secondary" style="padding:5px 12px" href="<?= url('templates', ['do' => 'reach']) ?>" target="_blank">REACH-Erklärung</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <?php if ($editD): ?>
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0">Dokument bearbeiten – <?= e($editD['label']) ?></h3>
      <a href="<?= url('documents') ?>" class="muted">← zurück zur Übersicht</a>
    </div>
  <?php else: ?>
    <h3>Neues Dokument hinzufügen</h3>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($editD): ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="doc_id" value="<?= (int)$editD['id'] ?>">
    <?php else: ?>
      <input type="hidden" name="action" value="new">
    <?php endif; ?>

    <div class="row">
      <div><label>Kategorie<?= info('Freitext. Bereits vorhandene Kategorien werden vorgeschlagen. Beispiele: „Farbe/Toner", „Klebstoff", „Klebeband", „REACH", „Sicherheitsdatenblätter", „Zertifikate", „Kunden-Muster", „Sonstiges".') ?>
        <input type="text" name="category" list="catList" value="<?= $ed('category') ?>" placeholder="z. B. Farbe/Toner">
        <datalist id="catList">
          <?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
          <option value="Farbe/Toner">
          <option value="Klebstoff">
          <option value="Klebeband">
          <option value="REACH">
          <option value="Sicherheitsdatenblätter">
          <option value="Zertifikate">
          <option value="Kunden-Muster">
          <option value="Sonstiges">
        </datalist>
      </label></div>
      <div><label>Bezeichnung *<?= info('Kurzer sprechender Name, z. B. „Saphira Ink Perfect Board – SDB", „Bogenoffset Skala – EuPIA-Konformität".') ?>
        <input type="text" name="label" value="<?= $ed('label') ?>" required placeholder="z. B. Bogenoffset Skala – EuPIA-Konformität">
      </label></div>
    </div>

    <div class="row">
      <div><label>Lieferant / Hersteller <span class="hint">(optional)</span>
        <input type="text" name="supplier" value="<?= $ed('supplier') ?>" placeholder="z. B. Heidelberg, tesa, K+E">
      </label></div>
      <div><label>Gültig bis <span class="hint">(optional)</span><?= info('Ablaufdatum. Das Tool warnt 60 Tage vor Ablauf.') ?>
        <input type="date" name="valid_until" value="<?= $ed('valid_until') ?>">
      </label></div>
    </div>

    <label>Datei<?= info('PDF/PNG/JPG. Bei Bearbeitung: leer lassen behält die aktuelle Datei; neue Datei ersetzt sie.') ?>
      <input type="file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg" <?= $editD ? '' : 'required' ?>>
      <?php if ($editD && !empty($editD['file'])): ?>
        <span class="hint">aktuell hinterlegt: <a href="<?= url('pdf', ['file' => $editD['file']]) ?>" target="_blank"><?= e($editD['file']) ?></a> – leer lassen zum Behalten</span>
      <?php endif; ?>
    </label>

    <label>Notiz <span class="hint">(optional)</span>
      <textarea name="note" rows="2" placeholder="z. B. „Gilt für alle Skala-Farben der Serie Y"; „Angefragt bei Heidelberg am TT.MM.JJJJ""><?= $ed('note') ?></textarea>
    </label>

    <div class="btn-row"><button class="btn" type="submit"><?= $editD ? 'Änderungen speichern' : '+ Dokument hinzufügen' ?></button></div>
  </form>
</div>

<div class="card">
  <div class="btn-row" style="margin-top:0">
    <form method="get" style="display:flex;gap:8px;flex:1;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="p" value="documents">
      <label style="margin:0;font-weight:400;font-size:13px">
        Kategorie:
        <select name="cat" onchange="this.form.submit()" style="padding:6px 8px">
          <option value="">— alle —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= e($c) ?>" <?= $fCat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Suche: Bezeichnung, Lieferant, Notiz" style="flex:1;min-width:220px">
      <button class="btn secondary" type="submit" style="padding:6px 14px">Filtern</button>
      <?php if ($q !== '' || $fCat !== ''): ?><a href="<?= url('documents') ?>" class="muted">zurücksetzen</a><?php endif; ?>
    </form>
  </div>
  <?php if ($q !== '' || $fCat !== ''): ?><p class="muted" style="margin-top:8px;font-size:13px"><?= count($rows) ?> Treffer</p><?php endif; ?>

  <?php if (!$rows): ?>
    <p class="muted">Noch keine Dokumente hinterlegt.</p>
  <?php else: ?>
    <table class="list">
      <tr><th>Kategorie</th><th>Bezeichnung</th><th>Lieferant</th><th>Datei</th><th>Gültig</th><th></th></tr>
      <?php foreach ($rows as $r):
        $v = doc_validity($r['valid_until']);
        $vPill = ['ok' => 'ok', 'soon' => 'warn', 'expired' => 'warn', 'none' => 'na'][$v['state']];
      ?>
        <tr>
          <td><?= e($r['category']) ?: '<span class="muted">–</span>' ?></td>
          <td><?= e($r['label']) ?><?php if ($r['note']): ?><br><span class="muted" style="font-size:12px"><?= e($r['note']) ?></span><?php endif; ?></td>
          <td class="muted"><?= e($r['supplier']) ?></td>
          <td><?= $r['file'] ? '<a href="' . url('pdf', ['file' => $r['file']]) . '" target="_blank">öffnen</a>' : '<span class="muted">–</span>' ?></td>
          <td><?= $v['state'] === 'none' ? '<span class="muted">–</span>' : '<span class="pill ' . $vPill . '">' . e($v['label']) . '</span>' ?></td>
          <td>
            <a href="<?= url('documents', ['edit' => $r['id']]) ?>">bearbeiten</a><br>
            <a class="muted" href="<?= url('documents', ['del' => $r['id']]) ?>" onclick="return confirm('Dokument löschen?')">löschen</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Dokumente', $content);
