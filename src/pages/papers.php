<?php
if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM papers WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Papier gelöscht.');
    redirect('papers');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $spec = handle_upload('spec_file', 'paper');
        db()->prepare("INSERT INTO papers (name,manufacturer,grammage,thickness_um,structure,food_contact,recyclable_note,spec_file)
            VALUES (?,?,?,?,?,?,?,?)")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['manufacturer'] ?? ''), trim($_POST['grammage'] ?? ''),
            trim($_POST['thickness_um'] ?? ''), trim($_POST['structure'] ?? ''),
            isset($_POST['food_contact']) ? 1 : 0, trim($_POST['recyclable_note'] ?? ''), $spec,
        ]);
        flash('Papier gespeichert.');
    } catch (Throwable $ex) {
        flash('Fehler: ' . $ex->getMessage());
    }
    redirect('papers');
}

$rows = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();

ob_start(); ?>
<h1>Papiere / Kartons</h1>
<p class="lead">Materialdatenblätter einmal hinterlegen – bei Aufträgen dann nur noch aus der Liste wählen.</p>

<div class="card">
  <h3>Neues Papier hinzufügen</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row">
      <div><label>Bezeichnung <span class="hint">(z. B. Invercote G 350 g/m²)</span><input type="text" name="name" required></label></div>
      <div><label>Hersteller<input type="text" name="manufacturer" placeholder="Iggesund / Holmen"></label></div>
    </div>
    <div class="row">
      <div><label>Grammatur (g/m²)<input type="text" name="grammage" placeholder="350"></label></div>
      <div><label>Dicke (µm)<input type="text" name="thickness_um" placeholder="465"></label></div>
    </div>
    <label>Schichtaufbau / Beschreibung<input type="text" name="structure" placeholder="SBB, mehrlagig, Frischfaser, dreifach gestrichen"></label>
    <label>Recycling-/Zusatzhinweis<input type="text" name="recyclable_note" placeholder="CEPI-recyclingfähig; EN 13432 (260–380 g/m²)"></label>
    <label><input type="checkbox" name="food_contact" value="1"> Lebensmittelkontakt-Eignung (1935/2004, BfR XXXVI) dokumentiert</label>
    <label>Herstellerdatenblatt (PDF)<input type="file" name="spec_file" accept=".pdf,.png,.jpg,.jpeg"></label>
    <div class="btn-row"><button class="btn" type="submit">Speichern</button></div>
  </form>
</div>

<div class="card">
  <h3>Hinterlegte Papiere (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?><p class="muted">Noch keine Papiere hinterlegt.</p><?php else: ?>
  <table class="list">
    <tr><th>Bezeichnung</th><th>Hersteller</th><th>g/m²</th><th>Datenblatt</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?><?php if ($r['food_contact']): ?> <span class="pill ok">LM-Kontakt</span><?php endif; ?></td>
        <td class="muted"><?= e($r['manufacturer']) ?></td>
        <td><?= e($r['grammage']) ?></td>
        <td><?= $r['spec_file'] ? '<a href="' . url('pdf', ['file' => $r['spec_file']]) . '" target="_blank">öffnen</a>' : '<span class="muted">–</span>' ?></td>
        <td><a class="muted" href="<?= url('papers', ['del' => $r['id']]) ?>" onclick="return confirm('Papier löschen?')">löschen</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Papiere', $content);
