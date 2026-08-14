<?php
/**
 * Zentrale Konfiguration.
 * Für den Produktivbetrieb ggf. nur DATA_DIR / APP_NAME anpassen.
 */

define('APP_NAME', 'PPWR-Konformitätserklärung – Faltschachteln');
define('APP_ROOT', __DIR__);
define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', DATA_DIR . '/uploads');
define('PDF_DIR', UPLOAD_DIR . '/pdf');
define('DB_FILE', DATA_DIR . '/app.db');

// Erlaubte Upload-Typen (Endung => MIME-Prüfung erfolgt zusätzlich in helpers.php)
const ALLOWED_UPLOAD_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'svg'];
const MAX_UPLOAD_BYTES = 20 * 1024 * 1024; // 20 MB

// Zeitzone für Datumsangaben
date_default_timezone_set('Europe/Berlin');
