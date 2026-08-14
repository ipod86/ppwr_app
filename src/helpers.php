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
