# PPWR-Konformitätserklärung – Faltschachteln

Ein geführtes Web-Tool (PHP) zur Erstellung von **EU-Konformitätserklärungen** nach der
Verpackungsverordnung **PPWR – Verordnung (EU) 2025/40** (Artikel 39 i. V. m. Anhang VIII)
für Faltschachteln. Optimiert für eine kleine Druckerei mit gelegentlicher Faltschachtel-Produktion
in **Offset-** oder **Toner-Digitaldruck** – und für **zugekaufte** Schachteln.

Am Ende steht ein **fertiges PDF**: Erklärung + technische Dokumentation (Anhang VII) + eingebundene
**Stanzkontur** und – beim Zukauf – die **Lieferanten-DoC**.

## Funktionen

- **Geführter Assistent (Wizard)** in logischer Reihenfolge, zwei Fälle:
  - *Eigenproduktion* (ihr seid Hersteller)
  - *Zukauf* (z. B. Packex) mit Weiterverkauf unter eigenem Namen → Eigenmarken-/Own-Brand-Regel;
    EU-/Nicht-EU-Lieferant wird automatisch berücksichtigt
- **Wiederverwendung per Dropdown** (einmal anlegen, immer wieder nutzen):
  - Firmenprofil (Hersteller) – steht automatisch in jeder Erklärung
  - Papiere/Kartons inkl. Herstellerdatenblatt (z. B. Invercote G)
  - Farben/Toner/Kleber/Lack inkl. einmaliger EuPIA-Konformitätserklärungen (Art. 5)
  - Lieferanten, Schachtel-Vorlagen (Maße + Stanzkontur)
- **Uploads**: Papierspezifikation, Stanzkontur (PDF/SVG/PNG), Lieferanten-DoC (PDF)
- **PDF-Erstellung** mit mPDF; Stanzkontur und Lieferanten-DoC werden per FPDI eingebettet
- **Wiederholaufträge**: bestehende Erklärung 1:1 übernehmen, nur Charge/Datum/Nummer anpassen

## Technik

- PHP ≥ 8.1, **SQLite** (Datei `data/app.db`, keine DB-Server nötig)
- Abhängigkeiten: `mpdf/mpdf`, `setasign/fpdi` – **im `vendor/`-Verzeichnis mitgeliefert**,
  läuft daher auf Shared-Hosting ohne Composer
- Keine externen Dienste, alle Daten bleiben lokal

## Installation (z. B. Shared-Hosting / Artfiles)

1. Gesamtes Verzeichnis auf den Webspace hochladen.
2. Den **DocumentRoot** auf den Ordner **`public/`** zeigen lassen
   (oder `public/` als Startverzeichnis der (Sub-)Domain wählen).
3. Sicherstellen, dass **`data/`** durch PHP **beschreibbar** ist (Uploads + SQLite-DB).
   Falls möglich, `data/` außerhalb des Webroots belassen (Standard hier: eine Ebene über `public/`).
4. Seite aufrufen → unter **Firmenprofil** die Herstellerdaten eintragen, dann Papiere/Materialien anlegen.

> Kann `data/` nicht außerhalb des Webroots liegen, schützt die mitgelieferte `public/.htaccess`
> den direkten Zugriff (nur bei Apache mit `mod_rewrite`).

## Lokaler Test

```bash
php -S localhost:8000 -t public
# Browser: http://localhost:8000
```

## Verzeichnisstruktur

```
public/            Webroot (index.php Router, assets, .htaccess)
src/
  bootstrap.php    Autoload, Session, DB-Init
  db.php           SQLite-Schema & Helfer
  helpers.php      Escaping, CSRF, Uploads, Layout
  Pdf.php          PDF-Erzeugung (mPDF + FPDI-Einbettung)
  pages/           Seiten (dashboard, wizard, jobs, papers, ...)
  views/           layout.php, doc_template.php (PDF-HTML)
data/              app.db + uploads/ (zur Laufzeit, nicht versioniert)
vendor/            gebündelte Bibliotheken
```

## Rechtlicher Hinweis

Das Tool ist eine **technische Arbeitshilfe**, keine Rechtsberatung. Die inhaltliche Richtigkeit
der produkt- und prüfspezifischen Angaben (Art. 5–12) ist vor Unterzeichnung zu verifizieren.
Beim **Zukauf** und Weiterverkauf unter eigenem Namen greift regelmäßig die Eigenmarken-/Own-Brand-Regel
(ihr geltet als Hersteller); die genaue Rollenzuordnung sollte im Zweifel mit dem Lieferanten bzw.
rechtlich bestätigt werden.
