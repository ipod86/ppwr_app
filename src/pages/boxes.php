<?php
if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM boxes WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Vorlage gelöscht.');
    redirect('boxes');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newId = 0;
    try {
        $contour = handle_upload('contour_file', 'contour');
        db()->prepare("INSERT INTO boxes (name,length_mm,width_mm,height_mm,contour_file) VALUES (?,?,?,?,?)")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['length_mm'] ?? ''), trim($_POST['width_mm'] ?? ''),
            trim($_POST['height_mm'] ?? ''), $contour,
        ]);
        $newId = (int)db()->lastInsertId();
        flash('Schachtel-Vorlage gespeichert.');
    } catch (Throwable $ex) {
        flash('Fehler: ' . $ex->getMessage());
    }
    if (($_POST['return'] ?? '') === 'wizard' && $newId) {
        $pf = $_SESSION['prefill'] ?? [];
        $pf['box_id'] = $newId;
        $_SESSION['prefill'] = $pf;
        redirect('wizard');
    }
    redirect('boxes');
}

$backToWizard = (($_GET['return'] ?? '') === 'wizard');

$rows = db()->query("SELECT * FROM boxes ORDER BY name")->fetchAll();

ob_start(); ?>
<h1>Schachtel-Vorlagen</h1>
<p class="lead">Wiederkehrende Formate mit Stanzkontur speichern – im Assistenten dann direkt auswählbar.</p>

<?php if ($backToWizard): ?>
<div class="note">Du legst gerade eine Vorlage für eine laufende Erklärung an. Nach dem Speichern geht es <b>automatisch zurück</b> – die Vorlage ist dann vorausgewählt. <a href="<?= url('wizard') ?>">Ohne Speichern zurück →</a></div>
<?php endif; ?>

<div class="card">
  <h3>Neue Vorlage</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($backToWizard): ?><input type="hidden" name="return" value="wizard"><?php endif; ?>
    <label>Bezeichnung <span class="hint">(z. B. Würfel 100×100×100)</span><input type="text" name="name" required></label>
    <div class="row">
      <div><label>Länge (mm)<input type="text" name="length_mm" placeholder="100"></label></div>
      <div><label>Breite (mm)<input type="text" name="width_mm" placeholder="100"></label></div>
      <div><label>Höhe (mm)<input type="text" name="height_mm" placeholder="100"></label></div>
    </div>
    <label>Stanzkontur (PDF/SVG/PNG)<input type="file" name="contour_file" accept=".pdf,.svg,.png,.jpg,.jpeg"></label>
    <div class="btn-row"><button class="btn" type="submit">Speichern</button></div>
  </form>
</div>

<div class="card">
  <h3>Vorlagen (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?><p class="muted">Noch keine Vorlagen.</p><?php else: ?>
  <table class="list">
    <tr><th>Bezeichnung</th><th>Maße (mm)</th><th>Stanzkontur</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td>
        <td class="muted"><?= e($r['length_mm']) ?> × <?= e($r['width_mm']) ?> × <?= e($r['height_mm']) ?></td>
        <td><?= $r['contour_file'] ? '<a href="' . url('pdf', ['file' => $r['contour_file']]) . '" target="_blank">öffnen</a>' : '<span class="muted">–</span>' ?></td>
        <td><a class="muted" href="<?= url('boxes', ['del' => $r['id']]) ?>" onclick="return confirm('Löschen?')">löschen</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Schachtel-Vorlagen', $content);
