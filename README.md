# PPWR-Konformitätserklärung – Faltschachteln

Ein geführtes Web-Tool (PHP) zur Erstellung von **EU-Konformitätserklärungen** nach der
Verpackungsverordnung **PPWR – Verordnung (EU) 2025/40** (Artikel 39 i. V. m. Anhang VIII)
für Faltschachteln. Optimiert für eine kleine Druckerei mit gelegentlicher Faltschachtel-Produktion
in **Offset-** oder **Toner-Digitaldruck** – und für **zugekaufte** Schachteln.

Am Ende steht ein **fertiges PDF**: Erklärung + technische Dokumentation (Anhang VII) + eingebundene
**Stanzkontur** und – beim Zukauf – die **Lieferanten-DoC**.

## Funktionen

- **Kompakte Einseiten-Maske**: nur *Produkt/Bezeichnung* ist Pflicht, alles andere optional.
  Herstellerdaten kommen automatisch aus dem Firmenprofil.
- **Neutrales PDF**: die erzeugte Erklärung ist immer identisch und verrät nichts darüber,
  ob eine Schachtel selbst produziert oder **zugekauft** wurde.
- **Interner Nachweis** (z. B. Lieferanten-DoC bei Zukauf): optional hochladbar, wird nur intern
  zur 10-Jahres-Ablage gespeichert und **nicht** ins Kunden-PDF eingebunden.
- **Wiederverwendung** (einmal anlegen, immer wieder nutzen):
  - Firmenprofil (Hersteller) – steht automatisch in jeder Erklärung
  - Papiere/Kartons inkl. Herstellerdatenblatt (z. B. Invercote G) – per Dropdown wählbar
  - Schachtel-Vorlagen (Maße + Stanzkontur)
- **Uploads**: Stanzkontur (PDF/SVG/PNG, wird ins PDF eingebunden) und interner Nachweis (PDF)
- **PDF-Erstellung** mit mPDF; die Stanzkontur wird per FPDI eingebettet
- **Wiederholaufträge**: bestehende Erklärung übernehmen, nur Charge/Datum/Nummer anpassen

> Rechtlicher Hintergrund: Beim Zukauf und Weiterverkauf unter eigenem Namen gilt regelmäßig die
> Eigenmarken-/Own-Brand-Regel – ihr geltet als Hersteller und stellt die Erklärung selbst aus.
> Deshalb ist das Ausgabe-Dokument bewusst neutral; der Lieferantennachweis bleibt intern.

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
