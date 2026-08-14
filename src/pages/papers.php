<?php
if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM papers WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Papier gelöscht.');
    redirect('papers');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newId = 0;
    try {
        $doc  = handle_upload('doc_file', 'paperdoc');   // Konformitätserklärung
        $spec = handle_upload('spec_file', 'paper');     // Technisches Datenblatt
        db()->prepare("INSERT INTO papers (name,manufacturer,grammage,thickness_um,structure,food_contact,recyclable_note,recycled_content,compostable,spec_file,doc_file)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['manufacturer'] ?? ''), trim($_POST['grammage'] ?? ''),
            trim($_POST['thickness_um'] ?? ''), trim($_POST['structure'] ?? ''),
            isset($_POST['food_contact']) ? 1 : 0,
            trim($_POST['recyclable_note'] ?? ''),
            trim($_POST['recycled_content'] ?? ''),
            isset($_POST['compostable']) ? 1 : 0,
            $spec, $doc,
        ]);
        $newId = (int)db()->lastInsertId();
        flash('Papier gespeichert.');
    } catch (Throwable $ex) {
        flash('Fehler: ' . $ex->getMessage());
    }
    // Rücksprung in die Erklärung, neues Papier vorauswählen
    if (($_POST['return'] ?? '') === 'wizard' && $newId) {
        $pf = $_SESSION['prefill'] ?? [];
        $pf['paper_id'] = $newId;
        $_SESSION['prefill'] = $pf;
        redirect('wizard');
    }
    redirect('papers');
}

$backToWizard = (($_GET['return'] ?? '') === 'wizard');

$rows = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();

ob_start(); ?>
<h1>Papiere / Kartons</h1>
<p class="lead">Materialdatenblätter einmal hinterlegen – bei Aufträgen dann nur noch aus der Liste wählen.</p>

<?php if ($backToWizard): ?>
<div class="note">Du legst gerade ein Papier für eine laufende Erklärung an. Nach dem Speichern geht es <b>automatisch zurück</b> – das neue Papier ist dann vorausgewählt. <a href="<?= url('wizard') ?>">Ohne Speichern zurück zur Erklärung →</a></div>
<?php endif; ?>

<div class="card">
  <h3>Neues Papier hinzufügen</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($backToWizard): ?><input type="hidden" name="return" value="wizard"><?php endif; ?>
    <div class="row">
      <div><label>Bezeichnung <span class="hint">(z. B. Invercote G 350 g/m²)</span><input type="text" name="name" required></label></div>
      <div><label>Hersteller<input type="text" name="manufacturer" placeholder="Iggesund / Holmen"></label></div>
    </div>
    <div class="row">
      <div><label>Grammatur (g/m²)<?= info('Flächengewicht des Papiers in Gramm pro Quadratmeter. Findet man auf jedem Datenblatt. Bei uns typischerweise 250–400.') ?><input type="text" name="grammage" placeholder="350"></label></div>
      <div><label>Dicke (µm)<?= info('Kartondicke in Mikrometern (1 µm = 1/1000 mm). Wird auf dem Datenblatt oft „Thickness/Caliper" genannt.') ?><input type="text" name="thickness_um" placeholder="465"></label></div>
    </div>
    <label>Schichtaufbau / Beschreibung<?= info('Kurz die Kartonart: z. B. SBB (Solid Bleached Board = Frischfaserkarton) oder GD2 (Recyclingkarton). Steht auf dem Datenblatt oben.') ?>
        <input type="text" name="structure" placeholder="SBB, mehrlagig, Frischfaser, dreifach gestrichen">
    </label>

    <h4 style="margin:14px 0 4px;color:#14425f">Angaben zur PPWR</h4>

    <label>Recyclingfähigkeit<?= info('PPWR Art. 6. Steht auf dem Datenblatt meist als „EN 13430 erfüllt" oder „CEPI-recyclingfähig". Wenn du unsicher bist, „nicht ausgewiesen" eintragen – das PDF verwendet dann eine neutrale Formulierung.') ?>
        <input type="text" name="recyclable_note" placeholder="z. B. EN 13430 erfüllt / CEPI-recyclingfähig">
    </label>

    <label>Rezyklatanteil<?= info('PPWR Art. 7. Anteil an wiederverwertetem Material. Bei Frischfaserkarton wie Invercote G: „Frischfaser 100 %". Bei Recyclingkarton wie Multicolor Mirabell: „PCW 60 %, PIW 20 %" oder ähnlich – steht so auf dem Datenblatt.') ?>
        <input type="text" name="recycled_content" placeholder="z. B. Frischfaser 100 %">
    </label>

    <label><input type="checkbox" name="compostable" value="1"> Industriell kompostierbar (EN 13432 zertifiziert)<?= info('PPWR Art. 8/9. Nur ankreuzen, wenn das Datenblatt ausdrücklich „EN 13432 zertifiziert" nennt. Für unsere üblichen Faltschachteln nicht verpflichtend, aber ein netter Zusatznachweis.') ?></label>

    <label><input type="checkbox" name="food_contact" value="1"> Lebensmittelkontakt-Eignung<?= info('Nachweise nach EG 1935/2004 und BfR-Empfehlung XXXVI. Für Werkzeuge o. ä. nicht nötig, aber wenn der Karton dafür geeignet ist, hier ankreuzen – dann steht es auch im internen PDF.') ?> (1935/2004, BfR XXXVI) dokumentiert</label>

    <h4 style="margin:14px 0 4px;color:#14425f">Nachweisdokumente</h4>

    <label>Konformitätserklärung des Herstellers (PDF)<?= info('Der eigentliche Nachweis für PPWR Art. 5 (PFAS, Schwermetalle ≤ 100 mg/kg, REACH). Beim Papierhersteller oder Großhändler anfordern – oft eine gemeinsame Erklärung für mehrere Kartongruppen. Wird ins interne PDF eingebettet.') ?> <span class="hint">– empfohlen</span>
        <input type="file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg">
    </label>
    <label>Technisches Datenblatt (PDF)<?= info('Reines Produktdatenblatt (Grammatur, Aufbau, Prüfnormen). Beim Hersteller frei online herunterladbar. Kein Konformitätsnachweis, aber gute technische Beschreibung.') ?> <span class="hint">– optional</span>
        <input type="file" name="spec_file" accept=".pdf,.png,.jpg,.jpeg">
    </label>
    <div class="btn-row"><button class="btn" type="submit">Speichern</button></div>
  </form>
</div>

<div class="card">
  <h3>Hinterlegte Papiere (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?><p class="muted">Noch keine Papiere hinterlegt.</p><?php else: ?>
  <table class="list">
    <tr><th>Bezeichnung</th><th>Hersteller</th><th>g/m²</th><th>Konformität</th><th>Datenblatt</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?><?php if ($r['food_contact']): ?> <span class="pill ok">LM-Kontakt</span><?php endif; ?></td>
        <td class="muted"><?= e($r['manufacturer']) ?></td>
        <td><?= e($r['grammage']) ?></td>
        <td><?= !empty($r['doc_file']) ? '<a href="' . url('pdf', ['file' => $r['doc_file']]) . '" target="_blank">öffnen</a>' : '<span class="pill warn">fehlt</span>' ?></td>
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
