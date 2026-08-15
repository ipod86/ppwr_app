<?php

/**
 * Löscht eine hochgeladene Papier-Datei nur, wenn kein anderes Papier
 * (z. B. ein Klon, der dieselbe Datei-Referenz übernommen hat) noch
 * darauf verweist.
 */
function unlink_if_unused_paper_file(string $filename): void
{
    if ($filename === '') {
        return;
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM papers WHERE spec_file = ? OR doc_file = ?");
    $stmt->execute([$filename, $filename]);
    if ((int)$stmt->fetchColumn() === 0) {
        $path = upload_path($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (($_GET['del'] ?? '') !== '') {
    $pid = (int)$_GET['del'];
    $p = db()->query("SELECT spec_file, doc_file FROM papers WHERE id=$pid")->fetch();
    db()->prepare("DELETE FROM papers WHERE id=?")->execute([$pid]);
    if ($p) {
        unlink_if_unused_paper_file($p['spec_file'] ?? '');
        unlink_if_unused_paper_file($p['doc_file'] ?? '');
    }
    flash('Papier gelöscht.');
    redirect('papers');
}

// Papier klonen (dupliziert Metadaten inkl. Datei-Referenzen)
if (($_GET['clone'] ?? '') !== '') {
    $src = db()->query("SELECT * FROM papers WHERE id=" . (int)$_GET['clone'])->fetch();
    if ($src) {
        db()->prepare("INSERT INTO papers
            (name,manufacturer,grammage,thickness_um,structure,food_contact,recyclable_note,
             recycled_content,compostable,material_code,spec_file,doc_file,doc_valid_until)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            $src['name'] . ' (Kopie)', $src['manufacturer'], $src['grammage'], $src['thickness_um'],
            $src['structure'], (int)$src['food_contact'], $src['recyclable_note'],
            $src['recycled_content'], (int)$src['compostable'],
            $src['material_code'] ?? '',
            $src['spec_file'], $src['doc_file'], $src['doc_valid_until'] ?? '',
        ]);
        flash('Papier dupliziert – Bezeichnung/Grammatur ggf. anpassen.');
    }
    redirect('papers');
}

/* JSON-Import: legt ein Papier aus dem KI-Rückgabe-JSON direkt an. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'importjson') {
    $raw = trim((string)($_POST['json'] ?? ''));
    // Manche KIs verpacken das JSON in ```json … ``` — abstreifen
    $raw = preg_replace('/^```(?:json)?\s*|\s*```$/im', '', $raw);
    $data = json_decode($raw, true);
    if (!is_array($data) || empty(trim((string)($data['name'] ?? '')))) {
        flash('Import fehlgeschlagen: kein gültiges JSON oder Feld „name" fehlt.');
        redirect('papers');
    }
    $newId = 0;
    try {
        db()->prepare("INSERT INTO papers (name,manufacturer,grammage,thickness_um,structure,food_contact,recyclable_note,recycled_content,compostable,material_code,spec_file,doc_file,doc_valid_until)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            trim((string)$data['name']),
            trim((string)($data['manufacturer'] ?? '')),
            trim((string)($data['grammage'] ?? '')),
            trim((string)($data['thickness_um'] ?? '')),
            trim((string)($data['structure'] ?? '')),
            !empty($data['food_contact']) ? 1 : 0,
            trim((string)($data['recyclable_note'] ?? '')),
            trim((string)($data['recycled_content'] ?? '')),
            !empty($data['compostable']) ? 1 : 0,
            trim((string)($data['material_code'] ?? '')),
            '', '', '',
        ]);
        $newId = (int)db()->lastInsertId();
        flash('Papier per JSON importiert – Konformitätserklärung/Datenblatt kannst du oben nachreichen.');
    } catch (Throwable $ex) {
        flash('Import-Fehler: ' . $ex->getMessage());
    }
    // Bei Rücksprung ins Wizard: neues Papier vorwählen
    if (($_POST['return'] ?? '') === 'wizard' && $newId) {
        $pf = $_SESSION['prefill'] ?? [];
        $pf['paper_id'] = $newId;
        $_SESSION['prefill'] = $pf;
        redirect('wizard');
    }
    redirect('papers');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $pid = (int)($_POST['paper_id'] ?? 0);
    if (!$pid) { redirect('papers'); }
    try {
        $existing = db()->query("SELECT spec_file, doc_file FROM papers WHERE id=$pid")->fetch();
        $oldDoc  = $existing['doc_file']  ?? '';
        $oldSpec = $existing['spec_file'] ?? '';
        $doc  = handle_upload('doc_file', 'paperdoc');
        $spec = handle_upload('spec_file', 'paper');
        // Keine neue Datei → alte behalten
        if (!$doc)  { $doc  = $oldDoc; }
        if (!$spec) { $spec = $oldSpec; }
        db()->prepare("UPDATE papers SET name=?, manufacturer=?, grammage=?, thickness_um=?, structure=?,
            food_contact=?, recyclable_note=?, recycled_content=?, compostable=?, material_code=?, spec_file=?, doc_file=?, doc_valid_until=?
            WHERE id=?")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['manufacturer'] ?? ''), trim($_POST['grammage'] ?? ''),
            trim($_POST['thickness_um'] ?? ''), trim($_POST['structure'] ?? ''),
            isset($_POST['food_contact']) ? 1 : 0,
            trim($_POST['recyclable_note'] ?? ''),
            trim($_POST['recycled_content'] ?? ''),
            isset($_POST['compostable']) ? 1 : 0,
            trim($_POST['material_code'] ?? ''),
            $spec, $doc, trim($_POST['doc_valid_until'] ?? ''),
            $pid,
        ]);
        // Ersetzte Dateien aufräumen (erst nach dem UPDATE, damit die
        // "noch verwendet?"-Prüfung den neuen Stand sieht)
        if ($doc !== $oldDoc)   { unlink_if_unused_paper_file($oldDoc); }
        if ($spec !== $oldSpec) { unlink_if_unused_paper_file($oldSpec); }
        flash('Papier aktualisiert.');
    } catch (Throwable $ex) {
        flash('Fehler: ' . $ex->getMessage());
    }
    redirect('papers');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newId = 0;
    try {
        $doc  = handle_upload('doc_file', 'paperdoc');   // Konformitätserklärung
        $spec = handle_upload('spec_file', 'paper');     // Technisches Datenblatt
        db()->prepare("INSERT INTO papers (name,manufacturer,grammage,thickness_um,structure,food_contact,recyclable_note,recycled_content,compostable,material_code,spec_file,doc_file,doc_valid_until)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            trim($_POST['name'] ?? ''), trim($_POST['manufacturer'] ?? ''), trim($_POST['grammage'] ?? ''),
            trim($_POST['thickness_um'] ?? ''), trim($_POST['structure'] ?? ''),
            isset($_POST['food_contact']) ? 1 : 0,
            trim($_POST['recyclable_note'] ?? ''),
            trim($_POST['recycled_content'] ?? ''),
            isset($_POST['compostable']) ? 1 : 0,
            trim($_POST['material_code'] ?? ''),
            $spec, $doc, trim($_POST['doc_valid_until'] ?? ''),
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

$editId = (int)($_GET['edit'] ?? 0);
$editP  = $editId ? db()->query("SELECT * FROM papers WHERE id=$editId")->fetch() : null;
$ep = fn(string $k, string $d = '') => e((string)($editP[$k] ?? $d));

$backToWizard = (($_GET['return'] ?? '') === 'wizard');

$rows = db()->query("SELECT * FROM papers ORDER BY name")->fetchAll();

ob_start(); ?>
<h1>Papiere / Kartons</h1>
<p class="lead">Materialdatenblätter einmal hinterlegen – bei Aufträgen dann nur noch aus der Liste wählen.</p>

<?php if ($backToWizard): ?>
<div class="note">Du legst gerade ein Papier für eine laufende Erklärung an. Nach dem Speichern geht es <b>automatisch zurück</b> – das neue Papier ist dann vorausgewählt. <a href="<?= url('wizard') ?>">Ohne Speichern zurück zur Erklärung →</a></div>
<?php endif; ?>

<details class="card ai-help">
  <summary><b>🤖 KI-Hilfe: Papierwerte recherchieren lassen</b> — <span class="muted">Prompt kopieren, KI fragen, JSON zurück ins Import-Feld</span></summary>
  <p class="hint" style="margin-top:10px">Zwei-Schritt-Weg: <b>(1)</b> Prompt kopieren, in Claude/ChatGPT einfügen, Papiername eintragen. Die KI antwortet mit einer Liste <b>und</b> einem JSON-Block. <b>(2)</b> Nur den JSON-Block kopieren und unten in „Import per JSON" einfügen — das Papier wird direkt angelegt.</p>
  <textarea id="ai-prompt" readonly rows="22" style="width:100%;font-family:Consolas,'Courier New',monospace;font-size:12.5px;padding:10px;border:1px solid var(--line);border-radius:6px;background:#f7fafb;">Ich brauche für unsere technische Dokumentation nach EU-Verpackungsverordnung (PPWR, VO (EU) 2025/40) eine kurze Materialübersicht zu folgendem Karton/Papier:

  Bezeichnung: [HIER PAPIERNAME EINSETZEN, z. B. „Invercote G 350 g/m²"]

Bitte recherchiere im aktuellen offiziellen Datenblatt des Herstellers und gib mir – falls verfügbar mit Fundstelle/Link – folgende Angaben:

  1. Hersteller (z. B. „Iggesund/Holmen", „MM Karton", „Stora Enso")
  2. Grammatur in g/m²
  3. Dicke/Caliper in µm (für die genannte Grammatur)
  4. Schichtaufbau/Beschreibung in einem Satz (z. B. „SBB, mehrlagig, Frischfaser, dreifach gestrichen" oder „GD2, Recyclingkarton mit PIW/PCW-Anteil")
  5. Recyclingfähigkeit (z. B. „EN 13430 erfüllt", „CEPI-Recyclability-Test bestanden") — bitte den genauen Wortlaut aus dem Datenblatt
  6. Rezyklatanteil (z. B. „Frischfaser 100 %" oder „PCW 60 %, PIW 20 %, Frischfaser 20 %")
  7. Industriell kompostierbar nach EN 13432: ja oder nein? (Achtung: nur „ja", wenn das Datenblatt EN 13432 ausdrücklich nennt.)
  8. Eignung für Lebensmittelkontakt (Verordnung (EG) Nr. 1935/2004, BfR-Empfehlung XXXVI): ja oder nein?
  9. PPWR Art. 5 (PFAS, Schwermetalle ≤ 100 mg/kg): im Datenblatt bestätigt oder nur separat als Konformitätserklärung verfügbar?
 10. Materialcode nach DIN 6120 / Entscheidung 97/129/EG (aus dem Kartontyp abzuleiten): PAP 20 = Wellpappe, PAP 21 = Karton/Vollpappe, PAP 22 = Papier. Für die üblichen Faltschachtelkartons (SBB, GD2, GC1, GC2, GZ, UC1, UC2, Solid Bleached Board) ist es PAP 21. Wenn der Kartontyp aus dem Datenblatt eindeutig hervorgeht, den Code angeben; sonst leer lassen.

Bitte nur bestätigte Angaben aus dem offiziellen Datenblatt / offiziellen Herstellerquellen — nicht raten. Wenn ein Punkt im Datenblatt nicht auftaucht, schreib „nicht ausgewiesen" hin.

Antworte in ZWEI Blöcken:

Block A — die 10 Punkte kompakt in Textform mit Quelle.

Block B — exakt dieses JSON-Objekt (ohne weiteren Text), sodass ich es direkt in unser Tool importieren kann. Felder, für die im Datenblatt nichts steht, als leerer String bzw. false. Keine zusätzlichen Felder erfinden.

```json
{
  "name": "[Bezeichnung wie oben eingegeben]",
  "manufacturer": "",
  "grammage": "",
  "thickness_um": "",
  "structure": "",
  "recyclable_note": "",
  "recycled_content": "",
  "compostable": false,
  "food_contact": false,
  "material_code": ""
}
```</textarea>
  <div class="btn-row" style="margin-top:8px">
    <button type="button" class="btn secondary" onclick="copyPrompt()">📋 Prompt kopieren</button>
    <span id="ai-copied" class="muted" style="margin-left:8px"></span>
  </div>

  <h3 style="margin-top:18px">Import per JSON</h3>
  <p class="hint">Nur den JSON-Block der KI-Antwort hier einfügen (mit oder ohne <code>```json</code>-Wrapper) und importieren. Konformitätserklärung/Datenblatt danach oben nachreichen.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="importjson">
    <?php if ($backToWizard): ?><input type="hidden" name="return" value="wizard"><?php endif; ?>
    <textarea name="json" rows="8" placeholder='{"name":"Invercote G 350 g/m²","manufacturer":"Iggesund/Holmen", ...}' style="width:100%;font-family:Consolas,'Courier New',monospace;font-size:12.5px;padding:10px;border:1px solid var(--line);border-radius:6px"></textarea>
    <div class="btn-row" style="margin-top:6px"><button class="btn" type="submit">⬆︎ Papier aus JSON anlegen</button></div>
  </form>
</details>

<script>
function copyPrompt(){
  const t=document.getElementById('ai-prompt');
  t.select(); t.setSelectionRange(0,99999);
  navigator.clipboard.writeText(t.value).then(()=>{
    document.getElementById('ai-copied').textContent='✓ In die Zwischenablage kopiert';
    setTimeout(()=>{document.getElementById('ai-copied').textContent='';},2500);
  }).catch(()=>{
    document.getElementById('ai-copied').textContent='Bitte manuell markieren und kopieren (Strg+C).';
  });
}
</script>

<div class="card">
  <?php if ($editP): ?>
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0">Papier bearbeiten – <?= e($editP['name']) ?></h3>
      <a href="<?= url('papers') ?>" class="muted">← zurück zur Übersicht</a>
    </div>
  <?php else: ?>
    <h3>Neues Papier hinzufügen</h3>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($editP): ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="paper_id" value="<?= (int)$editP['id'] ?>">
    <?php endif; ?>
    <?php if ($backToWizard): ?><input type="hidden" name="return" value="wizard"><?php endif; ?>
    <div class="row">
      <div><label>Bezeichnung <span class="hint">(z. B. Invercote G 350 g/m²)</span><input type="text" name="name" required value="<?= $ep('name') ?>"></label></div>
      <div><label>Hersteller<input type="text" name="manufacturer" placeholder="Iggesund / Holmen" value="<?= $ep('manufacturer') ?>"></label></div>
    </div>
    <div class="row">
      <div><label>Grammatur (g/m²)<?= info('Flächengewicht des Papiers in Gramm pro Quadratmeter. Findet man auf jedem Datenblatt. Bei uns typischerweise 250–400.') ?><input type="text" name="grammage" placeholder="350" value="<?= $ep('grammage') ?>"></label></div>
      <div><label>Dicke (µm)<?= info('Kartondicke in Mikrometern (1 µm = 1/1000 mm). Wird auf dem Datenblatt oft „Thickness/Caliper" genannt.') ?><input type="text" name="thickness_um" placeholder="465" value="<?= $ep('thickness_um') ?>"></label></div>
    </div>
    <label>Schichtaufbau / Beschreibung<?= info('Kurz die Kartonart: z. B. SBB (Solid Bleached Board = Frischfaserkarton) oder GD2 (Recyclingkarton). Steht auf dem Datenblatt oben.') ?>
        <input type="text" name="structure" placeholder="SBB, mehrlagig, Frischfaser, dreifach gestrichen" value="<?= $ep('structure') ?>">
    </label>

    <h4 style="margin:14px 0 4px;color:#14425f">Angaben zur PPWR</h4>

    <label>Recyclingfähigkeit<?= info('PPWR Art. 6. Steht auf dem Datenblatt meist als „EN 13430 erfüllt" oder „CEPI-recyclingfähig". Wenn du unsicher bist, „nicht ausgewiesen" eintragen – das PDF verwendet dann eine neutrale Formulierung.') ?>
        <input type="text" name="recyclable_note" placeholder="z. B. EN 13430 erfüllt / CEPI-recyclingfähig" value="<?= $ep('recyclable_note') ?>">
    </label>

    <label>Rezyklatanteil<?= info('PPWR Art. 7. Anteil an wiederverwertetem Material. Bei Frischfaserkarton wie Invercote G: „Frischfaser 100 %". Bei Recyclingkarton wie Multicolor Mirabell: „PCW 60 %, PIW 20 %" oder ähnlich – steht so auf dem Datenblatt.') ?>
        <input type="text" name="recycled_content" placeholder="z. B. Frischfaser 100 %" value="<?= $ep('recycled_content') ?>">
    </label>

    <label><input type="checkbox" name="compostable" value="1" <?= (!empty($editP['compostable'])) ? 'checked' : '' ?>> Industriell kompostierbar (EN 13432 zertifiziert)<?= info('PPWR Art. 8/9. Nur ankreuzen, wenn das Datenblatt ausdrücklich „EN 13432 zertifiziert" nennt.') ?></label>

    <label>Materialcode (PAP-Code) – nur eintragen, wenn Symbol tatsächlich aufgedruckt wird<?= info('PPWR Art. 12: Recycling-Piktogramm für die Sortierung. PAP 20 = Wellpappe, PAP 21 = Karton/Vollpappe (Standard für Faltschachteln), PAP 22 = Papier. WICHTIG: Nur eintragen, wenn ihr das Symbol tatsächlich auf die Schachtel druckt. Sonst leer lassen – das PDF setzt dann den rechtlich sicheren Standardvermerk „harmonisierte Kennzeichnung nach Vorgabe anzubringen".') ?> <span class="hint">(optional)</span>
        <select name="material_code" style="max-width:220px">
            <?php $mc = $editP['material_code'] ?? ''; ?>
            <option value=""      <?= $mc === ''      ? 'selected' : '' ?>>— nicht angegeben —</option>
            <option value="PAP 20" <?= $mc === 'PAP 20' ? 'selected' : '' ?>>PAP 20 (Wellpappe)</option>
            <option value="PAP 21" <?= $mc === 'PAP 21' ? 'selected' : '' ?>>PAP 21 (Karton/Vollpappe)</option>
            <option value="PAP 22" <?= $mc === 'PAP 22' ? 'selected' : '' ?>>PAP 22 (Papier)</option>
        </select>
    </label>

    <label><input type="checkbox" name="food_contact" value="1" <?= (!empty($editP['food_contact'])) ? 'checked' : '' ?>> Lebensmittelkontakt-Eignung<?= info('Nachweise nach EG 1935/2004 und BfR-Empfehlung XXXVI. Nur ankreuzen, wenn der Karton dafür geeignet ist.') ?> (1935/2004, BfR XXXVI) dokumentiert</label>

    <h4 style="margin:14px 0 4px;color:#14425f">Nachweisdokumente</h4>

    <label>Konformitätserklärung des Herstellers (PDF)<?= info('Der eigentliche Nachweis für PPWR Art. 5 (PFAS, Schwermetalle ≤ 100 mg/kg, REACH). Wird ins interne PDF eingebettet.') ?> <span class="hint">– empfohlen</span>
        <input type="file" name="doc_file" accept=".pdf,.png,.jpg,.jpeg">
        <?php if (!empty($editP['doc_file'])): ?>
          <span class="hint">aktuell hinterlegt: <a href="<?= url('pdf', ['file' => $editP['doc_file']]) ?>" target="_blank"><?= e($editP['doc_file']) ?></a> – leer lassen zum Behalten, oder neue Datei wählen zum Ersetzen</span>
        <?php endif; ?>
    </label>
    <label>Gültig bis<?= info('Ablaufdatum der Konformitätserklärung (steht meist unten auf dem Dokument, z. B. „Gültigkeit 2 Jahre"). Das Tool warnt 60 Tage vor Ablauf.') ?> <span class="hint">(optional)</span>
        <input type="date" name="doc_valid_until" value="<?= $ep('doc_valid_until') ?>" style="max-width:200px">
    </label>
    <label>Technisches Datenblatt (PDF)<?= info('Reines Produktdatenblatt (Grammatur, Aufbau, Prüfnormen). Kein Konformitätsnachweis, aber gute technische Beschreibung.') ?> <span class="hint">– optional</span>
        <input type="file" name="spec_file" accept=".pdf,.png,.jpg,.jpeg">
        <?php if (!empty($editP['spec_file'])): ?>
          <span class="hint">aktuell hinterlegt: <a href="<?= url('pdf', ['file' => $editP['spec_file']]) ?>" target="_blank"><?= e($editP['spec_file']) ?></a> – leer lassen zum Behalten, oder neue Datei wählen zum Ersetzen</span>
        <?php endif; ?>
    </label>
    <div class="btn-row"><button class="btn" type="submit"><?= $editP ? 'Änderungen speichern' : 'Speichern' ?></button></div>
  </form>
</div>

<div class="card">
  <h3>Hinterlegte Papiere (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?><p class="muted">Noch keine Papiere hinterlegt.</p><?php else: ?>
  <table class="list">
    <tr><th>Bezeichnung</th><th>Hersteller</th><th>g/m²</th><th>Konformität</th><th>Gültig</th><th>Datenblatt</th><th></th></tr>
    <?php foreach ($rows as $r):
        $v = doc_validity($r['doc_valid_until'] ?? '');
        $vPill = ['ok' => 'ok', 'soon' => 'warn', 'expired' => 'warn', 'none' => 'na'][$v['state']];
        $vText = $v['label'] ?: '<span class="muted">–</span>';
    ?>
      <tr>
        <td><?= e($r['name']) ?><?php if ($r['food_contact']): ?> <span class="pill ok">LM-Kontakt</span><?php endif; ?></td>
        <td class="muted"><?= e($r['manufacturer']) ?></td>
        <td><?= e($r['grammage']) ?></td>
        <td><?= !empty($r['doc_file']) ? '<a href="' . url('pdf', ['file' => $r['doc_file']]) . '" target="_blank">öffnen</a>' : '<span class="pill warn">fehlt</span>' ?></td>
        <td><?= $v['state'] === 'none' ? '<span class="muted">–</span>' : '<span class="pill ' . $vPill . '">' . e($v['label']) . '</span>' ?></td>
        <td><?= $r['spec_file'] ? '<a href="' . url('pdf', ['file' => $r['spec_file']]) . '" target="_blank">öffnen</a>' : '<span class="muted">–</span>' ?></td>
        <td>
          <a href="<?= url('papers', ['edit' => $r['id']]) ?>">bearbeiten</a><br>
          <a href="<?= url('papers', ['clone' => $r['id']]) ?>">duplizieren</a><br>
          <a class="muted" href="<?= url('papers', ['del' => $r['id']]) ?>" onclick="return confirm('Papier löschen?')">löschen</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Papiere', $content);
