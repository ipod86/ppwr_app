<?php
/**
 * SQLite-Datenbank: Verbindung (Singleton) und Schema-Migration.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function db_init(): void
{
    foreach ([DATA_DIR, UPLOAD_DIR, PDF_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS producer (
            id            INTEGER PRIMARY KEY CHECK (id = 1),
            company       TEXT NOT NULL DEFAULT '',
            street        TEXT NOT NULL DEFAULT '',
            zip           TEXT NOT NULL DEFAULT '',
            city          TEXT NOT NULL DEFAULT '',
            country       TEXT NOT NULL DEFAULT 'Deutschland',
            vat           TEXT NOT NULL DEFAULT '',
            contact       TEXT NOT NULL DEFAULT '',
            signer_name   TEXT NOT NULL DEFAULT '',
            signer_role   TEXT NOT NULL DEFAULT '',
            place         TEXT NOT NULL DEFAULT '',
            logo_path     TEXT NOT NULL DEFAULT '',
            doc_prefix    TEXT NOT NULL DEFAULT 'DoC',
            doc_counter   INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("INSERT OR IGNORE INTO producer (id) VALUES (1)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS papers (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            name           TEXT NOT NULL,
            manufacturer   TEXT NOT NULL DEFAULT '',
            grammage       TEXT NOT NULL DEFAULT '',
            thickness_um   TEXT NOT NULL DEFAULT '',
            structure      TEXT NOT NULL DEFAULT '',
            food_contact   INTEGER NOT NULL DEFAULT 0,
            recyclable_note TEXT NOT NULL DEFAULT '',
            spec_file      TEXT NOT NULL DEFAULT '',
            created_at     TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    // Farben / Toner / Kleber / Lack – die einmaligen EuPIA-Nachweise (Teil C.1)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS materials (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            kind          TEXT NOT NULL,               -- ink | toner | adhesive | varnish
            name          TEXT NOT NULL,
            manufacturer  TEXT NOT NULL DEFAULT '',
            eupia         INTEGER NOT NULL DEFAULT 1,   -- EuPIA-konform bestätigt
            doc_file      TEXT NOT NULL DEFAULT '',     -- hochgeladene Lieferantenerklärung
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            name          TEXT NOT NULL,
            country       TEXT NOT NULL DEFAULT 'Deutschland',
            eu            INTEGER NOT NULL DEFAULT 1,
            contact       TEXT NOT NULL DEFAULT '',
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS boxes (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            name          TEXT NOT NULL,
            length_mm     TEXT NOT NULL DEFAULT '',
            width_mm      TEXT NOT NULL DEFAULT '',
            height_mm     TEXT NOT NULL DEFAULT '',
            contour_file  TEXT NOT NULL DEFAULT '',
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $createJobs = "
        CREATE TABLE IF NOT EXISTS jobs (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            mode            TEXT NOT NULL DEFAULT 'self',   -- self | buyin_eu | buyin_noneu
            doc_number      TEXT NOT NULL DEFAULT '',
            product_name    TEXT NOT NULL DEFAULT '',
            article_no      TEXT NOT NULL DEFAULT '',
            packed_item     TEXT NOT NULL DEFAULT '',
            intended_use    TEXT NOT NULL DEFAULT '',
            length_mm       TEXT NOT NULL DEFAULT '',
            width_mm        TEXT NOT NULL DEFAULT '',
            height_mm       TEXT NOT NULL DEFAULT '',
            paper_id        INTEGER,
            print_method    TEXT NOT NULL DEFAULT '',
            has_lamination  INTEGER NOT NULL DEFAULT 0,
            material_ids    TEXT NOT NULL DEFAULT '[]',
            supplier_id     INTEGER,
            supplier_doc    TEXT NOT NULL DEFAULT '',
            contour_file    TEXT NOT NULL DEFAULT '',
            batch           TEXT NOT NULL DEFAULT '',
            production_date TEXT NOT NULL DEFAULT '',
            place           TEXT NOT NULL DEFAULT '',
            date_issued     TEXT NOT NULL DEFAULT '',
            signer_name     TEXT NOT NULL DEFAULT '',
            signer_role     TEXT NOT NULL DEFAULT '',
            pdf_path        TEXT NOT NULL DEFAULT '',
            created_at      TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ";
    $pdo->exec($createJobs);

    // ── Migrationen: fehlende Spalten ergänzen ──────────────────────────
    $cols = array_column($pdo->query("PRAGMA table_info(jobs)")->fetchAll(), 'name');
    if (!in_array('internal_note', $cols, true)) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN internal_note TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('pdf_intern', $cols, true)) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN pdf_intern TEXT NOT NULL DEFAULT ''");
    }
    // Papier: zweites Upload-Feld für Konformitätserklärung
    $pcols = array_column($pdo->query("PRAGMA table_info(papers)")->fetchAll(), 'name');
    if (!in_array('doc_file', $pcols, true)) {
        $pdo->exec("ALTER TABLE papers ADD COLUMN doc_file TEXT NOT NULL DEFAULT ''");
    }
    // Papier: Angaben für Art. 6/7/8-9 der PPWR
    if (!in_array('recycled_content', $pcols, true)) {
        $pdo->exec("ALTER TABLE papers ADD COLUMN recycled_content TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('compostable', $pcols, true)) {
        $pdo->exec("ALTER TABLE papers ADD COLUMN compostable INTEGER NOT NULL DEFAULT 0");
    }
    // Auftrag: Angaben für Art. 10/11/12 der PPWR
    $jcols = array_column($pdo->query("PRAGMA table_info(jobs)")->fetchAll(), 'name');
    if (!in_array('minimization_note', $jcols, true)) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN minimization_note TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('reusable', $jcols, true)) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN reusable TEXT NOT NULL DEFAULT 'einweg'");
    }
    if (!in_array('marking_note', $jcols, true)) {
        $pdo->exec("ALTER TABLE jobs ADD COLUMN marking_note TEXT NOT NULL DEFAULT ''");
    }
}

function producer(): array
{
    return db()->query("SELECT * FROM producer WHERE id = 1")->fetch() ?: [];
}

function next_doc_number(): string
{
    $p = producer();
    $n = ((int)($p['doc_counter'] ?? 0)) + 1;
    $prefix = $p['doc_prefix'] ?: 'DoC';
    return sprintf('%s-%s-%04d', $prefix, date('Y'), $n);
}

function bump_doc_counter(): void
{
    db()->exec("UPDATE producer SET doc_counter = doc_counter + 1 WHERE id = 1");
}
