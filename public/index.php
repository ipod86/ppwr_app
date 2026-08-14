<?php
/**
 * Front-Controller / Router.
 * Aufruf: index.php?p=<seite>
 */
require __DIR__ . '/../src/bootstrap.php';

csrf_check();

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard'));
$allowed = ['dashboard', 'producer', 'papers', 'materials', 'suppliers', 'boxes', 'wizard', 'jobs', 'pdf'];
if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}
require __DIR__ . '/../src/pages/' . $page . '.php';
