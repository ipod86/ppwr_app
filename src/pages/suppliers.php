<?php
if (($_GET['del'] ?? '') !== '') {
    db()->prepare("DELETE FROM suppliers WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Lieferant gelöscht.');
    redirect('suppliers');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare("INSERT INTO suppliers (name,country,eu,contact) VALUES (?,?,?,?)")->execute([
        trim($_POST['name'] ?? ''), trim($_POST['country'] ?? 'Deutschland'),
        isset($_POST['eu']) ? 1 : 0, trim($_POST['contact'] ?? ''),
    ]);
    flash('Lieferant gespeichert.');
    redirect('suppliers');
}

$rows = db()->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

ob_start(); ?>
<h1>Lieferanten (Zukauf)</h1>
<p class="lead">Für fertig zugekaufte Schachteln (z. B. Packex). EU/Nicht-EU steuert die rechtliche Rolle im Assistenten.</p>

<div class="card">
  <h3>Neuen Lieferanten hinzufügen</h3>
  <form method="post">
    <?= csrf_field() ?>
    <div class="row">
      <div><label>Name<input type="text" name="name" required placeholder="Packex GmbH"></label></div>
      <div><label>Land<input type="text" name="country" value="Deutschland"></label></div>
    </div>
    <label><input type="checkbox" name="eu" value="1" checked> Sitz in der EU</label>
    <label>Kontakt<input type="text" name="contact"></label>
    <div class="btn-row"><button class="btn" type="submit">Speichern</button></div>
  </form>
</div>

<div class="card">
  <h3>Hinterlegte Lieferanten (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?><p class="muted">Noch keine Lieferanten hinterlegt.</p><?php else: ?>
  <table class="list">
    <tr><th>Name</th><th>Land</th><th>EU</th><th>Kontakt</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td>
        <td class="muted"><?= e($r['country']) ?></td>
        <td><?= $r['eu'] ? '<span class="pill ok">EU</span>' : '<span class="pill warn">Nicht-EU</span>' ?></td>
        <td class="muted"><?= e($r['contact']) ?></td>
        <td><a class="muted" href="<?= url('suppliers', ['del' => $r['id']]) ?>" onclick="return confirm('Löschen?')">löschen</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
layout('Lieferanten', $content);
