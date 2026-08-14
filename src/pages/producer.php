<?php
$p = producer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logo = $p['logo_path'];
    try {
        $up = handle_upload('logo', 'logo');
        if ($up) {
            $logo = $up;
        }
    } catch (Throwable $ex) {
        flash('Logo-Upload: ' . $ex->getMessage());
    }
    $stmt = db()->prepare("UPDATE producer SET company=?,street=?,zip=?,city=?,country=?,vat=?,contact=?,
        signer_name=?,signer_role=?,place=?,doc_prefix=?,logo_path=? WHERE id=1");
    $stmt->execute([
        trim($_POST['company'] ?? ''), trim($_POST['street'] ?? ''), trim($_POST['zip'] ?? ''),
        trim($_POST['city'] ?? ''), trim($_POST['country'] ?? ''), trim($_POST['vat'] ?? ''),
        trim($_POST['contact'] ?? ''), trim($_POST['signer_name'] ?? ''), trim($_POST['signer_role'] ?? ''),
        trim($_POST['place'] ?? ''), trim($_POST['doc_prefix'] ?? 'DoC') ?: 'DoC', $logo,
    ]);
    flash('Firmenprofil gespeichert.');
    redirect('producer');
}

ob_start(); ?>
<h1>Firmenprofil</h1>
<p class="lead">Diese Angaben erscheinen automatisch als Hersteller in jeder Erklärung.</p>
<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="row">
    <div><label>Firmenname<input type="text" name="company" value="<?= e($p['company']) ?>" required></label></div>
    <div><label>USt-IdNr.<input type="text" name="vat" value="<?= e($p['vat']) ?>"></label></div>
  </div>
  <label>Straße &amp; Hausnummer<input type="text" name="street" value="<?= e($p['street']) ?>"></label>
  <div class="row">
    <div><label>PLZ<input type="text" name="zip" value="<?= e($p['zip']) ?>"></label></div>
    <div><label>Ort<input type="text" name="city" value="<?= e($p['city']) ?>"></label></div>
    <div><label>Land<input type="text" name="country" value="<?= e($p['country'] ?: 'Deutschland') ?>"></label></div>
  </div>
  <label>Kontakt (E-Mail / Telefon)<input type="text" name="contact" value="<?= e($p['contact']) ?>"></label>
  <div class="row">
    <div><label>Unterzeichner – Name<input type="text" name="signer_name" value="<?= e($p['signer_name']) ?>"></label></div>
    <div><label>Unterzeichner – Funktion<input type="text" name="signer_role" value="<?= e($p['signer_role']) ?>"></label></div>
  </div>
  <div class="row">
    <div><label>Ausstellungsort (Standard)<input type="text" name="place" value="<?= e($p['place']) ?>"></label></div>
    <div><label>DoC-Nummernpräfix <span class="hint">(z. B. DoC)</span><input type="text" name="doc_prefix" value="<?= e($p['doc_prefix'] ?: 'DoC') ?>"></label></div>
  </div>
  <label>Logo (optional, PNG/JPG)
    <input type="file" name="logo" accept=".png,.jpg,.jpeg">
    <?php if ($p['logo_path']): ?><span class="hint">aktuell hinterlegt: <?= e($p['logo_path']) ?></span><?php endif; ?>
  </label>
  <div class="btn-row"><button class="btn" type="submit">Speichern</button></div>
</form>
<?php
$content = ob_get_clean();
layout('Firmenprofil', $content);
