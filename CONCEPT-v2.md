# eLeDia | exam2pdf — Konzept v2

## Kernidee

Das Plugin erzeugt PDF-Prüfungsberichte für Quiz-Versuche in Moodle. Der primäre Nutzer ist der **Teacher/Manager**, der über die Quiz-Results-Seite PDFs einzeln oder gebündelt herunterladen kann. Optional können auch **Studenten** ihre eigenen PDFs auf der Review-Seite herunterladen.

## Plugin-Name

- Anzeigename: **eLeDia | exam2pdf**
- Komponente: `local_eledia_exam2pdf` (unverändert)

## Neue Admin-Einstellungen

| Einstellung | Typ | Default | Beschreibung |
|---|---|---|---|
| PDF-Erzeugung | Select | Bei Abgabe | Wann wird das PDF erzeugt? "Bei Abgabe (automatisch)" oder "On-demand (bei Klick)" |
| PDF-Scope | Select | Nur bestanden | Für welche Versuche wird ein PDF angeboten? "Nur bestanden" oder "Alle abgeschlossenen" |
| Student darf herunterladen | Checkbox | Ja | Zeigt den Download-Button auf der Student-Review-Seite |
| Bulk-Format | Select | ZIP | Was liefert der Bulk-Download? "ZIP mit einzelnen PDFs" oder "Ein zusammengefügtes PDF" |
| Ausgabemodus | Select | Download | Bestehend: Download / Email / Beides |
| Aufbewahrungsfrist | Text | 365 | Bestehend: Tage bis Auto-Löschung (0 = nie) |

Bestehende Einstellungen (Show score, Show duration, Show passgrade etc.) bleiben erhalten.

## Features

### 1. Quiz-Results-Integration (Hauptfeature)

**Wo:** Quiz → Results → Grades (die Übersichtstabelle mit allen TN-Versuchen)

**"What to include"-Sektion:** Neue Checkbox-Option **"PDF-Reports anzeigen"** im Filter-Bereich. Wenn aktiviert, erscheint in der Tabelle eine zusätzliche Spalte ".pdf" mit einem Download-Icon/Button pro Zeile.

**Per-TN-Button:** Jede Zeile in der Grades-Tabelle bekommt ein PDF-Icon. Klick darauf:
- Wenn PDF schon generiert: sofortiger Download
- Wenn On-demand-Modus: PDF wird jetzt generiert, dann Download

**Bulk-Download-Button:** Neben dem bestehenden "Download"-Button (CSV) erscheint ein zweiter Button **".pdf-Reports herunterladen"**. Dieser generiert/sammelt alle PDFs der aktuell angezeigten (gefilterten) Versuche und liefert sie je nach Einstellung als ZIP oder als zusammengefügtes PDF.

### 2. Student-Self-Service (optional)

**Wo:** Quiz-Review-Seite (nach Absenden eines Versuchs)

**Verhalten:** Wenn "Student darf herunterladen" aktiviert ist UND der Versuch laut PDF-Scope berechtigt ist, erscheint ein "Download certificate"-Link auf der Review-Seite. Identisch zum bisherigen Verhalten.

### 3. PDF-Erzeugung

**Automatisch (bei Abgabe):** Observer auf `attempt_submitted` erzeugt das PDF sofort und speichert es in der DB-Tabelle `local_eledia_exam2pdf`. So wie aktuell implementiert.

**On-demand (bei Klick):** Kein Observer. Das PDF wird erst beim Klick auf den Button erzeugt. Vorteil: kein Speicherverbrauch für Versuche die niemand als PDF braucht.

In beiden Modi kann der Teacher über den Button in der Results-Tabelle ein PDF (nach-)generieren.

## Technische Umsetzung

### Quiz-Report-Plugin vs. Hook

**Option A — Quiz-Report-Subplugin:** Ein `quiz_exam2pdf`-Report-Plugin das als eigener Tab unter Results erscheint. Nachteil: separater Tab, nicht in der bestehenden Grades-Tabelle integriert.

**Option B — Hook in die Grades-Tabelle:** Über Moodle-Hooks die bestehende Grades-Tabelle erweitern (zusätzliche Spalte + Button). Vorteil: nahtlose Integration. Nachteil: Hooks dafür existieren möglicherweise nicht in allen Moodle-Versionen.

**Option C — Eigene Report-Seite im Local-Plugin:** Eine eigene Seite `/local/eledia_exam2pdf/report.php` die vom Quiz aus verlinkt wird (z.B. über Navigation-Hook). Zeigt dieselben Daten wie Grades, aber mit PDF-Spalte und Bulk-Button. Vorteil: volle Kontrolle, keine Abhängigkeit von Quiz-internen Hooks.

Wir haben uns für **Option C** entschieden: Eigene Report-Seite mit voller Kontrolle über Tabelle und UI, kompatibel mit Moodle 4.5 LTS bis 5.1.

## Berechtigungen

| Capability | Beschreibung | Default |
|---|---|---|
| `local/eledia_exam2pdf:downloadown` | Eigenes PDF herunterladen (Student) | student |
| `local/eledia_exam2pdf:downloadall` | Alle PDFs herunterladen (Teacher) | editingteacher, manager |
| `local/eledia_exam2pdf:generatepdf` | PDF (nach-)generieren | editingteacher, manager |

## Phasenplan

**Phase 1** ✅ CI grün — PHPUnit + Behat bestehen auf allen 4 Matrix-Zellen.

**Phase 2** (jetzt) — Konzept-Finalisierung + Plugin-Umbenennung + Admin-Settings erweitern.

**Phase 3** — Quiz-Results-Integration implementieren (Hauptfeature).

**Phase 4** — Bulk-Download + On-demand-Generierung.

**Phase 5** — Smoketest + Plugin-Directory-Submission.
