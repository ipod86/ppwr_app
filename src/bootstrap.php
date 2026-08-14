<?php
/**
 * Bootstrap: Autoload, Konfiguration, DB, Session, Helpers.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1'); // in Produktion ggf. auf '0'

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/Pdf.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

db_init();
