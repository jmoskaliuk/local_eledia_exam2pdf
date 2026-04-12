# User Documentation — eLeDia | exam2pdf

## Für wen ist dieses Dokument?

Dieses Dokument beschreibt das Plugin aus Nutzersicht — für Trainer/innen, Manager/innen, Lernende und Administrator/innen.

---

## Trainer/innen und Manager/innen (Hauptzielgruppe)

### PDF-Berichte über die Quiz-Ergebnisseite

Das Plugin fügt eine eigene Report-Seite hinzu, erreichbar über **Quiz → Ergebnisse → PDF-Reports** (oder direkt über den Link in der Quiz-Navigation).

Auf dieser Seite sehen Sie:
- Eine Übersichtstabelle aller Quizversuche (wie die Grades-Ansicht)
- Eine zusätzliche PDF-Spalte mit Download-Icon pro Versuch
- Einen Bulk-Download-Button für alle angezeigten Reports

### Einzelne PDFs herunterladen

Klicken Sie auf das PDF-Icon neben einem Versuch, um den Bericht herunterzuladen. Je nach Konfiguration:
- **Auto-Modus**: Das PDF wurde bereits bei Abgabe erzeugt → sofortiger Download
- **On-demand-Modus**: Das PDF wird beim Klick erzeugt → kurze Wartezeit, dann Download

### Bulk-Download

Der Button **".pdf-Reports herunterladen"** sammelt alle PDFs der aktuell angezeigten (gefilterten) Versuche. Das Ergebnis ist je nach Admin-Einstellung:
- Ein **ZIP-Archiv** mit einem PDF pro Versuch (Dateinamen enthalten TN-Name und Versuchsnummer)
- Ein **zusammengefügtes PDF** mit allen Berichten hintereinander

### Per-Quiz-Konfiguration

Über **Quiz → Einstellungen → PDF Certificate settings** können Sie das Verhalten für ein einzelnes Quiz anpassen:
- Ausgabemodus: Download / E-Mail / Beides
- E-Mail-Empfänger (zusätzlich zum Lernenden)
- E-Mail-Betreff
- Aufbewahrungsfrist in Tagen
- Optionale PDF-Felder
- Korrekte Antworten anzeigen: Ja / Nein

Leere Felder übernehmen den globalen Standard.

### Wer sieht welche PDFs?

- Trainer/innen und Manager/innen mit der Berechtigung `downloadall`: alle PDFs eines Quiz über die Report-Seite
- Lernende: nur eigene PDFs, nur wenn "Student darf herunterladen" aktiviert ist (Admin-Einstellung)

---

## Lernende

### Wann erhalte ich ein PDF?

Ein PDF wird erzeugt, wenn Sie einen Quiz-Versuch abgeschlossen haben und der Versuch in den konfigurierten PDF-Scope fällt (z.B. nur bestandene Versuche oder alle abgeschlossenen).

**Wichtig:** Der Student-Download ist eine optionale Funktion. Ihr/e Administrator/in kann diese deaktivieren — in dem Fall sind PDFs nur für Trainer/innen über die Report-Seite verfügbar.

### Wie komme ich an mein PDF?

Falls der Student-Download aktiviert ist:

**Download:**
Nach Abschluss des Quiz sehen Sie auf der Ergebnisseite einen blauen Button **„Download certificate"**. Klicken Sie darauf, um das PDF herunterzuladen.

Fällt Ihr Versuch nicht in den PDF-Scope (z.B. nicht bestanden bei Scope "nur bestanden"), ist der Button grau und deaktiviert.

**E-Mail:**
Je nach Konfiguration erhalten Sie das PDF als Anhang an Ihre Moodle-E-Mail-Adresse.

### Was steht im PDF?

Das PDF enthält immer: Ihren Namen, den Quiz-Namen und ob Sie bestanden haben.

Je nach Konfiguration zusätzlich: erreichte Punkte, Bestehensgrenze, Prozentzahl, Datum/Uhrzeit, Dauer, Versuchsnummer und alle Fragen mit Ihren Antworten.

---

## Administrator/innen

### Globale Konfiguration

**Website-Administration → Plugins → Lokale Plugins → eLeDia | exam2pdf**

| Einstellung | Beschreibung |
|---|---|
| PDF-Erzeugung | Bei Abgabe (automatisch) oder On-demand (bei Klick) |
| PDF-Scope | Nur bestandene Versuche oder alle abgeschlossenen |
| Student darf herunterladen | Download-Button auf der Student-Review-Seite anzeigen |
| Bulk-Format | ZIP mit einzelnen PDFs oder ein zusammengefügtes PDF |
| Ausgabemodus | Download, E-Mail oder Beides |
| Aufbewahrungsfrist | Tage bis Auto-Löschung (0 = unbegrenzt) |
| Optionale Felder | Welche Zusatzfelder im PDF erscheinen |
| Korrekte Antworten | Im PDF anzeigen oder nicht |

### Installation

```bash
# Plugin-Ordner nach local/eledia_exam2pdf kopieren
# Dann Moodle-Upgrade ausführen:
php admin/cli/upgrade.php
```

Anschließend **Website-Administration → Benachrichtigungen** aufrufen.

### Scheduled Task

Der Cleanup-Task läuft täglich um 02:30 Uhr und löscht alle PDFs, deren Aufbewahrungsfrist abgelaufen ist:

```bash
php admin/cli/scheduled_task.php --execute='\local_eledia_exam2pdf\task\cleanup_expired_pdfs'
```

### Berechtigungen

| Capability | Beschreibung | Standard |
|---|---|---|
| `local/eledia_exam2pdf:downloadown` | Eigenes PDF herunterladen | Student, Teacher |
| `local/eledia_exam2pdf:downloadall` | Alle PDFs herunterladen (Report-Seite) | Editing Teacher, Manager |
| `local/eledia_exam2pdf:generatepdf` | PDF (nach-)generieren | Editing Teacher, Manager |
| `local/eledia_exam2pdf:configure` | Per-Quiz-Settings ändern | Editing Teacher, Manager |
