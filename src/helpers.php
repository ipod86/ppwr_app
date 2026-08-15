<?php
/**
 * Hilfsfunktionen: Escaping, CSRF, Flash, Uploads, Rendering.
 */

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $t = $_POST['_csrf'] ?? '';
        if (!hash_equals(csrf_token(), (string)$t)) {
            http_response_code(400);
            exit('Ungültiges CSRF-Token. Bitte Seite neu laden.');
        }
    }
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/**
 * Verarbeitet einen Datei-Upload und legt ihn im UPLOAD_DIR ab.
 * @return string relativer Dateiname (im UPLOAD_DIR) oder '' wenn keine Datei.
 */
function handle_upload(string $field, string $prefix = 'file'): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload-Fehler (Code ' . $f['error'] . ').');
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Datei zu groß (max. ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB).');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_UPLOAD_EXT, true)) {
        throw new RuntimeException('Dateityp nicht erlaubt: .' . $ext);
    }
    $safe = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $safe;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        // Fallback für CLI-Tests
        if (!rename($f['tmp_name'], $dest)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
    }
    return $safe;
}

function upload_path(string $file): string
{
    return $file ? UPLOAD_DIR . '/' . $file : '';
}

function is_pdf(string $file): bool
{
    return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
}

function is_image(string $file): bool
{
    return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'svg'], true);
}

/**
 * Gibt eine fertige Content-HTML-Zeichenkette im Layout aus.
 */
function layout(string $title, string $content): void
{
    $title = $title ?: APP_NAME;
    include __DIR__ . '/views/layout.php';
}

/** Kleiner Helfer für aktive Navigation. */
function active(string $p): string
{
    return (($_GET['p'] ?? 'dashboard') === $p) ? ' class="active"' : '';
}

/**
 * Info-Icon mit klickbarem Custom-Tooltip.
 * Auf Desktop: Hover zeigt den Text. Auf Mobil: Tap zeigt/schließt ihn.
 * Kleines JS im Layout kümmert sich um den Klick.
 */
function info(string $text): string
{
    return '<button type="button" class="info-tip" data-tip="' . e($text) . '" aria-label="Info: ' . e($text) . '">i</button>';
}

/**
 * Ablauf-Status einer Papier-Konformitätserklärung.
 * @return array{state:string, label:string, days:?int}  state = ok|soon|expired|none
 */
function doc_validity(string $validUntil): array
{
    $validUntil = trim($validUntil);
    if ($validUntil === '') {
        return ['state' => 'none', 'label' => '', 'days' => null];
    }
    $ts = strtotime($validUntil . ' 23:59:59');
    if ($ts === false) {
        return ['state' => 'none', 'label' => '', 'days' => null];
    }
    $days = (int)floor(($ts - time()) / 86400);
    if ($days < 0) {
        return ['state' => 'expired', 'label' => 'abgelaufen (' . date('d.m.Y', $ts) . ')', 'days' => $days];
    }
    if ($days <= 60) {
        return ['state' => 'soon', 'label' => 'läuft in ' . $days . ' Tagen ab (' . date('d.m.Y', $ts) . ')', 'days' => $days];
    }
    return ['state' => 'ok', 'label' => 'gültig bis ' . date('d.m.Y', $ts), 'days' => $days];
}

/** Erzeugt einen dateisystemsicheren Namen aus beliebigem Text. */
function safe_filename(string $s): string
{
    $s = preg_replace('/[^\pL\pN _.-]+/u', '', $s) ?? '';
    $s = preg_replace('/\s+/', '_', trim($s));
    return trim(mb_substr($s, 0, 80), '_-.');
}

/**
 * Verpackungsart aus jobs.package_kind + package_kind_other in Bezeichnung,
 * Ebene (Primär/Sekundär/Tertiär) und PPWR-Kategoriebegriff auflösen.
 */
function package_kind_info(string $kind, string $other = ''): array
{
    $map = [
        'faltschachtel' => ['name' => 'Faltschachtel',                    'level' => 'primär',    'cat' => 'Verkaufsverpackung'],
        'einlegekarte'  => ['name' => 'Einlegekarte / Blisterkarte',      'level' => 'primär',    'cat' => 'Verkaufsverpackung'],
        'umkarton'      => ['name' => 'Umkarton / Sammelverpackung',      'level' => 'sekundär',  'cat' => 'Umverpackung'],
        'versand'       => ['name' => 'Versand-/Transportkarton',         'level' => 'tertiär',   'cat' => 'Transportverpackung'],
        'sonstige'      => ['name' => trim($other) !== '' ? trim($other) : 'Sonstige Kartonverpackung',
                            'level' => 'primär', 'cat' => 'Verkaufsverpackung'],
    ];
    return $map[$kind] ?? $map['faltschachtel'];
}

/** Beschriftung der Materialart. */
function kind_label(string $kind): string
{
    return [
        'ink'      => 'Druckfarbe (Offset)',
        'toner'    => 'Toner (Digitaldruck)',
        'adhesive' => 'Klebstoff',
        'varnish'  => 'Lack',
    ][$kind] ?? $kind;
}

/**
 * Einfacher HTTP-GET per cURL. GitHub verlangt einen User-Agent auch bei
 * unauthentifizierten Anfragen, sonst gibt's ein 403.
 */
function http_get(string $url, int $timeoutSec = 30): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeoutSec,
        CURLOPT_USERAGENT      => 'ppwr-app-updater',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP-Anfrage fehlgeschlagen: ' . $err);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("HTTP $code bei $url");
    }
    return $body;
}

/**
 * Kopiert ein Verzeichnis rekursiv. $skipTopLevel benennt Einträge, die nur
 * auf der obersten Ebene übersprungen werden (z. B. "data").
 */
function copy_dir_recursive(string $src, string $dst, array $skipTopLevel = []): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $skipTopLevel, true)) {
            continue;
        }
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            copy_dir_recursive($s, $d);
        } else {
            copy($s, $d);
        }
    }
}

/** Löscht ein Verzeichnis rekursiv (z. B. zum Aufräumen von Temp-Ordnern). */
function remove_dir_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $p = $dir . '/' . $item;
        is_dir($p) ? remove_dir_recursive($p) : unlink($p);
    }
    rmdir($dir);
}
